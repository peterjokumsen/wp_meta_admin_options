<?php

/*
Plugin Name: PJ Admin General
Plugin URI: http://pjokumsen.co.za/wordpress/pj-admin-general/
Description: Plugin to showcase generalising admin options into an array for easy options page creation.
Version: 1.0.0
Author: Peter Jokumsen
Author URI: http://pjokumsen.co.za/
*/

include_once 'classes.php';

function pj_ag_initialise() {
  $pj_ag_controller = new PJ_AG_Controller('PJ Admin General');
}
add_action('plugins_loaded', 'pj_ag_initialise');

function pj_ag_activation() {
  $pj_ag_controller = new PJ_AG_Controller();
  $pj_ag_controller->register_plugin();
}
register_activation_hook(__FILE__, 'pj_ag_activation');