<?php
declare(strict_types=1);

namespace Vie\Cron;

final class CronRegistry
{
    /** @var array<string,class-string> */
    public const HOOKS = [
        'vie_security_sweep' => SecuritySweep::class,
        'vie_no_show_sweep'  => NoShowSweep::class,
        'vie_token_cleanup'  => TokenCleanup::class,
    ];

    public static function register(): void
    {
        foreach (self::HOOKS as $hook => $class) {
            add_action($hook, [$class, 'run']);
        }
        add_action('init', [self::class, 'schedule']);
        add_action('switch_theme', [self::class, 'unschedule']);
    }

    public static function schedule(): void
    {
        $offset = 60;
        foreach (array_keys(self::HOOKS) as $hook) {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time() + $offset, 'daily', $hook);
                $offset += 60;
            }
        }
    }

    public static function unschedule(): void
    {
        foreach (array_keys(self::HOOKS) as $hook) {
            $ts = wp_next_scheduled($hook);
            if ($ts) {
                wp_unschedule_event($ts, $hook);
            }
            wp_clear_scheduled_hook($hook);
        }
    }
}
