<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrmHistoryEntryShortcode {

    public function __construct() {
        add_shortcode( 'frm-entry-history', [ $this, 'render_shortcode' ] );

        add_action( 'wp_ajax_frm_get_entry_history', [ $this, 'ajax_get_entry_history' ] );
        add_action( 'wp_ajax_nopriv_frm_get_entry_history', [ $this, 'ajax_get_entry_history' ] );
    }

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts(
            [
                'entry' => 0,
            ],
            $atts,
            'frm-entry-history'
        );

        $entry_id = absint( $atts['entry'] );
        if ( ! $entry_id ) {
            return '<div class="alert alert-danger">Entry ID is required for [frm-entry-history].</div>';
        }

        $ajax_url = admin_url( 'admin-ajax.php' );
        $nonce    = wp_create_nonce( 'frm_entry_history_nonce' );
        $uid      = 'frm-entry-history-' . uniqid();

        ob_start();

        static $printed_css = false;
        if ( ! $printed_css ) :
            $printed_css = true;
            ?>
            <style>
                .frm-entry-history-wrapper { margin: 1.5rem 0; }

                .frm-entry-history-card {
                    border-radius: .5rem;
                    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075);
                    border: 1px solid #e5e5e5;
                    background: #fff;
                }

                .frm-entry-history-card .card-header {
                    padding: .75rem 1rem;
                    border-bottom: 1px solid #e5e5e5;
                    background: #f8f9fa;
                }

                .frm-entry-history-card .card-body { padding: 1rem; }

                .frm-entry-history-loading { font-style: italic; }

                .frm-entry-history-table-wrapper {
                    margin-top: .5rem;
                    overflow-x: auto;
                }

                .frm-entry-history-table-wrapper table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 0.875rem;
                }

                .frm-entry-history-table-wrapper th,
                .frm-entry-history-table-wrapper td {
                    padding: .5rem .75rem;
                    border-top: 1px solid #dee2e6;
                    vertical-align: top;
                    text-align: left !important;
                }

                .frm-entry-history-table-wrapper thead th {
                    border-bottom: 2px solid #dee2e6;
                    background: #f8f9fa;
                }

                /* Arrow column styling */
                .frm-entry-history-arrow {
                    text-align: center !important;
                    width: 40px;
                    white-space: nowrap;
                    color: #6c757d;
                    font-weight: 600;
                }

                .frm-entry-history-badge {
                    display: inline-block;
                    padding: .15rem .4rem;
                    border-radius: .25rem;
                    font-size: .75rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    text-align: left;
                }

                .frm-entry-history-badge-create { background: #d4edda; color: #155724; }
                .frm-entry-history-badge-update { background: #cce5ff; color: #004085; }
                .frm-entry-history-badge-delete { background: #f8d7da; color: #721c24; }

                .frm-entry-history-alert {
                    padding: .5rem .75rem;
                    border-radius: .25rem;
                    border: 1px solid transparent;
                    margin-top: .5rem;
                    font-size: .875rem;
                }

                .frm-entry-history-alert-error {
                    border-color: #f5c6cb;
                    background: #f8d7da;
                    color: #721c24;
                }
                .frm-entry-history-alert-empty {
                    border-color: #ffeeba;
                    background: #fff3cd;
                    color: #856404;
                }
            </style>
        <?php endif; ?>

        <div id="<?php echo esc_attr( $uid ); ?>" class="frm-entry-history-wrapper">
            <div class="frm-entry-history-card card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Entry #<?php echo esc_html( $entry_id ); ?></h5>
                </div>

                <div class="card-body">
                    <div class="frm-entry-history-loading">Loading entry history…</div>

                    <div class="frm-entry-history-error frm-entry-history-alert frm-entry-history-alert-error" style="display:none;"></div>
                    <div class="frm-entry-history-empty frm-entry-history-alert frm-entry-history-alert-empty" style="display:none;"></div>

                    <div class="frm-entry-history-table-wrapper" style="display:none;">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Field</th>
                                    <th>Old value</th>
                                    <th></th> <!-- arrow -->
                                    <th>New value</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================
             JavaScript (AJAX + Formatting)
        =============================== -->
        <script>
        (function() {
            var containerId = <?php echo wp_json_encode( $uid ); ?>;
            var ajaxUrl     = <?php echo wp_json_encode( $ajax_url ); ?>;
            var nonce       = <?php echo wp_json_encode( $nonce ); ?>;
            var entryId     = <?php echo (int) $entry_id; ?>;

            // FORMAT DATE → "12/3/2025 5:00 AM"
            function formatDateUS(dateStr) {
                if (!dateStr) return "";

                // Support "YYYY-MM-DD HH:MM:SS" → "YYYY-MM-DDTHH:MM:SS"
                var normalized = dateStr.replace(" ", "T");
                var d = new Date(normalized);

                if (isNaN(d.getTime())) return dateStr;

                let month = d.getMonth() + 1;
                let day   = d.getDate();
                let year  = d.getFullYear();

                let hours   = d.getHours();
                let minutes = d.getMinutes().toString().padStart(2, "0");

                let ampm = hours >= 12 ? "PM" : "AM";
                hours = hours % 12;
                if (hours === 0) hours = 12;

                return `${month}/${day}/${year} ${hours}:${minutes} ${ampm}`;
            }

            function runFrmEntryHistory() {
                var wrapper   = document.getElementById(containerId);

                var loadingEl = wrapper.querySelector('.frm-entry-history-loading');
                var errorEl   = wrapper.querySelector('.frm-entry-history-error');
                var emptyEl   = wrapper.querySelector('.frm-entry-history-empty');
                var tableWrap = wrapper.querySelector('.frm-entry-history-table-wrapper');
                var tbody     = wrapper.querySelector('tbody');

                loadingEl.style.display = 'block';
                errorEl.style.display   = 'none';
                emptyEl.style.display   = 'none';
                tableWrap.style.display = 'none';
                tbody.innerHTML = '';

                var formData = new FormData();
                formData.append('action', 'frm_get_entry_history');
                formData.append('nonce', nonce);
                formData.append('entry_id', entryId);

                fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                })
                .then(resp => resp.json())
                .then(json => {
                    loadingEl.style.display = 'none';

                    if (!json || !json.success) {
                        errorEl.textContent = (json && json.data) || 'Failed to load entry history.';
                        errorEl.style.display = 'block';
                        return;
                    }

                    var data  = json.data || {};
                    var items = data.items || [];

                    if (!items.length) {
                        emptyEl.textContent = 'No changes logged for this entry yet.';
                        emptyEl.style.display = 'block';
                        return;
                    }

                    items.forEach(function(item, index) {
                        var tr = document.createElement('tr');

                        var fieldId = item.field_id || '';
                        var fieldName = '';

                        // Prefer nested `field.label` / `field.name`
                        if (item.field && (item.field.label || item.field.name)) {
                            fieldName = item.field.label || item.field.name;
                        } else if (item.field_name) {
                            fieldName = item.field_name;
                        } else {
                            fieldName = '#' + fieldId;
                        }

                        var fieldDisplay = fieldName + (fieldId ? ' (#' + fieldId + ')' : '');

                        // New structure: old_value, new_value
                        var oldVal = item.old_value || '';
                        var newVal = item.new_value || '';

                        // Type badge
                        var type = item.update_type || '';
                        var badgeClass =
                            type === 'create' ? 'frm-entry-history-badge-create' :
                            type === 'delete' ? 'frm-entry-history-badge-delete' :
                            'frm-entry-history-badge-update';

                        var dateFormatted = formatDateUS(item.change_date);

                        tr.innerHTML =
                            '<td>' + (index + 1) + '</td>' +
                            '<td>' + escapeHtml(fieldDisplay) + '</td>' +
                            '<td>' + escapeHtml(oldVal) + '</td>' +
                            '<td class="frm-entry-history-arrow">&rarr;</td>' +
                            '<td>' + escapeHtml(newVal) + '</td>' +
                            '<td><span class="frm-entry-history-badge ' + badgeClass + '">' + escapeHtml(type) + '</span></td>' +
                            '<td>' + escapeHtml(dateFormatted) + '</td>';

                        tbody.appendChild(tr);
                    });

                    tableWrap.style.display = 'block';
                })
                .catch(() => {
                    loadingEl.style.display = 'none';
                    errorEl.textContent = 'Error loading entry history.';
                    errorEl.style.display = 'block';
                });

                function escapeHtml(str) {
                    return String(str || '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }
            }

            if (document.readyState !== 'loading') {
                runFrmEntryHistory();
            } else {
                document.addEventListener('DOMContentLoaded', runFrmEntryHistory);
            }
        })();
        </script>

        <?php
        return ob_get_clean();
    }

    public function ajax_get_entry_history() {
        check_ajax_referer( 'frm_entry_history_nonce', 'nonce' );

        $entry_id = intval( $_POST['entry_id'] ?? 0 );
        if ( ! $entry_id ) {
            wp_send_json_error( 'Missing entry_id.' );
        }

        if ( ! class_exists( 'FrmHistoryEntryService' ) ) {
            wp_send_json_error( 'FrmHistoryEntryService not found.' );
        }

        try {
            $service = new FrmHistoryEntryService();
            $result  = $service->getEntryHistory( $entry_id );

            // Expecting: [ 'data' => [ 'items' => [ [ old_value, new_value, ... ], ... ] ] ]
            wp_send_json_success( $result['data'] ?? [] );
        } catch ( Throwable $e ) {
            wp_send_json_error( 'Error: ' . $e->getMessage() );
        }
    }
}

add_action( 'init', function () {
    new FrmHistoryEntryShortcode();
} );
