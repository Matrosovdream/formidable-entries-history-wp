<?php

class FrmHistoryEntryService {

    protected $api;

    public function __construct( 
        FrmHistoryApi $api = null
        ) {
        $this->api = $api ?: new FrmHistoryApi();

    }

    public function updateEntryHistory( array $payload ) {

        return $this->api->updateHistory( $payload );

    }

    public function getEntryHistory( int $entryId ) {

        return $this->api->getHistoryByEntry( $entryId );

    }    

}