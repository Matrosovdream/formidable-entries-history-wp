<?php
class FrmHistoryInit {

    public function __construct() {

        // Helpers
        $this->include_helpers();

        // References
        $this->include_references();

        // Trackers
        $this->include_trackers();

        // Admin settings
        $this->include_admin();

        // API
        $this->include_api();

        // Services
        $this->include_services();

        // Shortcodes
        $this->include_shortcodes();
        

        // Admin classes
        /*
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

    private function include_shortcodes() {

        require_once FRM_EH_BASE_URL.'/shortcodes/frm-entry-history.php';

    }

    private function include_api() {

        require_once FRM_EH_BASE_URL.'/classes/api/FrmHistoryApiAbstract.php';
        require_once FRM_EH_BASE_URL.'/classes/api/FrmHistoryApi.php';

    }

    private function include_helpers() {
        
        require_once FRM_EH_BASE_URL.'/classes/helpers/FrmHistorySettingsHelper.php';
        require_once FRM_EH_BASE_URL.'/classes/helpers/FrmHistoryFieldsHelper.php';

    }

    private function include_admin() {

        require_once FRM_EH_BASE_URL.'/classes/admin/FrmHistoryAdminSettings.php';

    }

    private function include_trackers() {

        require_once FRM_EH_BASE_URL.'/classes/changeTrackers/FrmEntryChangeTracker.php';
        
    }

    private function include_references() {

        require_once FRM_EH_BASE_URL.'/references.php';

    }

    private function include_services() {

        require_once FRM_EH_BASE_URL.'/classes/services/FrmHistoryFieldsService.php';
        require_once FRM_EH_BASE_URL.'/classes/services/FrmHistoryEntryService.php';

    }

}

new FrmHistoryInit();