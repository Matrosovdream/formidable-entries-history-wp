<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrmHistoryFieldsHelper {

    /**
     * Columns to select from frm_fields.
     *
     * @var string[]
     */
    protected $columns = [
        'id',
        'field_key',
        'name',
        'type',
        'field_order',
        'form_id',
    ];

    /**
     * Get all Formidable fields with optional filters.
     *
     * @param array $filter [
     *      'form_ids' => int[]|string[]  // optional, one or many form IDs
     *      'ids'      => int[]|string[]  // optional, one or many field IDs
     * ]
     *
     * @return array List of fields as associative arrays.
     */
    public function getFieldsAll( array $filter = [] ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'frm_fields';

        // Build SELECT column list from $this->columns
        $select_columns = $this->getSelectColumnsSql();

        $where  = [];
        $params = [];

        // Filter by form_ids
        if ( ! empty( $filter['form_ids'] ) ) {
            $form_ids = $this->normalizeToIntArray( $filter['form_ids'] );

            if ( ! empty( $form_ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
                $where[]      = "form_id IN ($placeholders)";
                $params       = array_merge( $params, $form_ids );
            }
        }

        // Filter by field ids
        if ( ! empty( $filter['ids'] ) ) {
            $field_ids = $this->normalizeToIntArray( $filter['ids'] );

            if ( ! empty( $field_ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $field_ids ), '%d' ) );
                $where[]      = "id IN ($placeholders)";
                $params       = array_merge( $params, $field_ids );
            }
        }

        $sql = "SELECT {$select_columns} FROM {$table}";

        if ( ! empty( $where ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        // Order by form + field order for nicer grouping
        $sql .= ' ORDER BY form_id, field_order';

        if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare( $sql, $params );
        }

        // ARRAY_A => each row is an associative array
        return (array) $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Normalize a value to an array of ints (drop invalid values).
     *
     * @param mixed $value
     * @return int[]
     */
    protected function normalizeToIntArray( $value ): array {
        if ( ! is_array( $value ) ) {
            $value = [ $value ];
        }

        $result = [];

        foreach ( $value as $v ) {
            $v = absint( $v );
            if ( $v > 0 ) {
                $result[] = $v;
            }
        }

        return $result;
    }

    /**
     * Build the SELECT column list from $this->columns, safely.
     *
     * @return string
     */
    protected function getSelectColumnsSql(): string {
        $cols = [];

        foreach ( $this->columns as $col ) {
            // sanitize_key makes sure we don't get weird chars in column names
            $col = sanitize_key( $col );
            if ( $col !== '' ) {
                $cols[] = "`{$col}`";
            }
        }

        // Fallback to * if somehow nothing is valid (shouldn't happen)
        if ( empty( $cols ) ) {
            return '*';
        }

        return implode( ', ', $cols );
    }

}
