<?php
declare(strict_types=1);

namespace Vie\Frontend;

use Vie\Container;
use Vie\Repository\BookingQuoteRepository;

/** A private quotation document on the main site, not an admin SPA route. */
final class BookingQuotePage
{
    public const QUERY_VAR = 'vie_booking_quote';
    private const REWRITE_VERSION = '1';

    public static function register(): void
    {
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = self::QUERY_VAR;
            return $vars;
        });
        self::addRewrite();
        add_action('wp_loaded', [self::class, 'maybeFlushRewrites']);
        add_action('parse_request', [self::class, 'disableCache']);
        add_action('template_redirect', [self::class, 'serve'], 0);
        add_filter('redirect_canonical', static function ($redirect) {
            return get_query_var(self::QUERY_VAR) !== '' ? false : $redirect;
        });
    }

    private static function addRewrite(): void
    {
        // Also serve malformed IDs as a generic unavailable document, never as
        // an accidental numeric WordPress post or an enumerable order lookup.
        add_rewrite_rule('^booking/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top');
    }

    public static function maybeFlushRewrites(): void
    {
        if (get_option('vie_booking_quote_rewrite_version') === self::REWRITE_VERSION) {
            return;
        }
        flush_rewrite_rules(false);
        update_option('vie_booking_quote_rewrite_version', self::REWRITE_VERSION, false);
    }

    public static function disableCache(\WP $wp): void
    {
        if (empty($wp->query_vars[self::QUERY_VAR])) {
            return;
        }
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        do_action('litespeed_control_set_nocache', 'Private booking quotation');
    }

    public static function serve(): void
    {
        $rawId = get_query_var(self::QUERY_VAR);
        if ($rawId === '') {
            return;
        }
        $publicId = is_string($rawId) && preg_match('/^[a-f0-9]{32}$/D', $rawId) ? $rawId : '';
        $status = 404;
        if ($publicId !== '') {
            try {
                $quote = Container::get(BookingQuoteRepository::class)->findByPublicId($publicId);
                if ($quote !== null && $quote['status'] === 'published') {
                    $status = 200;
                }
            } catch (\Throwable) {
                // Do not leak SQL, customer data or the private URL into logs.
                $status = 503;
            }
        }

        $assets = PublicAssets::standaloneEntry('src/entries/booking.ts');
        if ($assets === null) {
            $status = 503;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        status_header($status);
        nocache_headers();
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Content-Type: text/html; charset=utf-8');
        $cspNonce = base64_encode(random_bytes(18));
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}'; "
            . "style-src 'self' 'unsafe-inline'; img-src 'self' data: https://vietqr.app; font-src 'self' data:; "
            . "connect-src 'self'; form-action 'self'; "
            . "frame-ancestors 'none'; object-src 'none'; base-uri 'none'");

        $boot = [
            'root' => esc_url_raw(rest_url(VIE_API_NAMESPACE . '/')),
            'homeUrl' => esc_url_raw(home_url('/')),
        ];
        require VIE_CHILD_PATH . '/inc/templates/frontend/booking-quote-page.php';
        exit;
    }
}
