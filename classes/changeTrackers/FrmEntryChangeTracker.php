<?php
if ( ! defined('ABSPATH') ) { exit; }

class FrmEntryChangeTracker {

    protected $entryMetasBefore;
    protected $entryService;

    public function __construct(
        FrmHistoryEntryService $entryService = null
    ) {

        $this->entryService = $entryService ?: new FrmHistoryEntryService();

        // Hooks/filters to track entry changes
        add_filter('frm_pre_update_entry', array( $this, 'getPreUpdateEntry' ), 10, 2);
        add_action('frm_after_update_entry', array( $this, 'compareEntriesUpdate' ), 10, 2);
        add_action('frm_after_create_entry', array( $this, 'compareEntriesCreate' ), 10, 2);

    }

    public function getPreUpdateEntry( $values, $entry_id ) {

        $this->entryMetasBefore = $this->getEntryMetas( $entry_id );

        // It's important to return the $values array because filters must always return a value
        return $values;

    }

    public function compareEntriesUpdate( $entry_id, $new_metas ) {


        // Get the old entry metas
        $old_metas = $this->entryMetasBefore;

        // New entry metas are passed as $new_metas
        $new_metas = $this->getEntryMetas( $entry_id );

        $changes = $this->findChanges( $old_metas, $new_metas );
        if ( empty( $changes ) ) {
            return; // No changes detected
        }

        // Format changes for API
        $formattedChanges = $this->prepareChangeForApi( $changes );

        // Prepare payload for API and send
        $payload = [
            'entry_id' => $entry_id,
            'site_id' => 1,
            'updated' => $formattedChanges,
            'created' => [],
        ];
        $res = $this->entryService->updateEntryHistory( $payload );

        return $res;
    }    

    public function compareEntriesCreate( $entry_id, $new_metas ) {

        // New entry metas are passed as $new_metas
        $new_metas = $this->getEntryMetas( $entry_id );

        $changes = $this->findChanges( [], $new_metas );
        if ( empty( $changes ) ) {
            return; // No changes detected
        }

        // Format changes for API
        $formattedChanges = $this->prepareChangeForApi( $changes );

        // Prepare payload for API and send
        $payload = [
            'entry_id' => $entry_id,
            'site_id' => 1,
            'updated' => [],
            'created' => $formattedChanges,
        ];
        $res = $this->entryService->updateEntryHistory( $payload );

        ///return $res;
    }    

    protected function prepareChangeForApi( $changes ) {

        $formattedChanges = array();

        foreach ( $changes as $key => $change ) {
            $formattedChanges[] = array(
                'field_id' => $key,
                'old_value' => is_array( $change['old'] ) ? json_encode( $change['old'] ) : $change['old'],
                'new_value' => is_array( $change['new'] ) ? json_encode( $change['new'] ) : $change['new'],
                'change_date' => date('Y-m-d H:i:s'),
            );
        }

        return $formattedChanges;

    }

    protected function findChanges( $old_metas, $new_metas ) {

        // value can be array or string
        $changes = array();

        foreach ( $new_metas as $key => $new_value ) {

            $old_value = isset( $old_metas[$key] ) ? $old_metas[$key] : null;

            if ( is_array( $new_value ) || is_array( $old_value ) ) {
                if ( json_encode( $new_value ) !== json_encode( $old_value ) ) {
                    $changes[$key] = array( 'old' => $old_value, 'new' => $new_value );
                }
            } else {
                if ( $new_value !== $old_value ) {
                    $changes[$key] = array( 'old' => $old_value, 'new' => $new_value );
                }
            }

        }

        return $changes;

    }

    protected function getEntryMetas( int $entry_id ) {

        $entry = FrmEntry::getOne( $entry_id, true );
        return $entry->metas;

    }    



}


add_action('plugins_loaded', function() {
    new FrmEntryChangeTracker();
});