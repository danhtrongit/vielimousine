<?php
declare(strict_types=1);

namespace Vie\Email;

use Vie\Service\Settings\EmailSettings;

final class TemplateRenderer
{
    public function __construct(private readonly EmailSettings $settings) {}

    /**
     * Render 1 template HTML và bọc trong layout chung.
     *
     * @param array<string,mixed> $ctx
     */
    public function render(string $name, array $ctx): string
    {
        $ctx = $this->fillCommonContext($ctx);

        $override = $this->settings->templateOverride($name);
        if ($override !== null && !empty($override['body'])) {
            // Body override → HTML context → escape placeholder mặc định.
            $inner = $this->applyPlaceholders((string) $override['body'], $ctx, escape: true);
        } else {
            $inner = $this->includeTemplate($name, $ctx);
        }

        return $this->wrapLayout($inner, $ctx);
    }

    /**
     * Subject: lấy override nếu có, fallback default; thay placeholder {key}.
     *
     * @param array<string,mixed> $ctx
     */
    public function renderSubject(string $name, string $default, array $ctx): string
    {
        $ctx = $this->fillCommonContext($ctx);
        $override = $this->settings->templateOverride($name);
        $tpl = ($override !== null && !empty($override['subject']))
            ? (string) $override['subject']
            : $default;
        // Subject là plain-text trong email header → không cần escape HTML,
        // nhưng vẫn loại bỏ ký tự CR/LF để chống header-injection.
        return $this->stripControlChars($this->applyPlaceholders($tpl, $ctx, escape: false));
    }

    /**
     * Thay placeholder `{key}` bằng giá trị từ `$ctx`.
     *
     * - `$escape = true`: giá trị được `esc_html()` (mặc định cho body HTML).
     *   Đây là chống XSS qua tên khách hàng / customer_note có chứa thẻ HTML.
     * - `$escape = false`: giữ raw (cho subject hoặc field đã pre-rendered HTML
     *   như `items` table). Key có tiền tố `_raw_` luôn bypass escape ngay cả khi
     *   `$escape = true` — dành cho HTML chunks build sẵn trong code.
     */
    public function applyPlaceholders(string $template, array $ctx, bool $escape = true): string
    {
        return preg_replace_callback(
            '/\{([a-z][a-z0-9_]*)\}/i',
            static function ($m) use ($ctx, $escape) {
                $key   = $m[1];
                $value = $ctx[$key] ?? '';
                if (is_array($value) || is_object($value)) {
                    return $m[0];
                }
                $str = (string) $value;
                if (!$escape || str_starts_with($key, '_raw_')) {
                    return $str;
                }
                if (function_exists('esc_html')) {
                    return esc_html($str);
                }
                return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            },
            $template
        ) ?? $template;
    }

    private function stripControlChars(string $s): string
    {
        return preg_replace('/[\x00-\x1F\x7F]/', '', $s) ?? $s;
    }

    private function includeTemplate(string $name, array $ctx): string
    {
        $file = VIE_CHILD_PATH . '/inc/src/Email/Templates/' . $name . '.php';
        if (!is_file($file)) {
            return '';
        }
        ob_start();
        // phpcs:disable
        (static function (string $__file, array $ctx): void {
            include $__file;
        })($file, $ctx);
        // phpcs:enable
        return (string) ob_get_clean();
    }

    private function wrapLayout(string $body, array $ctx): string
    {
        $file = VIE_CHILD_PATH . '/inc/src/Email/Templates/layout.php';
        if (!is_file($file)) {
            return $body;
        }
        ob_start();
        (static function (string $__file, string $body, array $ctx): void {
            include $__file;
        })($file, $body, $ctx);
        return (string) ob_get_clean();
    }

    private function fillCommonContext(array $ctx): array
    {
        $defaults = [
            'site_name' => (string) get_option('blogname', 'Vielimousine'),
            'site_url'  => home_url('/'),
            'logo_url'  => $this->settings->logoUrl(),
        ];
        return array_merge($defaults, $ctx);
    }
}
