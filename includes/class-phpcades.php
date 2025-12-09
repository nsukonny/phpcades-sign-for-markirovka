<?php

/**
 * Sing auth token for Markirovka API using CAdES signature and client certificate
 */
class PhpCadesSigner {

	private string $container_pass;

	private string $markirovka_api_url;

	private string $cert_thumbprint;

	public function __construct( string $markirovka_api_url, string $cert_thumbprint, string $container_pass ) {
		$this->container_pass     = $container_pass;
		$this->markirovka_api_url = $markirovka_api_url;
		$this->cert_thumbprint    = $cert_thumbprint;
	}

	/**
	 * Get token for using Markirovka API
	 *
	 * @throws Exception
	 */
	public function get_simple_signin_token(): array {

		$auth      = $this->get_api_auth_key();
		$uuid      = $auth['uuid'] ?? '';
		$challenge = $auth['data'] ?? '';

		if ( empty( $uuid ) || empty( $challenge ) ) {
			throw new Exception( 'Cannot get auth key from Markirovka API' );
		}

		$signature = $this->sing_by_phpcades( $challenge );
		if ( empty( $signature ) ) {
			throw new Exception( 'Cannot create signature by client Key' );
		}

		$token = $this->get_api_token( $uuid, $signature );

		return array(
			'uuid'  => $uuid,
			'token' => $token,
		);
	}

	private function SetupStore( $location, $name, $mode ) {
		$store = new CPStore();
		$store->Open( $location, $name, $mode );

		return $store;
	}

	private function setup_certificates( $location, $name, $mode ) {
		$store = $this->SetupStore( $location, $name, $mode );

		return $store->get_Certificates();
	}

	private function setup_certificate(
		$location, $name, $mode,
		$find_type, $query, $valid_only,
		$number
	) {
		$certs = $this->setup_certificates( $location, $name, $mode );
		if ( ! is_null( $find_type ) ) {
			$certs = $certs->Find( $find_type, $query, $valid_only );
			if ( is_string( $certs ) ) {
				return $certs;
			} else {
				return $certs->Item( $number );
			}
		} else {
			$cert = $certs->Item( $number );

			return $cert;
		}
	}

	/**
	 * Get auth key from Markirovka API
	 *
	 * @return array
	 */
	private function get_api_auth_key(): array {
		$json_answer = file_get_contents( $this->markirovka_api_url . "/auth/key" );

		if ( empty( $json_answer ) ) {
			return array();
		}

		$data = json_decode( $json_answer, true );
		if ( empty( $data ) || ! isset( $data['uuid'] ) || ! isset( $data['data'] ) ) {
			return array();
		}

		return $data;
	}

	/**
	 * Sign challenge by CAdES using PHP-CAdES extension
	 *
	 * @param mixed $challenge
	 *
	 * @return string
	 */
	private function sing_by_phpcades( mixed $challenge ): string {

		$cert = $this->setup_certificate(
			CURRENT_USER_STORE, "My", STORE_OPEN_READ_ONLY,
			CERTIFICATE_FIND_SHA1_HASH, $this->cert_thumbprint, 0, 1
		);

		if ( ! $cert ) {
			return '';
		}

		$signer = new CPSigner();
		$signer->set_Certificate( $cert );
		$signer->set_KeyPin( $this->container_pass );
		$signer->set_Options( 2 );

		$sd = new CPSignedData();
		$sd->set_Content( $challenge );

		$signature = $sd->SignCades( $signer, CADES_BES, false, ENCODE_BASE64 );

		return preg_replace( "/[\r\n]+/", "", $signature );
	}

	/**
	 * Auth on Markirovka API and get token
	 *
	 * @param $uuid
	 * @param $signature
	 *
	 * @return string
	 * @throws Exception
	 */
	private function get_api_token( $uuid, $signature ): string {

		$payload = json_encode( array(
			'uuid' => $uuid,
			'data' => $signature
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		$ch = curl_init( $this->markirovka_api_url . "/auth/simpleSignIn" );
		curl_setopt_array( $ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_HTTPHEADER     => [ 'Content-Type: application/json', 'Accept: application/json' ],
			CURLOPT_POSTFIELDS     => $payload,
		] );

		$res = curl_exec( $ch );
		if ( $res === false ) {
			throw new Exception( 'curl: ' . curl_error( $ch ) );
		}
		curl_close( $ch );

		$data = json_decode( $res, true );
		if ( empty( $data ) || ! isset( $data['token'] ) ) {
			throw new Exception( 'Cannot get token from Markirovka API' );
		}

		return $data['token'];
	}
}