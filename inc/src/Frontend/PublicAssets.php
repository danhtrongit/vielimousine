<?php
declare(strict_types=1);

namespace Vie\Frontend;

final class PublicAssets
{
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void
    {
        if (is_admin()) {
            return;
        }

        // Legacy vie-public.js — kept for [vie_hotel_search], [vie_checkout],
        // [vie_order_success] shortcodes outside the Vue app (e.g. /dat-phong page).
        wp_enqueue_style(
            'vie-public',
            VIE_CHILD_URL . '/inc/assets/css/frontend.css',
            [],
            VIE_CHILD_VERSION
        );
        wp_enqueue_script(
            'vie-public',
            VIE_CHILD_URL . '/inc/assets/js/vie-public.js',
            [],
            VIE_CHILD_VERSION,
            true
        );
        wp_localize_script('vie-public', 'VieRest', [
            'root'        => esc_url_raw(rest_url(VIE_API_NAMESPACE . '/')),
            'nonce'       => wp_create_nonce('wp_rest'),
            'checkoutUrl' => esc_url_raw(home_url('/dat-phong/')),
            'successUrl'  => esc_url_raw(home_url('/dat-phong-thanh-cong/')),
        ]);

        // Vue 3 public-app — mount selectively per page type.
        $entry = null;
        if (is_singular('hotel') || self::pageHasMount('data-vie-public-hotel')) {
            $entry = 'src/entries/hotel.ts';
        } elseif (self::pageHasMount('data-vie-public-success')) {
            $entry = 'src/entries/success.ts';
        }

        if ($entry === null) {
            return;
        }

        $manifest = self::loadManifest();
        if ($manifest === null) {
            return; // dist not built yet — fall back to legacy JS
        }

        $entryFile = $manifest[$entry] ?? null;
        if (!is_array($entryFile)) {
            return;
        }

        $base = VIE_CHILD_URL . '/public-app/dist/';
        $handleSuffix = sanitize_key(basename($entry, '.ts'));

        // Walk imports recursively to collect all CSS + shared JS chunks.
        $seen = [];
        $cssFiles = [];
        $jsFiles  = [];
        self::walkChunk($entry, $manifest, $seen, $cssFiles, $jsFiles);

        foreach (array_unique($cssFiles) as $css) {
            wp_enqueue_style(
                'vie-public-vue-css-' . md5($css),
                $base . $css,
                [],
                VIE_CHILD_VERSION
            );
        }
        foreach (array_unique($jsFiles) as $js) {
            wp_enqueue_script(
                'vie-public-vue-import-' . md5($js),
                $base . $js,
                [],
                VIE_CHILD_VERSION,
                true
            );
        }

        // Main entry chunk
        wp_enqueue_script(
            'vie-public-vue-' . $handleSuffix,
            $base . $entryFile['file'],
            [],
            VIE_CHILD_VERSION,
            true
        );
        wp_add_inline_script(
            'vie-public-vue-' . $handleSuffix,
            'window.VieRest=' . wp_json_encode([
                'root'       => esc_url_raw(rest_url(VIE_API_NAMESPACE . '/')),
                'nonce'      => wp_create_nonce('wp_rest'),
                'successUrl' => esc_url_raw(home_url('/dat-phong-thanh-cong/')),
            ]) . ';',
            'before'
        );

        // Add type=module to all our Vue scripts via filter
        add_filter('script_loader_tag', [self::class, 'asModule'], 10, 3);
    }

    public static function asModule(string $tag, string $handle, string $src): string
    {
        if (strpos($handle, 'vie-public-vue') !== 0) {
            return $tag;
        }
        // Replace `<script src=...>` with type=module
        return '<script type="module" src="' . esc_url($src) . '" id="' . esc_attr($handle) . '-js"></script>' . "\n";
    }

    /**
     * Recursively gather CSS + shared JS imports for a Vite manifest entry.
     * @param array<string,array> $manifest
     * @param array<string,bool> $seen
     * @param string[] $cssFiles
     * @param string[] $jsFiles  (shared chunks; excludes the entry itself)
     */
    private static function walkChunk(string $key, array $manifest, array &$seen, array &$cssFiles, array &$jsFiles, bool $isEntry = true): void
    {
        if (isset($seen[$key])) return;
        $seen[$key] = true;
        $chunk = $manifest[$key] ?? null;
        if (!is_array($chunk)) return;

        foreach ((array) ($chunk['css'] ?? []) as $css) {
            $cssFiles[] = $css;
        }
        if (!$isEntry && !empty($chunk['file'])) {
            $jsFiles[] = (string) $chunk['file'];
        }
        foreach ((array) ($chunk['imports'] ?? []) as $imp) {
            self::walkChunk($imp, $manifest, $seen, $cssFiles, $jsFiles, false);
        }
    }

    private static function loadManifest(): ?array
    {
        // Vite outputs .vite/manifest.json by default in v5+
        $candidates = [
            VIE_CHILD_PATH . '/public-app/dist/.vite/manifest.json',
            VIE_CHILD_PATH . '/public-app/dist/manifest.json',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                $json = file_get_contents($path);
                $data = json_decode($json, true);
                if (is_array($data)) return $data;
            }
        }
        return null;
    }

    /**
     * Sniff the page content to see if a mount-point attribute is present.
     * Used to decide which Vue entry to load. We check `$post->post_content` first,
     * then short-circuit on hotel single template.
     */
    private static function pageHasMount(string $attr): bool
    {
        global $post;
        if (!$post instanceof \WP_Post) return false;
        return strpos((string) $post->post_content, $attr) !== false;
    }
}
