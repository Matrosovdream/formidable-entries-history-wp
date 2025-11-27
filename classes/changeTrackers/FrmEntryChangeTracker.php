<?php
if ( ! defined('ABSPATH') ) { exit; }

return ;

/**
 * ---------------------------------------------------------------------
 * Formidable Entry Change Tracker (instance-based, no static)
 * ---------------------------------------------------------------------
 *
 * - On create: all fields with change_type = 'create'
 * - On update: only changed fields with change_type = 'update'
 *
 * Usage:
 *   // in your main plugin file after including this file:
 *   FrmEntryChangeTracker::bootstrap();
 *
 *   // Later in the same request (helper function):
 *   $changes = frm_get_last_entry_changes();
 *
 *   // Or listen to:
 *   add_action( 'frm_entry_field_changes_detected', function ( $entry_id, $form_id, $changes ) {
 *       // your code
 *   }, 10, 3 );
 */
final class FrmEntryChangeTracker {

    /** @var FrmEntryChangeTracker|null */
    private static $instance = null;

    /** @var array<int,array> */
    private $last_changes = [];

    /**
     * Create/init singleton instance without exposing static API for logic.
     */
    public static function bootstrap(): void {
        if ( self::$instance instanceof self ) {
            return;
        }
        self::$instance = new self();
    }

    /**
     * Get instance internally (for helper function).
     *
     * @return FrmEntryChangeTracker|null
     */
    public static function get_instance() {
        return self::$instance;
    }

    /**
     * Constructor: register hooks.
     */
    public function __construct() {
        // All fields on create
        add_action( 'frm_after_create_entry', [ $this, 'handle_after_create' ], 20, 2 );

        // Changed fields on update
        add_action( 'frm_before_update_entry', [ $this, 'handle_before_update' ], 20, 2 );

        // frm_update_entry
        add_action( 'frm_update_entry', [ $this, 'handle_after_update2' ], 20, 2 );

        // frm_entry_saved
        add_action( 'frm_entry_saved', [ $this, 'handle_after_update3' ], 20, 2 );
    }

    public function handle_after_update3( $entry_id, $values): void {
        //echo 'Entry Saved:';
        //die();
    }

    public function handle_after_update2( $entry, $entry_id): void {

        /*
        echo $entry_id;

        echo '<pre>';
        print_r( $entry  );
        echo '</pre>';
        die();
        */
    }

    /**
     * Handle update: compare DB values vs $values['item_meta'] (submitted).
     *
     * @param int   $entry_id
     * @param array $values
    */
    public function handle_before_update( $entry_id, $values ): void {
//exit(111);
        if ( ! class_exists( 'FrmEntryMeta' ) ) {
            return;
        }

        $form_id   = isset( $values['form_id'] ) ? (int) $values['form_id'] : 0;
        $new_metas = isset( $values['item_meta'] ) && is_array( $values['item_meta'] )
            ? $values['item_meta']
            : [];

        // Old values from DB
        $meta_objects = FrmEntryMeta::getAll(
            [ 'item_id' => $entry_id ],
            'field_id'
        );

        $old_metas = [];
        foreach ( $meta_objects as $field_id => $meta_obj ) {
            $old_metas[ (int) $field_id ] = $meta_obj->meta_value ?? null;
        }

        $changes = [];

        foreach ( $new_metas as $field_id => $new_value ) {
            $field_id = (int) $field_id;

            $old_value = $old_metas[ $field_id ] ?? null;

            // Normalise for comparison
            $normalized_old = is_array( $old_value ) ? maybe_serialize( $old_value ) : (string) $old_value;
            $normalized_new = is_array( $new_value ) ? maybe_serialize( $new_value ) : (string) $new_value;

            if ( $normalized_old === $normalized_new ) {
                continue; // not changed
            }

            $field = $this->get_field( $field_id );

            $changes[] = [
                'field_id'    => $field_id,
                'field_key'   => $field['field_key'] ?? null,
                'field_name'  => $field['name'] ?? null,
                'old_value'   => $old_value,
                'new_value'   => $new_value,
                'change_type' => 'update',
            ];
        }

        $this->last_changes = $changes;

        /**
         * Action: changed fields on update
         *
         * @param int   $entry_id
         * @param int   $form_id
         * @param array $changes
         */
        do_action( 'frm_entry_field_changes_detected', (int) $entry_id, (int) $form_id, $changes );
    }

    /**
     * Get last calculated changes (for the current request).
     *
     * @return array<int,array{
     *   field_id:int,
     *   field_key:?string,
     *   field_name:?string,
     *   old_value:mixed,
     *   new_value:mixed,
     *   change_type:string
     * }>
     */
    public function get_last_changes(): array {
        return $this->last_changes;
    }

    /**
     * Handle create: mark all fields as "create".
     *
     * @param int $entry_id
     * @param int $form_id
     */
    public function handle_after_create( $entry_id, $form_id ): void {
        if ( ! class_exists( 'FrmEntryMeta' ) ) {
            return;
        }

        // Get all metas for this entry
        $metas = FrmEntryMeta::getAll(
            [ 'item_id' => $entry_id ],
            'field_id' // index by field_id
        );

        $changes = [];

        foreach ( $metas as $field_id => $meta_obj ) {
            $new_value = $meta_obj->meta_value ?? null;
            $field     = $this->get_field( (int) $field_id );

            $changes[] = [
                'field_id'    => (int) $field_id,
                'field_key'   => $field['field_key'] ?? null,
                'field_name'  => $field['name'] ?? null,
                'old_value'   => null,
                'new_value'   => $new_value,
                'change_type' => 'create',
            ];
        }

        $this->last_changes = $changes;

        /**
         * Action: all fields on create
         *
         * @param int   $entry_id
         * @param int   $form_id
         * @param array $changes
         */
        do_action( 'frm_entry_field_changes_detected', (int) $entry_id, (int) $form_id, $changes );
    }

    /**
     * Helper: get field data (key + name) by field_id.
     *
     * @param int $field_id
     * @return array{field_key:?string,name:?string}
     */
    private function get_field( int $field_id ): array {
        if ( ! class_exists( 'FrmField' ) ) {
            return [
                'field_key' => null,
                'name'      => null,
            ];
        }

        $field = FrmField::getOne( $field_id );
        if ( ! $field ) {
            return [
                'field_key' => null,
                'name'      => null,
            ];
        }

        return [
            'field_key' => $field->field_key ?? null,
            'name'      => $field->name ?? null,
        ];
    }
}

add_action( 'plugins_loaded', function () {

    FrmEntryChangeTracker::bootstrap();

    new FrmEntryChangeTracker();
}, 20 );



add_action( 'frm_entry_field_changes_detected', function ( $entry_id, $form_id, $changes ) {

    echo "<pre>";
    print_r( $changes );
    echo "</pre>";
    die();

}, 10, 3);
