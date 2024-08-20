<?php

/**
 * Plugin Name:  Castlegate IT WP Site Manager
 * Plugin URI:   https://github.com/castlegateit/cgit-wp-site-manager
 * Description:  Site Manager user role with limited admin capabilities.
 * Version:      1.5.0
 * Requires PHP: 8.2
 * Author:       Castlegate IT
 * Author URI:   https://www.castlegateit.co.uk/
 * License:      MIT
 * Update URI:   https://github.com/castlegateit/cgit-wp-site-manager
 */

use Castlegate\SiteManager\Plugin;

if (!defined('ABSPATH')) {
    wp_die('Access denied');
}

define('CGIT_WP_SITE_MANAGER_VERSION', '1.5.0');
define('CGIT_WP_SITE_MANAGER_PLUGIN_FILE', __FILE__);
define('CGIT_WP_SITE_MANAGER_PLUGIN_DIR', __DIR__);

require_once __DIR__ . '/vendor/autoload.php';

Plugin::init();
