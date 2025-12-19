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
        syncFields();
        exit();
    }

    if( isset( $_GET['history'] ) ) {
        getEntryHistory();
        exit();
    }

    if( isset( $_GET['update_logs_ids'] ) ) {
        updateLogsByIds();
        exit();
    }

    if( isset( $_GET['remove_old_logs'] ) ) {

        removeOldLogs();
        exit();

    }

    if( isset( $_GET['update_log_chunks'] ) ) {

        updateLogChunks();
        exit();

    }

    if( isset( $_GET['emails_all'] ) ) {

        emailsLogAll();
        exit();

    }

}

function emailsLogAll() {

    $payload = [
        "filters" => [
            "entry_id" => "",
            "subject" => "",
            "email_from" => "",
            "email_to" => "",
            "date_from" => "",
            "date_to" => ""
        ],
        "paginate" => 25,
        "sorting" => [
            "id" => "desc"
        ]
    ];

    $service = new FrmEmailLogService();
    $result = $service->getEmailLogsAll($payload);

    echo '<pre>';
    print_r( $result );
    echo '</pre>';

}

function removeOldLogs() {

    global $wpdb;

    // Remove from wp_frm_emails_log where id < some_value
    $sql = "DELETE FROM {$wpdb->prefix}frm_emails_log WHERE id < %d LIMIT 50000";
    $value = 3;
    $wpdb->query( $wpdb->prepare( $sql, $value ) );

    echo 'Old logs removed successfully.';

}

function updateLogsByIds() {

    $log_ids = [3210185540, 3210161783];

    $service = new FrmEmailLogService();
    $result = $service->updateLogsByIds($log_ids);

    echo '<pre>';
    print_r( $result );
    echo '</pre>';

}

function updateLogChunks() {

    $chunk_size = 200;

    $service = new FrmEmailLogService();
    $result = $service->updateByChunks($chunk_size);

    echo '<pre>';
    print_r( $result );
    echo '</pre>';

}

function getEntryHistory() {

    $entry_id = 15;

    $service = new FrmHistoryEntryService();
    $result = $service->getEntryHistory($entry_id);

    echo '<pre>';
    print_r( $result );
    echo '</pre>';

}    

function syncFields() {

    $service = new FrmHistoryFieldsService();
    $result = $service->getEntryHistory(15);

    echo '<pre>';
    print_r( $result );
    echo '</pre>';

}
