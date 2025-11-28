<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrmHistoryAdminSettings {

    /** @var FrmHistorySettingsHelper */
    private $helper;

    /** @var FrmHistoryReferences */
    private $references;

    public function __construct() {
        $this->helper     = new FrmHistorySettingsHelper();
        $this->references = new FrmHistoryReferences();

        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_frm_history_verify_connection', [ $this, 'ajax_verify_connection' ] );
    }

    public function register_page() {
        add_submenu_page(
            'formidable',
            'Entry History',
            'Entry History',
            'manage_options',
            'frm-history-settings',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings() {
        register_setting(
            'frm_history_settings_group',
            FrmHistorySettingsHelper::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this->helper, 'sanitize_settings' ],
            ]
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'frm-history' ) );
        }

        $settings   = $this->helper->get_settings();
        $ajax_nonce = wp_create_nonce( 'frm_history_verify_connection' );
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

                    <form method="post" action="options.php">
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
                                            name="<?php echo esc_attr( FrmHistorySettingsHelper::OPTION_KEY ); ?>[api_url]"
                                            value="<?php echo esc_attr( $settings['api_url'] ); ?>"
                                            class="regular-text"
                                            placeholder="https://storage-app.example.com/api"
                                        />
                                        <p class="description">
                                            Base API URL of the storage app.
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
                                            name="<?php echo esc_attr( FrmHistorySettingsHelper::OPTION_KEY ); ?>[token]"
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
                                    <th scope="row">Verify connection</th>
                                    <td>
                                        <button
                                            type="button"
                                            class="button button-secondary"
                                            id="frm-history-verify-btn">
                                            Verify connection
                                        </button>
                                        <span id="frm-history-verify-msg" class="fo-msg"></span>
                                        <p class="description">
                                            Sends a request to
                                            <code>{API URL}
                                                <?php
                                                $route = $this->references->getRoute( 'api_account_status' );
                                                echo esc_html( $route ? $route['path'] : '/account/status' );
                                                ?>
                                            </code>.
                                        </p>
                                        <input type="hidden" id="frm-history-verify-nonce"
                                               value="<?php echo esc_attr( $ajax_nonce ); ?>">
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
            function frmHistorySwitchTab(event, tabId) {
                event.preventDefault();
                document.querySelectorAll('.nav-tab').forEach(function(tab){
                    tab.classList.remove('nav-tab-active');
                });
                document.querySelectorAll('.fo-tab-content').forEach(function(content){
                    content.style.display = 'none';
                });
                document.querySelector('[href="#' + tabId + '"]').classList.add('nav-tab-active');
                document.getElementById(tabId).style.display = 'block';
            }

            (function($){
                $(document).on('click', '#frm-history-verify-btn', function(e){
                    e.preventDefault();

                    var apiUrl = $('#frm_history_api_url').val() || '';
                    var token  = $('#frm_history_token').val() || '';
                    var nonce  = $('#frm-history-verify-nonce').val() || '';
                    var $msg   = $('#frm-history-verify-msg');

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
                        nonce: nonce,
                        api_url: apiUrl,
                        token: token
                    }).done(function(response){
                        // Debug: log route info if present
                        if (response && response.data) {
                            console.log(
                                'FrmHistory verify route:',
                                response.data.route_key || '(no key)',
                                response.data.route_url || '(no url)'
                            );
                        }

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
            .fo-success {
                color: #155724;
                background: #d4edda;
                border: 1px solid #c3e6cb;
            }
            .fo-error {
                color: #721c24;
                background: #f8d7da;
                border: 1px solid #f5c6cb;
            }
        </style>
        <?php
    }

    /**
     * AJAX: verify connection using FrmHistoryReferences (api_account_status).
     * Always returns route_key and route_url in the JSON response.
     */
    public function ajax_verify_connection() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                [
                    'message'   => 'Permission denied.',
                    'route_key' => 'api_account_status',
                    'route_url' => '',
                ],
                403
            );
        }

        check_ajax_referer( 'frm_history_verify_connection', 'nonce' );

        $api_url_raw = isset( $_POST['api_url'] ) ? wp_unslash( $_POST['api_url'] ) : '';
        $token_raw   = isset( $_POST['token'] ) ? wp_unslash( $_POST['token'] ) : '';

        $api_url = esc_url_raw( trim( $api_url_raw ) );
        $token   = sanitize_text_field( $token_raw );

        $route_key = 'api_account_status';
        $route     = $this->references->getRoute( $route_key );

        $method = $route && ! empty( $route['method'] ) ? $route['method'] : 'POST';
        $path   = $route && ! empty( $route['path'] )   ? $route['path']   : '/account/status';

        $base      = rtrim( (string) $api_url, '/' );
        $route_url = $base ? $base . $path : '';

        if ( empty( $api_url ) ) {
            wp_send_json_error(
                [
                    'message'   => 'API URL is required.',
                    'route_key' => $route_key,
                    'route_url' => $route_url,
                ],
                400
            );
        }

        $args = [
            'method'  => strtoupper( $method ),
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
            ],
        ];

        if ( ! empty( $token ) ) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        if ( in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
            $args['body'] = wp_json_encode( [ 'ping' => true ] );
        }

        $response = wp_remote_request( $route_url, $args );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error(
                [
                    'message'   => 'Request error: ' . $response->get_error_message(),
                    'route_key' => $route_key,
                    'route_url' => $route_url,
                ],
                500
            );
        }

        $code    = (int) wp_remote_retrieve_response_code( $response );
        $body    = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );

        if ( $code < 200 || $code >= 300 ) {
            wp_send_json_error(
                [
                    'message'   => sprintf(
                        'Remote server returned HTTP %1$d. Body: %2$s',
                        $code,
                        mb_substr( $body, 0, 200 )
                    ),
                    'route_key' => $route_key,
                    'route_url' => $route_url,
                ],
                $code
            );
        }

        $status_message = 'Connection successful.';

        if ( is_array( $decoded ) && isset( $decoded['status'] ) ) {
            $status_message = 'Status: ' . (string) $decoded['status'];
        }

        wp_send_json_success(
            [
                'message'   => $status_message,
                'raw'       => $decoded,
                'route_key' => $route_key,
                'route_url' => $route_url,
            ]
        );
    }
}

// Instantiate the admin settings page
new FrmHistoryAdminSettings();