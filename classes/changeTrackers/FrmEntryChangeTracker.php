<?php
if ( ! defined('ABSPATH') ) { exit; }

class FrmEntryChangeTracker {

    protected $entryMetasBefore;
    protected $entryService;
    protected bool $lockMetaCheck = false;

    /**
     * Cron hook name for deferred history updates.
     */
    protected const CRON_HOOK = 'frm_entry_history_update_single_event';

    public function __construct(
        FrmHistoryEntryService $entryService = null
    ) {
        $this->entryService = $entryService ?: new FrmHistoryEntryService();

        // Hooks/filters to track entry changes
        add_filter( 'frm_pre_update_entry', array( $this, 'getPreUpdateEntry' ), 10, 2 );
        add_action( 'frm_after_update_entry', array( $this, 'compareEntriesUpdate' ), 10, 2 );
        add_action( 'frm_after_create_entry', array( $this, 'compareEntriesCreate' ), 10, 2 );

        // Entry update/create hooks to track changes on single meta update
        add_filter( 'frm_update_entry_meta', array( $this, 'getPreUpdateEntry2' ), 10, 2 );

        // Cron handler for deferred sending
        add_action( self::CRON_HOOK, array( $this, 'handleSingleEvent' ), 10, 1 );
    }

    /**
     * Called on single meta updates.
     * Signature must match `add_filter( 'frm_update_entry_meta', ..., 10, 2 )`.
     */
    public function getPreUpdateEntry2( $values, $field = null ) {

        if ( $this->lockMetaCheck ) {
            return $values;
        }

        $entry_id  = isset( $values['item_id'] )   ? (int) $values['item_id']   : 0;
        $field_id  = isset( $values['field_id'] )  ? (int) $values['field_id']  : 0;
        $new_value = isset( $values['meta_value'] ) ? $values['meta_value']     : null;

        if ( ! $entry_id || ! $field_id ) {
            return $values;
        }

        $entryMetas = $this->getEntryMetas( $entry_id );
        $old_value  = isset( $entryMetas[ $field_id ] ) ? $entryMetas[ $field_id ] : null;

        if ( $old_value != $new_value ) {
            // Prepare change record
            $changes[ $field_id ] = array(
                'old' => $old_value,
                'new' => $new_value,
            );

            $formattedChanges = $this->prepareChangeForApi( $changes );

            // Prepare payload for API and queue it to cron
            $payload = [
                'entry_id' => $entry_id,
                'user_id'  => get_current_user_id(),
                'site_id'  => 1,
                'updated'  => $formattedChanges,
                'created'  => [],
            ];

            $this->addSingleEvent( $payload );
        }

        // Must return the values array
        return $values;
    }

    public function getPreUpdateEntry( $values, $entry_id ) {

        $this->lockMetaCheck    = true;
        $this->entryMetasBefore = $this->getEntryMetas( $entry_id );

        // Must return the values array
        return $values;
    }

    public function compareEntriesUpdate( $entry_id, $new_metas ) {

        // Get the old entry metas
        $old_metas = $this->entryMetasBefore;

        // New entry metas
        $new_metas = $this->getEntryMetas( $entry_id );

        $changes = $this->findChanges( $old_metas, $new_metas );
        if ( empty( $changes ) ) {
            $this->lockMetaCheck = false;
            return;
        }

        // Format changes for API
        $formattedChanges = $this->prepareChangeForApi( $changes );

        // Prepare payload for API and queue it
        $payload = [
            'entry_id' => $entry_id,
            'user_id'  => get_current_user_id(),
            'site_id'  => 1,
            'updated'  => $formattedChanges,
            'created'  => [],
        ];

        $this->addSingleEvent( $payload );

        $this->lockMetaCheck = false;
    }

    public function compareEntriesCreate( $entry_id, $new_metas ) {

        // New entry metas
        $new_metas = $this->getEntryMetas( $entry_id );

        $changes = $this->findChanges( [], $new_metas );
        if ( empty( $changes ) ) {
            return; // No changes detected
        }

        // Format changes for API
        $formattedChanges = $this->prepareChangeForApi( $changes );

        // Prepare payload for API and queue it
        $payload = [
            'entry_id' => $entry_id,
            'user_id'  => get_current_user_id(),
            'site_id'  => 1,
            'updated'  => [],
            'created'  => $formattedChanges,
        ];

        $this->addSingleEvent( $payload );
    }

    /**
     * NEW: Schedule a single WP-Cron event instead of calling the API directly.
     *
     * @param array $payload
     */
    protected function addSingleEvent( array $payload ): void {

        // When the cron fires, it will call self::CRON_HOOK with $payload
        // Run as soon as possible; you can add +10 seconds if you want
        $timestamp = time();

        // Optional: avoid scheduling an identical event twice in the same request
        if ( ! wp_next_scheduled( self::CRON_HOOK, array( $payload ) ) ) {
            wp_schedule_single_event(
                $timestamp,
                self::CRON_HOOK,
                array( $payload )
            );
        }
    }

    /**
     * NEW: Cron handler for the scheduled single event.
     * This is where we actually call the API.
     *
     * @param array $payload
     */
    public function handleSingleEvent( $payload ): void {

        if ( ! is_array( $payload ) || empty( $payload['entry_id'] ) ) {
            return;
        }

        // Finally call the actual service method
        $this->entryService->updateEntryHistory( $payload );
    }

    protected function prepareChangeForApi( $changes ) {

        $formattedChanges = array();

        foreach ( $changes as $key => $change ) {
            $formattedChanges[] = array(
                'field_id'    => $key,
                'old_value'   => is_array( $change['old'] ) ? json_encode( $change['old'] ) : $change['old'],
                'new_value'   => is_array( $change['new'] ) ? json_encode( $change['new'] ) : $change['new'],
                'change_date' => date( 'Y-m-d H:i:s' ),
            );
        }

        return $formattedChanges;
    }

    protected function findChanges( $old_metas, $new_metas ) {

        // value can be array or string
        $changes = array();

        foreach ( $new_metas as $key => $new_value ) {

            $old_value = isset( $old_metas[ $key ] ) ? $old_metas[ $key ] : null;

            if ( is_array( $new_value ) || is_array( $old_value ) ) {
                if ( json_encode( $new_value ) !== json_encode( $old_value ) ) {
                    $changes[ $key ] = array( 'old' => $old_value, 'new' => $new_value );
                }
            } else {
                if ( $new_value !== $old_value ) {
                    $changes[ $key ] = array( 'old' => $old_value, 'new' => $new_value );
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

add_action( 'plugins_loaded', function() {
    new FrmEntryChangeTracker();
} );
