<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrmHistorySettingsHelper {

    public const OPTION_KEY = 'frm_history_storage_app';

    public function get_settings(): array {
        $defaults = [
            'api_url'        => '',
            'token'          => '',
            'excluded_fields'=> [],               // [ form_id => [ field_id, ... ] ]
            'active_tab'     => 'api-connection', // 'api-connection' | 'field-settings'
        ];

        $saved = get_option( self::OPTION_KEY, [] );

        if ( ! is_array( $saved ) ) {
            $saved = [];
        }

        $settings = wp_parse_args( $saved, $defaults );

        // Normalize excluded_fields
        $normalized = [];
        if ( ! empty( $settings['excluded_fields'] ) && is_array( $settings['excluded_fields'] ) ) {
            foreach ( $settings['excluded_fields'] as $form_id => $field_ids ) {
                $form_id = (int) $form_id;
                if ( ! $form_id || ! is_array( $field_ids ) ) {
                    continue;
                }

                $clean_field_ids = [];
                foreach ( $field_ids as $fid ) {
                    $fid = (int) $fid;
                    if ( $fid ) {
                        $clean_field_ids[] = $fid;
                    }
                }

                if ( ! empty( $clean_field_ids ) ) {
                    $normalized[ $form_id ] = array_values( array_unique( $clean_field_ids ) );
                }
            }
        }
        $settings['excluded_fields'] = $normalized;

        // Normalize active_tab
        if ( ! in_array( $settings['active_tab'], [ 'api-connection', 'field-settings' ], true ) ) {
            $settings['active_tab'] = 'api-connection';
        }

        return $settings;
    }

    public function sanitize_settings( array $input ): array {
        $output = [
            'api_url'        => '',
            'token'          => '',
            'excluded_fields'=> [],
            'active_tab'     => 'api-connection',
        ];

        // API URL
        if ( isset( $input['api_url'] ) ) {
            $output['api_url'] = esc_url_raw( trim( (string) $input['api_url'] ) );
        }

        // Token
        if ( isset( $input['token'] ) ) {
            $output['token'] = sanitize_text_field( (string) $input['token'] );
        }

        // Excluded fields: [form_id => [field_id, ...]]
        if ( isset( $input['excluded_fields'] ) && is_array( $input['excluded_fields'] ) ) {
            $excluded = [];

            foreach ( $input['excluded_fields'] as $form_id => $field_ids ) {
                $form_id = (int) $form_id;
                if ( ! $form_id || ! is_array( $field_ids ) ) {
                    continue;
                }

                $clean_ids = [];
                foreach ( $field_ids as $fid ) {
                    $fid = (int) $fid;
                    if ( $fid ) {
                        $clean_ids[] = $fid;
                    }
                }

                if ( ! empty( $clean_ids ) ) {
                    $excluded[ $form_id ] = array_values( array_unique( $clean_ids ) );
                }
            }

            $output['excluded_fields'] = $excluded;
        }

        // Active tab
        if ( isset( $input['active_tab'] ) ) {
            $tab = sanitize_text_field( (string) $input['active_tab'] );
            if ( in_array( $tab, [ 'api-connection', 'field-settings' ], true ) ) {
                $output['active_tab'] = $tab;
            }
        }

        return $output;
    }

    public function save_settings( array $settings ): void {
        update_option( self::OPTION_KEY, $this->sanitize_settings( $settings ) );
    }

    public function get_api_url(): string {
        $settings = $this->get_settings();
        return (string) ( $settings['api_url'] ?? '' );
    }

    public function get_token(): string {
        $settings = $this->get_settings();
        return (string) ( $settings['token'] ?? '' );
    }
}
