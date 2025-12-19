<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Concrete API client for Entry History.
 *
 * Methods:
 *  - sendRequest(array $data)
 *  - updateFields(array $data)
 *  - updateHistory(int $entry_id, array $data)
 *  - getHistoryByEntry(int $entry_id)
 */
class FrmHistoryApi extends FrmHistoryApiAbstract {

    public function __construct(
        FrmHistorySettingsHelper $helper = null,
        FrmHistoryReferences $references = null
    ) {
        parent::__construct( $helper, $references );
    }

    /**
     * Update fields in the external storage.
     */
    public function updateFields( array $data ): array {

        return $this->requestByKey( 'update_fields', $data );
        
    }

    /**
     * Update history for a specific entry.
     */
    public function updateHistory( array $payload ): array {
        return $this->requestByKey( 'update_history', $payload );
    }

    /**
     * Get history by entry from the external storage.
     */
    public function getHistoryByEntry( int $entry_id ): array {

        $params = [
            'id' => $entry_id,        // for /{id} in URL
        ];

        return $this->requestByKey( 'get_history', [], $params );
    }

    /**
     * Email log update all
     */
    public function emailsLogUpdateAll( array $payload ): array {

        return $this->requestByKey( 'emails_log_update_all', $payload );

    }

    /**
     * Email log Get all
     */   
    public function getEmailLogsAll( array $payload ): array {

        return $this->requestByKey( 'emails_log_list', $payload );

    }        

}
