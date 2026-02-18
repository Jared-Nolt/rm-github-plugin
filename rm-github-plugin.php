<?php
/**
 * Plugin Name:  RM GitHub Plugin
 * Version:      2.1.0
 * Description:  A professional plugin with GitHub update integration.
 * Author:       Plugin updater
 * GitHub:      https://github.com/Jared-Nolt/rm-github-plugin
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'RM_GITHUB_PLUGIN_VERSION', '2.1.0' );
define( 'RM_GITHUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Load the updater logic
require_once RM_GITHUB_PLUGIN_DIR . 'updater/updater.php';

// Initialize the updater class in admin context
if ( is_admin() && class_exists( '\RM_GitHub_Plugin\Updater' ) ) {
    new \RM_GitHub_Plugin\Updater();
}