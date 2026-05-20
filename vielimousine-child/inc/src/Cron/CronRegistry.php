<?php
declare(strict_types=1);

namespace Vie\Cron;

final class CronRegistry
{
    /** @var array<string,array{class:class-string,recurrence:string}> */
    public const HOOKS = [
        'vie_security_sweep' => ['class' => SecuritySweep::class, 'recurrence' => 'vie_15min'],
        'vie_no_show_sweep'  => ['class' => NoShowSweep::class,   'recurrence' => 'daily'],
        'vie_token_cleanup'  => ['class' => TokenCleanup::class,  'recurrence' => 'daily'],
    ];

    public static function register(): void
    {
        add_filter('cron_schedules', [self::class, 'addSchedules']);
        foreach (self::HOOKS as $hook => $cfg) {
            add_action($hook, [$cfg['class'], 'run']);
        }
        add_action('init', [self::class, 'schedule']);
        add_action('switch_theme', [self::class, 'unschedule']);
    }

    public static function addSchedules(array $schedules): array
    {
        if (!isset($schedules['vie_15min'])) {
            $schedules['vie_15min'] = [
                'interval' => 900,
                'display'  => __('Mỗi 15 phút (Vielimo)', 'vielimousine-child'),
            ];
        }
        return $schedules;
    }

    public static function schedule(): void
    {
        $offset = 60;
        foreach (self::HOOKS as $hook => $cfg) {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time() + $offset, $cfg['recurrence'], $hook);
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
