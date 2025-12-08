<?php

class FrmHistoryEntryService {

    /** @var FrmHistoryApi */
    protected $api;

    /** @var FrmHistorySettingsHelper */
    protected $settingsHelper;

    public function __construct(
        FrmHistoryApi $api = null,
        FrmHistorySettingsHelper $settingsHelper = null
    ) {
        $this->api            = $api ?: new FrmHistoryApi();
        $this->settingsHelper = $settingsHelper ?: new FrmHistorySettingsHelper();
    }

    /**
     * Push updates to storage app.
     *
     * @param array $payload
     *
     * @return array|WP_Error
     */
    public function updateEntryHistory( array $payload ) {
        return $this->api->updateHistory( $payload );
    }

    /**
     * Get entry history.
     *
     * @param int  $entryId
     * @param bool $excludeFields If true, remove fields marked as excluded in settings.
     *
     * @return array|WP_Error
     */
    public function getEntryHistory( int $entryId, bool $excludeFields = false ) {
        $res = $this->api->getHistoryByEntry( $entryId );

        // If API returned error or unexpected structure, just return as is.
        if ( is_wp_error( $res ) || ! is_array( $res ) ) {
            return $res;
        }

        if ( empty( $res['data'] ) || ! is_array( $res['data'] ) ) {
            return $res;
        }

        $data = $res['data'];

        $entry = FrmEntry::getOne( $entryId );
        $formId = $entry->form_id;

        // Optionally exclude fields based on settings.
        $excludedForForm = [];
        if ( $excludeFields && $formId ) {
            $settings = $this->settingsHelper->get_settings();
            if ( isset( $settings['excluded_fields'][ $formId ] ) && is_array( $settings['excluded_fields'][ $formId ] ) ) {
                // Normalize to int list.
                $excludedForForm = array_map( 'intval', $settings['excluded_fields'][ $formId ] );
            }
        }

        if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
            $items = [];

            foreach ( $data['items'] as $key => $item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }

                // Exclude by field_id if requested and list exists.
                if ( $excludeFields && ! empty( $excludedForForm ) && isset( $item['field_id'] ) ) {
                    $fieldId = (int) $item['field_id'];
                    if ( in_array( $fieldId, $excludedForForm, true ) ) {
                        // Skip this item.
                        continue;
                    }
                }

                // Attach user data if available.
                if ( isset( $item['user_id'] ) ) {
                    $item['user'] = $this->getUserById( (int) $item['user_id'] );
                }

                $items[] = $item;
            }

            // Replace items with filtered + enriched list.
            $res['data']['items'] = $items;
        }

        return $res;
    }

    /**
     * Simple user resolver by WP user ID.
     *
     * @param int $user_id
     *
     * @return array|null
     */
    protected function getUserById( int $user_id ) {
        $user = get_userdata( $user_id );

        if ( empty( $user ) ) {
            return null;
        }

        $userData = $user->data;

        return [
            'login' => $userData->user_login,
            'name'  => $userData->display_name,
            'email' => $userData->user_email,
        ];
    }
}
