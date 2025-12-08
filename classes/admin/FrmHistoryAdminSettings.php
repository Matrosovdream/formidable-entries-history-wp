<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrmHistoryAdminSettings {

    /** @var FrmHistorySettingsHelper */
    private $helper;

    /** @var FrmHistoryReferences */
    private $references;

    /** @var FrmHistoryFieldsHelper */
    private $fieldsHelper;

    public function __construct() {
        $this->helper       = new FrmHistorySettingsHelper();
        $this->references   = new FrmHistoryReferences();
        $this->fieldsHelper = new FrmHistoryFieldsHelper();

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

        $api_url   = $settings['api_url'] ?? '';
        $token     = $settings['token'] ?? '';
        $activeTab = $settings['active_tab'] ?? 'api-connection';

        $tab_api_active   = ( 'api-connection' === $activeTab );
        $tab_field_active = ( 'field-settings' === $activeTab );

        $forms  = $this->fieldsHelper->getFormsList();
        $fields = $this->fieldsHelper->getFieldsAll( [], 'form_id' );

        $excluded_fields = isset( $settings['excluded_fields'] ) && is_array( $settings['excluded_fields'] )
            ? $settings['excluded_fields']
            : [];
        ?>
        <div class="wrap">
            <h1>Entry History</h1>

            <h2 class="nav-tab-wrapper">
                <a href="#api-connection"
                   class="nav-tab <?php echo $tab_api_active ? 'nav-tab-active' : ''; ?>"
                   onclick="frmHistorySwitchTab(event, 'api-connection')">
                    API connection
                </a>
                <a href="#field-settings"
                   class="nav-tab <?php echo $tab_field_active ? 'nav-tab-active' : ''; ?>"
                   onclick="frmHistorySwitchTab(event, 'field-settings')">
                    Field settings
                </a>
            </h2>

            <form method="post" action="options.php">
                <?php settings_fields( 'frm_history_settings_group' ); ?>

                <input type="hidden"
                       id="frm_history_active_tab"
                       name="<?php echo esc_attr( FrmHistorySettingsHelper::OPTION_KEY ); ?>[active_tab]"
                       value="<?php echo esc_attr( $activeTab ); ?>" />

                <!-- TAB: API connection -->
                <div id="api-connection"
                     class="fo-tab-content"
                     style="<?php echo $tab_api_active ? '' : 'display:none;'; ?>">
                    <div class="fo-section" style="max-width: 800px;">
                        <h2>API connection</h2>

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
                                            value="<?php echo esc_attr( $api_url ); ?>"
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
                                            value="<?php echo esc_attr( $token ); ?>"
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
                                        <input type="hidden" id="frm-history-verify-nonce"
                                               value="<?php echo esc_attr( $ajax_nonce ); ?>">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB: Field settings -->
                <div id="field-settings"
                     class="fo-tab-content"
                     style="<?php echo $tab_field_active ? '' : 'display:none;'; ?>">
                    <div class="fo-section" style="max-width: 900px;">
                        <h2>Exclude fields</h2>

                        <p class="description">
                            Select fields that should be excluded from Entry History tracking.
                        </p>

                        <?php if ( ! empty( $forms ) ) : ?>
                            <?php foreach ( $forms as $form ) :
                                $form_id   = isset( $form['id'] ) ? (int) $form['id'] : 0;
                                $form_name = isset( $form['name'] ) ? $form['name'] : '';
                                if ( ! $form_id ) {
                                    continue;
                                }

                                $form_label   = sprintf( '%s (%d)', $form_name, $form_id );
                                $form_fields  = isset( $fields[ $form_id ] ) ? $fields[ $form_id ] : [];
                                $excluded_for = isset( $excluded_fields[ $form_id ] ) && is_array( $excluded_fields[ $form_id ] )
                                    ? $excluded_fields[ $form_id ]
                                    : [];
                                ?>
                                <div class="fo-form-block">
                                    <h3><?php echo esc_html( $form_label ); ?></h3>

                                    <?php if ( ! empty( $form_fields ) ) : ?>
                                        <select
                                            class="fo-fields-select frm-history-select-fields"
                                            name="<?php echo esc_attr( FrmHistorySettingsHelper::OPTION_KEY ); ?>[excluded_fields][<?php echo esc_attr( $form_id ); ?>][]"
                                            multiple="multiple"
                                            data-placeholder="Select fields to exclude"
                                            style="width: 100%;"
                                        >
                                            <?php foreach ( $form_fields as $field ) :
                                                $field_id   = isset( $field['id'] ) ? (int) $field['id'] : 0;
                                                $field_name = isset( $field['name'] ) ? $field['name'] : '';
                                                if ( ! $field_id ) {
                                                    continue;
                                                }

                                                $field_label = sprintf( '%s (%d)', $field_name, $field_id );
                                                $is_selected = in_array( $field_id, $excluded_for, true );
                                                ?>
                                                <option value="<?php echo esc_attr( $field_id ); ?>"
                                                    <?php selected( $is_selected ); ?>>
                                                    <?php echo esc_html( $field_label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">
                                            Start typing to search fields in this form.
                                        </p>
                                    <?php else : ?>
                                        <p class="description">No fields found for this form.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="description">No forms found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>

        <script>
            function frmHistorySetActiveTab(tabId) {
                document.querySelectorAll('.nav-tab').forEach(function(tab){
                    tab.classList.remove('nav-tab-active');
                });
                document.querySelectorAll('.fo-tab-content').forEach(function(content){
                    content.style.display = 'none';
                });

                var link = document.querySelector('.nav-tab[href="#' + tabId + '"]');
                var content = document.getElementById(tabId);

                if (link) {
                    link.classList.add('nav-tab-active');
                }
                if (content) {
                    content.style.display = 'block';
                }

                var hidden = document.getElementById('frm_history_active_tab');
                if (hidden) {
                    hidden.value = tabId;
                }
            }

            function frmHistorySwitchTab(event, tabId) {
                event.preventDefault();
                frmHistorySetActiveTab(tabId);
            }
        </script>

        <!-- Select2 (CDN) -->
        <link rel="stylesheet"
              href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <script>
            (function($){
                $(function() {
                    // Init Select2
                    if ( $.fn.select2 ) {
                        $('.frm-history-select-fields').select2({
                            width: '100%',
                            placeholder: function() {
                                return $(this).data('placeholder') || 'Select fields';
                            },
                            allowClear: true
                        });
                    }

                    // AJAX: Verify connection
                    $(document).on('click', '#frm-history-verify-btn', function(e){
                        e.preventDefault();

                        var apiUrl = $('#frm_history_api_url').val() || '';
                        // IMPORTANT: take live value from frm_history_storage_app[token]
                        var token  = $('[name="<?php echo esc_js( FrmHistorySettingsHelper::OPTION_KEY ); ?>[token]"]').val() || '';
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
                            if (response && response.data) {
                                console.log(
                                    'FrmHistory verify route:',
                                    response.data.route_key || '(no key)',
                                    response.data.route_url || '(no url)'
                                );
                            }

                            if (response && response.success) {
                                var text = (response.data && response.data.message)
                                    ? response.data.message
                                    : 'Connection successful.';
                                $msg
                                    .removeClass('fo-error')
                                    .addClass('fo-success')
                                    .text(text);
                            } else {
                                // On any logical failure → Status: Failed
                                $msg
                                    .removeClass('fo-success')
                                    .addClass('fo-error')
                                    .text('Status: Failed');
                            }
                        }).fail(function(){
                            // On transport failure → Status: Failed
                            $msg
                                .removeClass('fo-success')
                                .addClass('fo-error')
                                .text('Status: Failed');
                        });
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
            .fo-form-block {
                border-top: 1px solid #eee;
                padding-top: 15px;
                margin-top: 15px;
            }
            .fo-form-block h3 {
                margin-top: 0;
            }
            .fo-fields-select {
                max-width: 100%;
            }
        </style>
        <?php
    }

    /**
     * AJAX: verify connection using FrmHistoryReferences (api_status).
     */
    public function ajax_verify_connection() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                [
                    'message'   => 'Permission denied.',
                    'route_key' => 'api_status',
                    'route_url' => '',
                ],
                403
            );
        }

        check_ajax_referer( 'frm_history_verify_connection', 'nonce' );

        $api_url_raw = isset( $_POST['api_url'] ) ? wp_unslash( $_POST['api_url'] ) : '';
        $token_raw   = isset( $_POST['token'] ) ? wp_unslash( $_POST['token'] ) : '';

        // IMPORTANT: use values from POST (current form), not DB
        $api_url = esc_url_raw( trim( $api_url_raw ) );
        $token   = sanitize_text_field( $token_raw );

        $route_key = 'api_status';
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
