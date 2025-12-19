<?php

return ;

/**
 * Cron partition dispatcher for wp_frm_emails_log
 */
final class FrmHistoryEmailLogHelper {

    // Main dispatcher cron hook (runs every 3 minutes)
    private const DISPATCH_HOOK = 'frm_history_emails_log_dispatch';

    // Chunk job hook (single events)
    private const CHUNK_HOOK    = 'frm_history_emails_log_chunk';

    // Option key for cursor/state
    private const OPT_KEY       = 'frm_history_emails_log_cursor_state';

    // Lock key to avoid overlapping dispatcher runs
    private const LOCK_KEY      = 'frm_history_emails_log_dispatch_lock';

    public static function init(): void {
        // Add 3-minute schedule
        add_filter('cron_schedules', [__CLASS__, 'add_cron_schedule']);

        // Dispatcher
        add_action(self::DISPATCH_HOOK, [__CLASS__, 'dispatch'], 10, 2);

        // Chunk handler (intentionally empty)
        add_action(self::CHUNK_HOOK, [__CLASS__, 'handle_chunk'], 10, 1);

        // Auto-schedule if not scheduled
        add_action('init', [__CLASS__, 'ensure_scheduled']);
    }

    public static function add_cron_schedule(array $schedules): array {
        if (!isset($schedules['every_3_minutes'])) {
            $schedules['every_3_minutes'] = [
                'interval' => 3 * MINUTE_IN_SECONDS,
                'display'  => __('Every 3 minutes', 'frm-history'),
            ];
        }
        return $schedules;
    }

    public static function ensure_scheduled(): void {
        if (!wp_next_scheduled(self::DISPATCH_HOOK)) {
            // Default args: batchSize=1000, chunkSize=100
            wp_schedule_event(time() + 30, 'every_3_minutes', self::DISPATCH_HOOK, [1000, 100]);
        }
    }

    /**
     * Dispatcher:
     * - gets oldest rows after cursor
     * - schedules single events in chunks
     * - updates options with cursor + resume point
     */
    public static function dispatch(int $batchSize = 1000, int $chunkSize = 100): void {
        global $wpdb;

        $batchSize = max(1, (int) $batchSize);
        $chunkSize = max(1, (int) $chunkSize);

        // Prevent overlaps (WP-Cron can overlap under load)
        if (get_transient(self::LOCK_KEY)) {
            return;
        }
        set_transient(self::LOCK_KEY, 1, 2 * MINUTE_IN_SECONDS);

        $table = $wpdb->prefix . 'frm_emails_log';

        // State:
        // cursor_id         = last fully completed id
        // batch_end_id      = max id in current batch (planned range)
        // scheduled_until_id= last id already chunk-scheduled within that batch
        $state = get_option(self::OPT_KEY, []);
        $cursor_id          = isset($state['cursor_id']) ? (int) $state['cursor_id'] : 0;
        $batch_end_id       = isset($state['batch_end_id']) ? (int) $state['batch_end_id'] : 0;
        $scheduled_until_id = isset($state['scheduled_until_id']) ? (int) $state['scheduled_until_id'] : $cursor_id;

        // Start a new batch if none active or finished
        if ($batch_end_id <= 0 || $scheduled_until_id >= $batch_end_id) {
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$table}
                     WHERE id > %d
                     ORDER BY id ASC
                     LIMIT %d",
                    $cursor_id,
                    $batchSize
                )
            );

            if (empty($ids)) {
                // Nothing left
                delete_transient(self::LOCK_KEY);
                return;
            }

            $batch_end_id       = (int) max($ids);
            $scheduled_until_id = $cursor_id;

            $state = [
                'cursor_id'          => $cursor_id,
                'batch_end_id'       => $batch_end_id,
                'scheduled_until_id' => $scheduled_until_id,
                'updated_at'         => current_time('mysql'),
            ];
            update_option(self::OPT_KEY, $state, false);
        }

        // Now schedule all chunks for the active batch in this cron hit
        while (true) {
            $chunk_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$table}
                     WHERE id > %d AND id <= %d
                     ORDER BY id ASC
                     LIMIT %d",
                    $scheduled_until_id,
                    $batch_end_id,
                    $chunkSize
                )
            );

            if (empty($chunk_ids)) {
                // Batch complete: advance cursor and clear batch markers
                $cursor_id = $batch_end_id;

                $state = [
                    'cursor_id'          => $cursor_id,
                    'batch_end_id'       => 0,
                    'scheduled_until_id' => $cursor_id,
                    'updated_at'         => current_time('mysql'),
                ];
                update_option(self::OPT_KEY, $state, false);
                break;
            }

            // Schedule single event (empty handler by default)
            wp_schedule_single_event(time() + 500, self::CHUNK_HOOK, [$chunk_ids]);

            // Update resume point (start point for next partition)
            $scheduled_until_id = (int) max($chunk_ids);

            $state = [
                'cursor_id'          => $cursor_id,
                'batch_end_id'       => $batch_end_id,
                'scheduled_until_id' => $scheduled_until_id,
                'updated_at'         => current_time('mysql'),
            ];
            update_option(self::OPT_KEY, $state, false);

            // Continue until we schedule all chunks in this batch
            if ($scheduled_until_id >= $batch_end_id) {
                // loop will mark batch complete next iteration (chunk_ids empty) OR we can finalize here
                continue;
            }
        }

        delete_transient(self::LOCK_KEY);
    }

    /**
     * Chunk handler — intentionally empty.
     * Attach your real processing via the action below.
     */
    public static function handle_chunk(array $ids): void {
        // Leave empty, but provide an extension point:
        do_action('frm_history_emails_log_process_ids', $ids);
    }

    /**
     * Optional helper to reset cursor.
     */
    public static function reset_cursor(int $cursor_id = 0): void {
        update_option(self::OPT_KEY, [
            'cursor_id'          => max(0, (int) $cursor_id),
            'batch_end_id'       => 0,
            'scheduled_until_id' => max(0, (int) $cursor_id),
            'updated_at'         => current_time('mysql'),
        ], false);
    }
}

// Boot it
FrmHistoryEmailLogHelper::init();
