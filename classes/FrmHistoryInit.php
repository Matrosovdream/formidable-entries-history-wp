<?php
class FrmHistoryInit {

    public function __construct() {

        // Trackers
        $this->include_trackers();

        // Admin settings
        $this->include_admin();
        

        // Admin classes
        /*
        require_once FRM_EAP_BASE_URL.'/classes/admin/FrmEasypostAdminSettings.php';

        // API class
        $this->include_api();

        // Shortcodes
        $this->include_shortcodes();

        // Migrations
        $this->include_migrations();

        // Models
        $this->include_models();

        // Helpers
        $this->include_helpers();

        // CRON
        $this->include_cron();

        // Hooks
        $this->include_hooks();
        */

    }

    private function include_admin() {

        require_once FRM_EH_BASE_URL.'/classes/admin/FrmHistoryAdminSettings.php';
        
    }

    private function include_trackers() {

        require_once FRM_EH_BASE_URL.'/classes/changeTrackers/FrmEntryChangeTracker.php';
        
    }

}

new FrmHistoryInit();