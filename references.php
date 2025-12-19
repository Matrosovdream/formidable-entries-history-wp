<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrmHistoryReferences {

    public function getApiRoutes(): array {
        return [
            'api_status' => [
                'method' => 'GET',
                'path'   => '/status',
            ],
            'api_account_status' => [
                'method' => 'POST',
                'path'   => '/account/status',
            ],
            'update_fields' => [
                'method' => 'POST',
                'path'   => '/entry-history',
            ],
            'update_history' => [
                'method' => 'POST',
                'path'   => '/entry/history/update',
            ],
            'get_history' => [
                'method' => 'POST',
                'path'   => '/entry/history/view/{id}',
            ],
            'emails_log_update_all' => [
                'method' => 'POST',
                'path'   => '/emailslog/update-all',
            ],
            'emails_log_list' => [
                'method' => 'POST',
                'path'   => 'emailslog/list',
            ],
        ];
    }

    public function getRoute( string $key ): ?array {

        $routes = $this->getApiRoutes();
        return isset( $routes[ $key ] ) ? $routes[ $key ] : null;

    }
    
}
