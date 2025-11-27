<?php
/*
Plugin Name: Formidable Entries History Extension
Description: 
Version: 1.0
Plugin URI: 
Author URI: 
Author: Stanislav Matrosov
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Variables
define('FRM_EH_BASE_URL', __DIR__);
define('FRM_EH_BASE_URL', plugin_dir_url(__FILE__));

// Initialize core
require_once 'classes/FrmHistoryInit.php';
