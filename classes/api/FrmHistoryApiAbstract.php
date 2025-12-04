<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Base API client for Entry History storage app.
 */
abstract class FrmHistoryApiAbstract {

    /** @var FrmHistorySettingsHelper */
    protected $settingsHelper;

    /** @var FrmHistoryReferences */
    protected $references;

    public function __construct(
        FrmHistorySettingsHelper $helper = null,
        FrmHistoryReferences $references = null
    ) {
        $this->settingsHelper = $helper ?: new FrmHistorySettingsHelper();
        $this->references     = $references ?: new FrmHistoryReferences();
    }

    /**
     * Default endpoint path (from references).
     */
    protected function getDefaultEndpoint(): string {
        $route = $this->references->getRoute( 'update_fields' );
        if ( $route && ! empty( $route['path'] ) ) {
            return $route['path'];
        }
        return '/entry-history';
    }

    /**
     * Base URL from settings (no trailing slash).
     */
    protected function getBaseUrl(): string {
        $url = $this->settingsHelper->get_api_url();
        return rtrim( (string) $url, '/' );
    }

    /**
     * Headers for API requests.
     */
    protected function getHeaders(): array {
        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
        ];

        $token = $this->settingsHelper->get_token();
        if ( ! empty( $token ) ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * Low-level HTTP request.
     *
     * @return array ['success' => bool, 'code' => int, 'data' => mixed, 'error' => string|null]
     */
    protected function doHttpRequest( string $method, string $path, array $data = [] ): array {
        $base = $this->getBaseUrl();
        if ( empty( $base ) ) {
            return [
                'success' => false,
                'code'    => 0,
                'data'    => null,
                'error'   => 'API URL is not configured.',
            ];
        }

        $url = $base . '/' . ltrim( $path, '/' );
        $args = [
            'method'  => strtoupper( $method ),
            'timeout' => 15,
            'headers' => $this->getHeaders(),
        ];

        if ( in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
            $args['body'] = wp_json_encode( $data );
        } elseif ( ! empty( $data ) ) {
            $url = add_query_arg( $data, $url );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'code'    => 0,
                'data'    => null,
                'error'   => $response->get_error_message(),
            ];
        }

        $code    = (int) wp_remote_retrieve_response_code( $response );
        $body    = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );

        $success = $code >= 200 && $code < 300;

        return [
            'success' => $success,
            'code'    => $code,
            'data'    => $decoded !== null ? $decoded : $body,
            'error'   => $success ? null : 'HTTP ' . $code,
        ];
    }

    /**
     * Helper: call by route key from FrmHistoryReferences.
     * Supports placeholders in path: /entry/history/view/{id}
     */
    protected function requestByKey( string $route_key, array $data = [], array $params = [] ): array {
        $route = $this->references->getRoute( $route_key );
        if ( ! $route || empty( $route['path'] ) || empty( $route['method'] ) ) {
            return [
                'success' => false,
                'code'    => 0,
                'data'    => null,
                'error'   => 'Unknown API route: ' . $route_key,
            ];
        }

        $path = $route['path'];

        // Replace placeholders from $params only
        if (strpos($path, '{') !== false) {
            $path = preg_replace_callback(
                '/\{([^}]+)\}/',
                function ($matches) use ($params) {
                    $key = $matches[1];

                    if (array_key_exists($key, $params)) {
                        return rawurlencode((string) $params[$key]);
                    }

                    return $matches[0]; // keep placeholder unchanged
                },
                $path
            );
        }

        $res = $this->doHttpRequest($route['method'], $path, $data);

        if ( $res['success'] ) {
            return $res['data'];
        } else {
            return $res;
        }

    }


    /**
     * Public high-level sendRequest(data) to default endpoint.
     */
    public function sendRequest( array $data ): array {
        $path = $this->getDefaultEndpoint();
        return $this->doHttpRequest( 'POST', $path, $data );
    }
}
