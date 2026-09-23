<?php
declare(strict_types=1);

namespace HD {
    final class Helper {
        public static function getFields(int $postId): array { return []; }
        public static function getField(string $key, string $scope = ''): mixed { return false; }
        public static function blockTemplate(string $name): void {}
    }
}

namespace {
    const ABSPATH = '/';

    function get_header(): void {}
    function get_footer(): void {}
    function have_posts(): bool { return false; }
    function post_password_required(): bool { return false; }
    function get_the_ID(): int { return 1; }
    function get_field(string $key, string $scope = ''): mixed {
        return $key === 'list_tips' ? $GLOBALS['route_tips_fixture'] : false;
    }
    function do_shortcode(string $shortcode): string { return ''; }
    function get_template_part(string $name): void {}
    function esc_url(string $url): string { return htmlspecialchars($url, ENT_QUOTES); }

    $template = __DIR__ . '/../../../templates/page-route-limo.php';
    $render = static function (array $tip) use ($template): string {
        $GLOBALS['route_tips_fixture'] = [$tip];
        $bufferLevel = ob_get_level();
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });
        ob_start();
        try {
            include $template;
            return ob_get_clean();
        } finally {
            if (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            restore_error_handler();
        }
    };

    $withoutLink = $render(['img' => '/tip.jpg', 'gallery' => [], 'title' => 'Travel tip']);
    if (!str_contains($withoutLink, '<h3 class="title">') ||
        !str_contains($withoutLink, 'Travel tip') ||
        preg_match('/<h3 class="title">\s*<a\b/', $withoutLink)) {
        throw new \RuntimeException('A tip without a link must render its title without an anchor.');
    }

    $withLink = $render(['img' => '/tip.jpg', 'gallery' => [], 'title' => 'Travel tip', 'link' => '/route']);
    if (!preg_match('/<h3 class="title">\s*<a href="\/route">\s*Travel tip\s*<\/a>/', $withLink)) {
        throw new \RuntimeException('A tip with a link must retain its linked title.');
    }

    echo "Page route tip link: 2 passed\n";
}
