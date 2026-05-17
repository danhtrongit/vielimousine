<?php
/**
 * Vielimousine Child — Bootstrap.
 *
 * @package VielimousineChild
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
    return;
}

define( 'VIE_CHILD_VERSION', '2.1.0' );
define( 'VIE_CHILD_PATH', __DIR__ );
define( 'VIE_CHILD_URL', get_stylesheet_directory_uri() );
define( 'VIE_API_NAMESPACE', 'vie/v1' );

require_once __DIR__ . '/inc/bootstrap.php';
\Vie\Plugin::boot();

