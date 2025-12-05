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
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: .75rem;
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

                .frm-history-toggle {
                    font-size: .75rem;
                    margin-left: .25rem;
                    cursor: pointer;
                    text-decoration: underline;
                    color: #007bff;
                }
                .frm-history-toggle:hover {
                    text-decoration: none;
                }

                /* Type filter tags */
                .frm-history-type-filters {
                    display: flex;
                    gap: .4rem;
                    flex-wrap: wrap;
                    font-size: 0.8rem;
                }
                .frm-history-type-tag {
                    padding: .2rem .6rem;
                    border-radius: 999px;
                    border: 1px solid #ced4da;
                    cursor: pointer;
                    background: #ffffff;
                    color: #495057;
                    user-select: none;
                }
                .frm-history-type-tag:hover {
                    background: #e9ecef;
                }
                .frm-history-type-tag.active {
                    background: #0d6efd;
                    border-color: #0d6efd;
                    color: #fff;
                }
            </style>
        <?php endif; ?>

        <div id="<?php echo esc_attr( $uid ); ?>" class="frm-entry-history-wrapper">
            <div class="frm-entry-history-card card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Entry #<?php echo esc_html( $entry_id ); ?></h5>

                    <div class="frm-history-type-filters">
                        <span class="frm-history-type-tag active" data-type="update">Update</span>
                        <span class="frm-history-type-tag" data-type="create">Create</span>
                    </div>
                </div>

                <div class="card-body">
                    <div class="frm-entry-history-loading">Loading entry history…</div>

                    <div class="frm-entry-history-error frm-entry-history-alert frm-entry-history-alert-error" style="display:none;"></div>
                    <div class="frm-entry-history-empty frm-entry-history-alert frm-entry-history-alert-empty" style="display:none;"></div>

                    <div class="frm-entry-history-table-wrapper" style="display:none;">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Old value</th>
                                    <th></th>
                                    <th>New value</th>
                                    <th>Type</th>
                                    <th>User</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var containerId = <?php echo wp_json_encode( $uid ); ?>;
            var ajaxUrl     = <?php echo wp_json_encode( $ajax_url ); ?>;
            var nonce       = <?php echo wp_json_encode( $nonce ); ?>;
            var entryId     = <?php echo (int) $entry_id; ?>;

            function formatDateUS(dateStr) {
                if (!dateStr) return "";
                var normalized = dateStr.replace(" ", "T");
                var d = new Date(normalized);
                if (isNaN(d.getTime())) return dateStr;

                let month = d.getMonth() + 1;
                let day   = d.getDate();
                let year  = d.getFullYear();
                let hours = d.getHours();
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

                // Delegated click handler: show more/less + multi-select tags
                wrapper.addEventListener('click', function(e) {
                    var target = e.target;

                    // Show more / Show less toggle
                    if (target.classList.contains('frm-history-toggle')) {
                        e.preventDefault();
                        var cell = target.closest('td');
                        if (!cell) return;

                        var shortEl = cell.querySelector('.frm-history-val-short');
                        var fullEl  = cell.querySelector('.frm-history-val-full');

                        if (!shortEl || !fullEl) return;

                        var isExpanded = fullEl.style.display === 'inline';

                        if (isExpanded) {
                            fullEl.style.display  = 'none';
                            shortEl.style.display = 'inline';
                            target.textContent    = 'Show more';
                        } else {
                            fullEl.style.display  = 'inline';
                            shortEl.style.display = 'none';
                            target.textContent    = 'Show less';
                        }
                        return;
                    }

                    // Type filter tags (multi-select)
                    if (target.classList.contains('frm-history-type-tag')) {
                        e.preventDefault();

                        // toggle this tag
                        target.classList.toggle('active');

                        // collect all active types
                        var activeTags = wrapper.querySelectorAll('.frm-history-type-tag.active');
                        var activeTypes = [];
                        activeTags.forEach(function(tag) {
                            var t = tag.getAttribute('data-type');
                            if (t) {
                                activeTypes.push(t);
                            }
                        });

                        applyTypeFilter(wrapper, activeTypes);
                    }
                });

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
                        var fieldName = (item.field && (item.field.label || item.field.name))
                            ? (item.field.label || item.field.name)
                            : (item.field_name || ('#' + fieldId));

                        var fieldDisplay = fieldName + (fieldId ? ' (#' + fieldId + ')' : '');

                        var oldVal = item.old_value || '';
                        var newVal = item.new_value || '';

                        var type = item.update_type || '';
                        tr.setAttribute('data-type', type);

                        var badgeClass =
                            type === 'create' ? 'frm-entry-history-badge-create' :
                            type === 'delete' ? 'frm-entry-history-badge-delete' :
                            'frm-entry-history-badge-update';

                        var userName  = (item.user && (item.user.name || item.user.login)) ? (item.user.name || item.user.login) : '';
                        var userEmail = (item.user && item.user.email) ? item.user.email : '';
                        var userDisplay = userName
                            ? (userEmail ? userName + ' (' + userEmail + ')' : userName)
                            : userEmail;

                        var dateFormatted = formatDateUS(item.change_date);

                        tr.innerHTML =
                            '<td>' + escapeHtml(fieldDisplay) + '</td>' +
                            '<td>' + formatValueCell(oldVal) + '</td>' +
                            '<td class="frm-entry-history-arrow">&rarr;</td>' +
                            '<td>' + formatValueCell(newVal) + '</td>' +
                            '<td><span class="frm-entry-history-badge ' + badgeClass + '">' + escapeHtml(type) + '</span></td>' +
                            '<td>' + escapeHtml(userDisplay) + '</td>' +
                            '<td>' + escapeHtml(dateFormatted) + '</td>';

                        tbody.appendChild(tr);
                    });

                    tableWrap.style.display = 'block';

                    // Default filter: show only "update" (because Update tag starts as active)
                    applyTypeFilter(wrapper, ['update']);
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

                function formatValueCell(value) {
                    if (!value) {
                        return '';
                    }

                    var limit = 50;

                    if (value.length <= limit) {
                        return escapeHtml(value);
                    }

                    var shortText = value.slice(0, limit) + '...';

                    return ''
                        + '<span class="frm-history-val-short">' + escapeHtml(shortText) + '</span>'
                        + '<span class="frm-history-val-full" style="display:none;">' + escapeHtml(value) + '</span>'
                        + ' <a href="#" class="frm-history-toggle">Show more</a>';
                }
            }

            function applyTypeFilter(wrapper, activeTypes) {
                var rows = wrapper.querySelectorAll('tbody tr');

                // If no active tags -> show all
                if (!activeTypes || activeTypes.length === 0) {
                    rows.forEach(function(row) {
                        row.style.display = '';
                    });
                    return;
                }

                rows.forEach(function(row) {
                    var rowType = row.getAttribute('data-type') || '';
                    row.style.display = activeTypes.indexOf(rowType) !== -1 ? '' : 'none';
                });
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

        $entry_id = intval($_POST['entry_id'] ?? 0);
        if (!$entry_id) {
            wp_send_json_error('Missing entry_id.');
        }

        if (!class_exists('FrmHistoryEntryService')) {
            wp_send_json_error('FrmHistoryEntryService not found.');
        }

        try {
            $service = new FrmHistoryEntryService();
            $result  = $service->getEntryHistory($entry_id);

            wp_send_json_success($result['data'] ?? []);
        } catch (Throwable $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
}

add_action('init', function () {
    new FrmHistoryEntryShortcode();
});
