<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Entry History admin page
 * Subpage of: /wp-admin/admin.php?page=formidable-dashboard
 */
class FrmHistoryAdminSettings {

    const OPTION_KEY = 'frm_history_storage_app';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_frm_history_verify_connection', array( $this, 'ajax_verify_connection' ) );
    }

    /**
     * Add submenu under Formidable Dashboard.
     */
    public function register_page() {
        add_submenu_page(
            'formidable',                           // parent slug
            'Entry History',                        // page title
            'Entry History',                        // menu title
            'manage_options',                       // capability
            'frm-history-settings',                 // menu slug
            array( $this, 'render_page' )           // callback
        );
    }

    /**
     * Register settings for Storage app.
     */
    public function register_settings() {
        register_setting(
            'frm_history_settings_group',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => array(
                    'api_url' => '',
                    'token'   => '',
                ),
            )
        );
    }

    /**
     * Sanitize settings.
     */
    public function sanitize_settings( $input ) {
        $output = array(
            'api_url' => '',
            'token'   => '',
        );

        if ( is_array( $input ) ) {
            $output['api_url'] = isset( $input['api_url'] ) ? esc_url_raw( trim( $input['api_url'] ) ) : '';
            $output['token']   = isset( $input['token'] ) ? sanitize_text_field( $input['token'] ) : '';
        }

        return $output;
    }

    /**
     * Helper: get settings with defaults.
     */
    protected function get_settings() {
        $defaults = array(
            'api_url' => '',
            'token'   => '',
        );

        $saved = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Render admin page (using "fo-" blocks & styles like Frm_optimizer_admin).
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'frm-history' ) );
        }

        $settings = $this->get_settings();
        $nonce    = wp_create_nonce( 'frm_history_verify_connection' );
        ?>
        <div class="wrap">
            <h1>Entry History</h1>

            <h2 class="nav-tab-wrapper">
                <a href="#storage-app"
                   class="nav-tab nav-tab-active"
                   onclick="frmHistorySwitchTab(event, 'storage-app')">
                    Storage app
                </a>
            </h2>

            <div id="storage-app" class="fo-tab-content" style="">
                <div class="fo-section" style="max-width: 800px;">
                    <h2>Storage app</h2>

                    <form method="post" action="options.php" id="frm-history-settings-form">
                        <?php settings_fields( 'frm_history_settings_group' ); ?>

                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="frm_history_api_url">API URL</label>
                                    </th>
                                    <td>
                                        <input
                                            type="text"
                                            id="frm_history_api_url"
                                            name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_url]"
                                            value="<?php echo esc_attr( $settings['api_url'] ); ?>"
                                            class="regular-text"
                                            placeholder="https://storage-app.example.com/api"
                                        />
                                        <p class="description">
                                            Base API URL of the storage app (without trailing <code>/account/status</code>).
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="frm_history_token">Token</label>
                                    </th>
                                    <td>
                                        <input
                                            type="text"
                                            id="frm_history_token"
                                            name="<?php echo esc_attr( self::OPTION_KEY ); ?>[token]"
                                            value="<?php echo esc_attr( $settings['token'] ); ?>"
                                            class="regular-text"
                                            autocomplete="off"
                                        />
                                        <p class="description">
                                            API token for authenticating with the storage app.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        Verify connection
                                    </th>
                                    <td>
                                        <button type="button"
                                                class="button button-secondary"
                                                id="frm-history-verify-btn">
                                            Verify connection
                                        </button>
                                        <span id="frm-history-verify-msg" class="fo-msg"></span>
                                        <p class="description">
                                            Sends a request to <code>{API URL}/account/status</code> using the token.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <?php submit_button( 'Save Settings' ); ?>
                    </form>
                </div>
            </div>
        </div>

        <script>
            // Tab switcher (same pattern as frmOptSwitchTab, even if we have single tab for now)
            function frmHistorySwitchTab(event, tabId) {
                event.preventDefault();
                document.querySelectorAll('.nav-tab').forEach(function(tab) {
                    tab.classList.remove('nav-tab-active');
                });
                document.querySelectorAll('.fo-tab-content').forEach(function(content) {
                    content.style.display = 'none';
                });

                document.querySelector('[href="#' + tabId + '"]').classList.add('nav-tab-active');
                document.getElementById(tabId).style.display = 'block';
            }

            (function($){
                $(document).on('click', '#frm-history-verify-btn', function(e){
                    e.preventDefault();

                    var apiUrl   = $('#frm_history_api_url').val() || '';
                    var token    = $('#frm_history_token').val() || '';
                    var $msg     = $('#frm-history-verify-msg');

                    $msg
                        .removeClass('fo-success fo-error')
                        .text('Checking...');

                    if (!apiUrl) {
                        $msg
                            .addClass('fo-error')
                            .text('Please enter API URL first.');
                        return;
                    }

                    $.post(ajaxurl, {
                        action: 'frm_history_verify_connection',
                        nonce: '<?php echo esc_js( $nonce ); ?>',
                        api_url: apiUrl,
                        token: token
                    }).done(function(response){
                        if (response && response.success) {
                            var text = response.data && response.data.message
                                ? response.data.message
                                : 'Connection successful.';
                            $msg
                                .removeClass('fo-error')
                                .addClass('fo-success')
                                .text(text);
                        } else {
                            var err = (response && response.data && response.data.message)
                                ? response.data.message
                                : 'Connection failed.';
                            $msg
                                .removeClass('fo-success')
                                .addClass('fo-error')
                                .text(err);
                        }
                    }).fail(function(){
                        $msg
                            .removeClass('fo-success')
                            .addClass('fo-error')
                            .text('AJAX request failed.');
                    });
                });
            })(jQuery);
        </script>

        <style>
            /* Simple versions of the fo-* styles, matching your Optimizer layout */
            .fo-section {
                background: #fff;
                border: 1px solid #e5e5e5;
                padding: 20px;
                margin-top: 20px;
            }
            .fo-msg {
                display: inline-block;
                margin-left: 10px;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
            }
            .fo-msg.fo-success {
                color: #155724;
                background: #d4edda;
                border: 1px solid #c3e6cb;
            }
            .fo-msg.fo-error {
                color: #721c24;
                background: #f8d7da;
                border: 1px solid #f5c6cb;
            }
        </style>
        <?php
    }

    /**
     * AJAX handler: verify connection to {api_url}/account/status
     */
    public function ajax_verify_connection() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array( 'message' => 'Permission denied.' ),
                403
            );
        }

        check_ajax_referer( 'frm_history_verify_connection', 'nonce' );

        $api_url = isset( $_POST['api_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['api_url'] ) ) ) : '';
        $token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

        if ( empty( $api_url ) ) {
            wp_send_json_error(
                array( 'message' => 'API URL is required.' ),
                400
            );
        }

        $endpoint = trailingslashit( $api_url ) . 'account/status';

        $args = array(
            'timeout' => 10,
            'headers' => array(),
        );

        if ( ! empty( $token ) ) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                array(
                    'message' => 'Request error: ' . $response->get_error_message(),
                ),
                500
            );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            wp_send_json_error(
                array(
                    'message' => sprintf(
                        'Remote server returned HTTP %1$d. Body: %2s',
                        $code,
                        mb_substr( $body, 0, 200 )
                    ),
                ),
                $code
            );
        }

        $decoded = json_decode( $body, true );
        $status_message = '';

        if ( is_array( $decoded ) && isset( $decoded['status'] ) ) {
            $status_message = 'Status: ' . (string) $decoded['status'];
        }

        if ( ! $status_message ) {
            $status_message = 'Connection successful.';
        }

        wp_send_json_success(
            array(
                'message' => $status_message,
                'raw'     => $decoded,
            )
        );
    }
}

// Bootstrap
new FrmHistoryAdminSettings();
