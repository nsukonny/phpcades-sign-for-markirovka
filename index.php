<?php

ini_set( 'display_errors', '1' );
ini_set( 'display_startup_errors', '1' );
error_reporting( E_ALL );

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

include_once 'includes/class-phpcades.php';

$dotenv = Dotenv::createImmutable( __DIR__ );
$dotenv->load();

$markirovka_api_url   = $_ENV['MARKIROVKA_API_URL']; //URL to Markirovka API endpoints
$cert_thumbprint      = $_ENV['CERT_THUMBPRINT']; //thumbprint of certificate from `sudo -u www-data /opt/cprocsp/bin/amd64/certmgr -list -store uMy`
$container_pass       = $_ENV['CONTAINER_PASS']; //password for private key container
$request_bearer_token = $_ENV['REQUEST_BEARER_TOKEN']; //secure for my requests

$headers = getallheaders();
if ( ! isset( $headers['Authorization'] ) || $headers['Authorization'] !== 'Bearer ' . $request_bearer_token ) {
	http_response_code( 401 );
	echo json_encode(
		array(
			'status'  => 'error',
			'message' => 'Unauthorized',
		),
		JSON_THROW_ON_ERROR
	);
	exit;
}

$signer = new PhpCadesSigner( $markirovka_api_url, $cert_thumbprint, $container_pass );

try {
	$token         = $signer->get_simple_signin_token();
	$response_data = array(
		'status' => 'success',
		'data'   => $token,
	);
	http_response_code( 200 );
	echo json_encode( $response_data, JSON_THROW_ON_ERROR );
} catch ( Throwable $e ) {
	http_response_code( 500 );
	echo json_encode(
		array(
			'status'  => 'error',
			'message' => $e->getMessage(),
		),
		JSON_THROW_ON_ERROR
	);
}
