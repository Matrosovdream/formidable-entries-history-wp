<?php

class FrmHistoryFieldsService {

    protected $helper;
    protected $api;

    public function __construct( 
        FrmHistoryFieldsHelper $helper = null,
        FrmHistoryApi $api = null
        ) {

        $this->helper = $helper ?: new FrmHistoryFieldsHelper();
        $this->api = $api ?: new FrmHistoryApi();

    }

    public function syncFields( array $filter = [] ): array {

        $fields = $this->helper->getFieldsAll( $filter );

        /*
        echo '<pre>';
        print_r( $fields );
        echo '</pre>';
        */

        if ( empty( $fields ) ) {
            return [];
        }

        $response = $this->api->updateFields( [ 'fields' => $fields ] );

        return $response;
    }

}