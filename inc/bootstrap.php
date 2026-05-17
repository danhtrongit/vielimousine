<?php
declare(strict_types=1);

// Composer autoload (dompdf and other third-party deps).
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Vie\\')) {
        return;
    }
    $rel  = substr($class, 4);
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
