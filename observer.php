<?php
/*
Plugin Name: Observer
Plugin URI: http://amwhalen.com
Description: Creates an audit trail log file for multisite super admin actions. Used only for multisite installations, and must be Network Activated.
Version: 1.0.3
Author: Andy Whalen
Author URI: http://amwhalen.com
License: The MIT License
*/

$logfile = (defined('OBSERVER_LOG')) ? OBSERVER_LOG : '';
require_once dirname(__FILE__) . '/lib/AMWObserver.php';
$amw_observer = new AMWObserver($logfile);

// Load this plugin at the correct point in the execution flow so is_super_admin() and wp_get_current_user() work.
// Without this there's an error: Call to undefined function wp_get_current_user().
// See: http://stackoverflow.com/questions/6127559/wordpress-plugin-call-to-undefined-function-wp-get-current-user
add_action('plugins_loaded', array(&$amw_observer, 'observe'));