<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrmHistoryEntrySearchShortcode {

    public function __construct() {
        add_shortcode( 'frm_history_entry_search', [ $this, 'render_shortcode' ] );
    }

    /**
     * Shortcode: [frm_history_entry_search exclude_fields="1"]
     */
    public function render_shortcode( $atts ) {

        $atts = shortcode_atts(
            [
                'exclude_fields' => 0,
            ],
            $atts,
            'frm_history_entry_search'
        );

        // Detect searched entry ID
        $current_id = isset($_GET['frm_history_entry_id'])
            ? (int) $_GET['frm_history_entry_id']
            : 0;

        ob_start();
        ?>

        <div class="frm-history-search-wrapper">

            <form method="get" class="frm-history-search-form">

                <?php
                // Preserve all unrelated GET params
                if ( ! empty($_GET) ) {
                    foreach ( $_GET as $key => $value ) {
                        if ( $key === 'frm_history_entry_id' ) {
                            continue;
                        }
                        if ( is_array($value) ) {
                            foreach ( $value as $k => $v ) {
                                echo '<input type="hidden" name="'.esc_attr($key).'['.esc_attr($k).']" value="'.esc_attr($v).'">';
                            }
                        } else {
                            echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">';
                        }
                    }
                }
                ?>

                <label class="frm-history-search-label">
                    <span>Find by Entry ID</span>
                </label>

                <input
                    type="number"
                    name="frm_history_entry_id"
                    value="<?php echo $current_id ?: ''; ?>"
                    class="frm-history-search-input"
                    placeholder="Entry ID"
                    min="1"
                />

                <button type="submit" class="frm-history-search-btn">Search</button>
            </form>

        </div>

        <?php if ( $current_id > 0 ) : ?>
            <div class="frm-history-search-results">
                <?php
                echo do_shortcode(
                    sprintf(
                        '[frm-entry-history entry="%d"]',
                        $current_id
                    )
                );
                ?>
            </div>
        <?php endif; ?>

        <style>
            /* Container */
            .frm-history-search-wrapper {
                margin-bottom: 18px;
            }

            /* Form layout */
            .frm-history-search-form {
                display: flex;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
                background: #f7f7f7;
                border: 1px solid #ddd;
                padding: 12px 14px;
                border-radius: 4px;
            }

            .frm-history-search-label {
                font-weight: 600;
                font-size: 14px;
            }

            /* Input styling matching WP admin + table styling */
            .frm-history-search-input {
                padding: 6px 8px;
                border: 1px solid #ccc;
                border-radius: 3px;
                width: 150px;
                font-size: 14px;
            }

            .frm-history-search-input:focus {
                border-color: #007cba;
                box-shadow: 0 0 0 1px #007cba;
                outline: none;
            }

            /* Button styling matching previous shortcode hover colors */
            .frm-history-search-btn {
                background: #0073aa;
                border: 1px solid #006799;
                color: #fff;
                padding: 6px 14px;
                font-size: 14px;
                border-radius: 3px;
                cursor: pointer;
            }

            .frm-history-search-btn:hover {
                background: #006799;
                border-color: #005a87;
            }

            .frm-history-search-results {
                margin-top: 20px;
            }
        </style>

        <?php
        return ob_get_clean();
    }
}

new FrmHistoryEntrySearchShortcode();
