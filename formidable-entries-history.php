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

add_action('init', 'init222');
function init222() {
    
    if( isset( $_GET['sync'] ) ) {

        $service = new FrmHistoryFieldsService();
        $result = $service->syncFields();
        echo '<pre>';
        print_r( $result );
        echo '</pre>';
        exit;
        
    }

}
