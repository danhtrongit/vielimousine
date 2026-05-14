<?php
declare(strict_types=1);

namespace Vie;

final class Plugin
{
    public static function boot(): void
    {
        Schema\SchemaManager::install();
        Service\Auth\RoleInstaller::install();

        add_action('after_switch_theme', [Schema\SchemaManager::class, 'install']);
        add_action('after_switch_theme', [Service\Auth\RoleInstaller::class, 'install']);

        add_action('rest_api_init', [Http\RestRouter::class, 'register']);
        add_action('rest_api_init', [Service\Auth\CorsHandler::class, 'register']);
        add_action('init', [Frontend\AdminAppLoader::class, 'register']);

        Email\OrderEmailService::register();
        Frontend\ShortcodeRegistry::register();
        Frontend\PublicAssets::register();
        Cron\CronRegistry::register();
    }
}
