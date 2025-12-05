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

        $res = $this->api->getHistoryByEntry( $entryId );

        // Prepare history data if needed
        foreach ( $res['data']['items'] as $key=>$item ) {
            
            // User id
            if ( isset( $item['user_id'] ) ) {
                $item['user'] = $this->getUserById( $item['user_id'] );
            }

            $res['data']['items'][$key] = $item;

        }

        return $res;

    }    

    protected function getUserById( int $user_id ) {

        $user = get_userdata( $user_id );

        if( empty( $user ) ) {
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