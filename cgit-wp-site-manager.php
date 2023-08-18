<?php

/*

Plugin Name: Castlegate IT WP Site Manager
Plugin URI: https://github.com/castlegateit/cgit-wp-site-manager
Description: Site Manager user role with limited admin capabilities.
Version: 1.2.0
Author: Castlegate IT
Author URI: https://www.castlegateit.co.uk/
Network: true

Copyright (c) 2019 Castlegate IT. All rights reserved.

*/

if (!defined('ABSPATH')) {
    wp_die('Access denied');
}

define('CGIT_SITE_MANAGER_PLUGIN', __FILE__);

require_once __DIR__ . '/classes/autoload.php';

$plugin = new \Cgit\SiteManager\Plugin;

do_action('cgit_site_manager_plugin', $plugin);
do_action('cgit_site_manager_loaded');
