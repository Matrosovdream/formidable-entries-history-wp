<?php
if (!defined('ABSPATH')) exit;

class FrmHistorySettingsHelper {

    const OPTION_KEY = 'frm_history_storage_app';

    public function get_settings(): array {
        $defaults = [
            'api_url' => '',
            'token'   => '',
        ];

        $saved = get_option(self::OPTION_KEY, []);

        if (!is_array($saved)) {
            $saved = [];
        }

        return wp_parse_args($saved, $defaults);
    }

    public function sanitize_settings(array $input): array {
        $output = [
            'api_url' => '',
            'token'   => '',
        ];

        if (isset($input['api_url'])) {
            $output['api_url'] = esc_url_raw(trim($input['api_url']));
        }
        if (isset($input['token'])) {
            $output['token'] = sanitize_text_field($input['token']);
        }

        return $output;
    }

    public function save_settings(array $settings): void {
        update_option(self::OPTION_KEY, $this->sanitize_settings($settings));
    }

    public function get_api_url(): string {
        $settings = $this->get_settings();
        return $settings['api_url'];
    }

    public function get_token(): string {
        $settings = $this->get_settings();
        return $settings['token'];
    }

    public function build_status_endpoint(): string {
        $api = rtrim($this->get_api_url(), '/');
        return $api ? $api . '/account/status' : '';
    }
}
