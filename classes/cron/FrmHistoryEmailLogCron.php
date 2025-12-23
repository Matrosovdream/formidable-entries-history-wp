<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmHistoryEmailLogCron {

    /** Recurring “dispatcher” cron (every 3 minutes) */
    const CRON_HOOK_DISPATCH = 'frm_history_email_log_dispatch';

    /** Single events that actually process chunks */
    const CRON_HOOK_PROCESS  = 'frm_history_email_log_process_chunk';

    /** Default chunk size */
    const DEFAULT_CHUNK_SIZE = 200;

    /** Default how many single events to create per dispatch run */
    const DEFAULT_COUNT      = 5;

    public static function init(): void {

        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_schedules' ] );

        // IMPORTANT: accept dispatcher args ($count)
        add_action( self::CRON_HOOK_DISPATCH, [ __CLASS__, 'dispatch' ], 10, 1 );

        // IMPORTANT: accept worker args (array)
        add_action( self::CRON_HOOK_PROCESS, [ __CLASS__, 'process_chunk' ], 10, 1 );

        self::ensure_scheduled();
    }

    public static function add_cron_schedules( array $schedules ): array {
        $schedules['every_3_minutes'] = [
            'interval' => 3 * 60,
            'display'  => __( 'Every 3 Minutes', 'frm-history' ),
        ];
        return $schedules;
    }

    public static function ensure_scheduled(): void {

        // Make sure schedule exists before scheduling (init hook handles this normally)
        if ( ! wp_next_scheduled( self::CRON_HOOK_DISPATCH, [ self::DEFAULT_COUNT ] ) ) {
            wp_schedule_event(
                time() + 10,
                'every_3_minutes',
                self::CRON_HOOK_DISPATCH,
                [ self::DEFAULT_COUNT ]
            );
        }
    }

    /**
     * Dispatcher: runs every 3 minutes and creates $count single events.
     * Delay between events: 2 seconds.
     *
     * @param mixed $count
     */
    public static function dispatch( $count = self::DEFAULT_COUNT ): void {

        $count = max( 1, (int) $count );
        $count = min( $count, 200 ); // safety cap

        $start = time(); // base time for first event

        for ( $i = 0; $i < $count; $i++ ) {

            // 2-second spacing
            $run_at = $start + ( $i * 2 );

            // Use UNIQUE args per event so WP-Cron doesn't treat them as duplicates
            $args = [
                'chunk_size' => self::DEFAULT_CHUNK_SIZE,
                'batch'      => $start, // same for this dispatch run
                'n'          => $i,      // unique per scheduled event
            ];

            // If already scheduled (shouldn't happen due to unique args), skip
            if ( wp_next_scheduled( self::CRON_HOOK_PROCESS, [ $args ] ) ) {
                continue;
            }

            wp_schedule_single_event( $run_at, self::CRON_HOOK_PROCESS, [ $args ] );
        }
    }

    /**
     * Worker: processes one chunk.
     *
     * @param array $args
     */
    public static function process_chunk( $args = [] ): void {

        $chunk_size = self::DEFAULT_CHUNK_SIZE;

        if ( is_array( $args ) && isset( $args['chunk_size'] ) ) {
            $chunk_size = max( 1, (int) $args['chunk_size'] );
        }

        $service = new FrmEmailLogService();
        $result  = $service->updateByChunks( $chunk_size );

        // Optional debug:
        // error_log('process_chunk args=' . wp_json_encode($args) . ' result=' . wp_json_encode($result));
    }

    // Optional helpers for activate/deactivate (recommended)
    public static function activate(): void {
        self::ensure_scheduled();
    }

    public static function deactivate(): void {
        // Unschedule dispatcher with its args
        $ts = wp_next_scheduled( self::CRON_HOOK_DISPATCH, [ self::DEFAULT_COUNT ] );
        while ( $ts ) {
            wp_unschedule_event( $ts, self::CRON_HOOK_DISPATCH, [ self::DEFAULT_COUNT ] );
            $ts = wp_next_scheduled( self::CRON_HOOK_DISPATCH, [ self::DEFAULT_COUNT ] );
        }

        // Clear all workers
        wp_clear_scheduled_hook( self::CRON_HOOK_PROCESS );
    }
}

add_action( 'init', [ 'FrmHistoryEmailLogCron', 'init' ] );