<?php

namespace Cheevos;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

class CheevosClient {
	public function __construct(
		private Client $httpClient,
		private string $serviceUrl,
		private array $headers
	) {
	}

	public function get( string $path, array $data ): array {
		return $this->sendRequest( 'GET', $path, $data );
	}

	public function post( string $path, array $data ): array {
		return $this->sendRequest( 'POST', $path, $data );
	}

	public function put( string $path, array $data ): array {
		return $this->sendRequest( 'PUT', $path, $data );
	}

	public function delete( string $path, array $data ): array {
		return $this->sendRequest( 'DELETE', $path, $data );
	}

	private function sendRequest( string $type, string $path, array $data = [] ): array {
		$type = strtoupper( $type );
		$uri = "$this->serviceUrl/$path";
		$options = [
			RequestOptions::HEADERS => $this->headers,
			RequestOptions::TIMEOUT => 10,
		];

		if ( in_array( $type, [ 'DELETE', 'GET' ] ) && !empty( $data ) ) {
			$uri .= '/?' . http_build_query( $data );
		} else {
			$options[ RequestOptions::BODY ] = json_encode( $data );
		}

		try {
			$response = $this->httpClient->request( $type, $uri, $options );
		} catch ( GuzzleException $e ) {
			if ( $e->getCode() === 503 || $e->getCode() === 0 ) {
				throw new CheevosException( 'Cheevos Service Unavailable', $e->getCode() );
			}
			throw new CheevosException( $e->getMessage(), $e->getCode() );
		}

		return json_decode( $response->getBody(), true );
	}

	public function parse( array $data, ?string $field = null, ?string $class = null, bool $returnFirst = false ) {
		if ( $field && isset( $data[ $field ] ) ) {
			$data = $data[ $field ];
		}
		if ( !$class ) {
			return $data;
		}

		$response = [];
		foreach ( $data as $classData ) {
			if ( is_array( $classData ) ) {
				$object = new $class( $classData );
				if ( $object->hasId() ) {
					$response[$object->getId()] = $object;
				} else {
					$response[] = $object;
				}
				if ( $returnFirst ) {
					return $object;
				}
			}
		}
		return $response;
	}
}
