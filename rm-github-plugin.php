<?php
/**
 * Plugin Name:  RM GitHub Plugin
 * Version:      2.1.0
 * Description:  A professional plugin with GitHub update integration.
 * Author:       Plugin updater
 * GitHub:      https://github.com/Jared-Nolt/rm-github-plugin
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Load the updater logic
require_once plugin_dir_path( __FILE__ ) . 'updater/updater.php';

// Initialize the updater class
new RM_Plugin_Updater( __FILE__ );