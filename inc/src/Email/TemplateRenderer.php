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
            $inner = $this->applyPlaceholders((string) $override['body'], $ctx);
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
        return $this->applyPlaceholders($tpl, $ctx);
    }

    public function applyPlaceholders(string $template, array $ctx): string
    {
        return preg_replace_callback(
            '/\{([a-z][a-z0-9_]*)\}/i',
            static function ($m) use ($ctx) {
                $key = $m[1];
                $value = $ctx[$key] ?? '';
                if (is_array($value) || is_object($value)) {
                    return $m[0];
                }
                return (string) $value;
            },
            $template
        ) ?? $template;
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
