<?php

/**
 * Plugin Name: Svgstore
 * Description:       Compile a collection of SVG files in a single file, and inject it in the footer of the page to use them anywhere.
 * Requires at least: 6.1
 * Requires PHP:      8.1
 * Author:            Dimitri Avenel
 * License:           MIT
 */

if (!defined('ABSPATH')) {
    exit();
}

require_once 'class/svgstore.php';

svgstore::init();

// wp-cli
if (defined('WP_CLI') && constant('WP_CLI')) {
    require_once 'class/Svgstore_CLI.php';
    add_action('cli_init', function () {
        WP_CLI::add_command('svgstore', 'Svgstore_CLI');
    });
}
