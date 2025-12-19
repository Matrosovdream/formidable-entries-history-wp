<?php

class FrmEmailLogService {

    /** @var FrmHistoryApi */
    protected $api;

    /** @var FrmHistorySettingsHelper */
    protected $settingsHelper;

    public function __construct(
        FrmHistoryApi $api = null,
        FrmHistorySettingsHelper $settingsHelper = null
    ) {
        $this->api            = $api ?: new FrmHistoryApi();
        $this->settingsHelper = $settingsHelper ?: new FrmHistorySettingsHelper();
    }

    public function getEmailLogsAll( array $filters ): array {
        return $this->api->getEmailLogsAll( $filters );
    }

    public function getEmailLogsByEntry( int $entry_id ): array {
        return $this->api->getEmailLogsAll( [
            'filters' => [
                'entry_id' => $entry_id,
            ],
            'paginate' => 100,
            'sorting' => [
                'date_sent' => 'DESC'
            ]
        ]);
    }   

    public function updateAllEmailLogs( array $payload ): array {
        return $this->api->emailsLogUpdateAll( $payload );
    }

    public function updateLogsByIds( array $log_ids ): array {

        if ( empty( $log_ids ) ) {
            return [
                'success' => false,
                'message' => 'No log_ids provided',
            ];
        }

        global $wpdb;

        $table_name   = $wpdb->prefix . 'frm_emails_log';
        $placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = $wpdb->prepare( "SELECT * FROM $table_name WHERE id IN ($placeholders)", ...$log_ids );
        $logs  = $wpdb->get_results( $query, ARRAY_A );

        $payload = $logs;

        // Exclude fields
        $fieldsExcludes = [ 'id', 'updated' ];
        foreach ( $payload as &$log ) {
            foreach ( $fieldsExcludes as $field ) {
                unset( $log[ $field ] );
            }
        }
        unset( $log );

        $data = [ 'items' => $payload ];

        return $this->api->emailsLogUpdateAll( $data );
    }

    /**
     * Update logs in chunks where updated IS NULL.
     *
     * 1) Take param $chunk_size and get a chunk from wp_frm_emails_log where updated IS NULL
     * 2) Call updateLogsByIds() with these ids
     * 3) Set updated=true for this chunk elements (only if API call succeeded)
     */
    public function updateByChunks( int $chunk_size = 1000 ): array {

        $chunk_size = max( 1, (int) $chunk_size );

        global $wpdb;
        $table = $wpdb->prefix . 'frm_emails_log';

        // Grab IDs that are not processed yet
        // NOTE: adjust ORDER BY if you prefer oldest first (id ASC is typical).
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id
                 FROM {$table}
                 WHERE updated IS NULL
                 ORDER BY id ASC
                 LIMIT %d",
                $chunk_size
            )
        );

        if ( empty( $ids ) ) {
            return [
                'success' => true,
                'message' => 'No rows with updated IS NULL',
                'count'   => 0,
                'ids'     => [],
            ];
        }

        // Push chunk to API
        $res = $this->updateLogsByIds( array_map( 'intval', $ids ) );

        // Decide "success" — support multiple possible response shapes
        $ok = false;
        if ( is_array( $res ) ) {
            if ( isset( $res['success'] ) ) {
                $ok = (bool) $res['success'];
            } elseif ( isset( $res['status'] ) ) {
                $ok = in_array( (string) $res['status'], [ 'ok', 'success', '200' ], true );
            } else {
                // If API returns something else, treat non-empty array as success (best-effort)
                $ok = ! empty( $res );
            }
        }

        if ( ! $ok ) {
            return [
                'success' => false,
                'message' => 'API update failed; updated flag not changed',
                'count'   => count( $ids ),
                'ids'     => $ids,
                'api'     => $res,
            ];
        }

        // Mark as updated = 1 (true). This assumes `updated` column exists.
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET updated = 1
             WHERE id IN ({$placeholders})",
            ...array_map( 'intval', $ids )
        );

        $affected = $wpdb->query( $sql );

        return [
            'success'  => true,
            'message'  => 'Chunk processed',
            'count'    => count( $ids ),
            'affected' => (int) $affected,
            'ids'      => $ids,
            'api'      => $res,
        ];
    }
}
