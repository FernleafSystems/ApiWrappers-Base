<?php declare( strict_types=1 );

namespace FernleafSystems\ApiWrappers\Base;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * @property array  $reqdata
 * @property array  $reqquery
 * @property array  $request_headers
 * @property string $request_content_type
 * @property bool   $decode_response_as_array
 */
abstract class BaseApi extends DynPropertiesClass {

	use ConnectionConsumer;

	const REQUEST_METHOD = 'post';

	protected ?Client $httpClient = null;

	protected ?RequestException $lastError = null;

	protected ?ResponseInterface $lastResponse = null;

	public function __construct( ?Connection $connection = null ) {
		$this->setConnection( $connection );
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {

			case 'reqdata':
			case 'reqquery':
			case 'request_headers':
				if ( !is_array( $value ) ) {
					$value = [];
				}
				break;

			case 'request_content_type':
				if ( empty( $value ) ) {
					$value = 'application/json';
				}
				break;

			case 'decode_response_as_array':
				if ( is_null( $value ) ) {
					$value = true;
				}
				break;
			default:
				break;
		}
		return $value;
	}

	/**
	 * Takes a unix timestamp and converts it to the standard format for sending dates for the particular API
	 * @return string|int|mixed
	 */
	public static function convertToStdDateFormat( int $timestamp ) {
		return $timestamp;
	}

	public function req() :self {
		try {
			$this->send();
		}
		catch ( \Exception $e ) {
		}
		return $this;
	}

	/**
	 * @throws \Exception
	 */
	public function send() :self {
		$this->preSendVerification();
		$this->preFlight();

		$client = $this->getHttpRequest();

		$request = new Request(
			$this->getHttpRequestMethod(),
			$this->getUrlEndpoint()
		);
		try {
			$this->setLastError( null )
				 ->setLastApiResponse( $client->send( $request, $this->prepFinalRequestData() ) );
		}
		catch ( RequestException $RE ) {
			$this->setLastError( $RE );
		}
		catch ( GuzzleException $e ) {
		}
		catch ( \Exception $e ) {
		}
		return $this;
	}

	/**
	 * Helper method to get json-decoded array from body.  Override for anything else
	 */
	public function getDecodedResponseBody() :array {
		$response = [];
		if ( !$this->hasError() ) {
			$response = \json_decode( $this->getResponseBodyContentRaw(), $this->isDecodeAsArray() );
		}
		return \is_array( $response ) ? $response : [];
	}

	/**
	 * Helper method to quickly build a VO from a retrieve request
	 * @return BaseVO|mixed|null
	 */
	public function asVo() :?BaseVO {
		$VO = null;
		if ( $this->req()->isLastRequestSuccess() ) {
			$VO = $this->getVO()->applyFromArray( $this->getDecodedResponseBody() );
		}
		return $VO;
	}

	/**
	 * @return BaseVO|mixed
	 */
	protected function getVO() :BaseVO {
		return new BaseVO();
	}

	public function getResponseBodyContentRaw() :string {
		$content = '';
		if ( $this->hasLastApiResponse() ) {
			$body = $this->getLastApiResponse()->getBody();
			$body->rewind();
			$content = $body->getContents();
		}
		return $content;
	}

	protected function prepFinalRequestData() :array {
		$final = [
			'headers' => $this->getRequestHeaders()
		];

		$channel = $this->getDataChannel();
		if ( $channel == 'query' ) {
			$reqData = array_merge( $this->getRequestDataFinal(), $this->getRequestQueryData() );
			if ( !empty( $reqData ) ) {
				$final[ 'query' ] = $reqData;
			}
		}
		else {
			$reqData = $this->getRequestQueryData();
			if ( !empty( $reqData ) ) {
				$final[ 'query' ] = $reqData;
			}

			$reqData = $this->getRequestDataFinal();
			if ( !empty( $reqData ) ) {
				$final[ $channel ] = $reqData;
			}
		}

		// maybe use array_filter instead of all the ifs, what about non-array values?
		return $final;
	}

	/**
	 * Do anything required before the API request is built and sent.
	 */
	protected function preFlight() {
	}

	/**
	 * @throws \Exception
	 */
	protected function preSendVerification() {
		if ( !$this->getConnection() instanceof Connection ) {
			throw new \Exception( 'Attempting to make API request without a Connection' );
		}

		array_map(
			function ( $itemKey ) {
				if ( !$this->hasRequestDataItem( $itemKey ) ) {
					throw new \Exception( sprintf( 'Request Data Item "%s" must be provided', $itemKey ) );
				}
			},
			$this->getCriticalRequestItems()
		);
	}

	/**
	 * Let the Connection class handle this, but there is the option to override how
	 * this URL is formed here for weird APIs/
	 * Base URLs should generally have a trailing slash
	 */
	protected function getBaseUrl() :string {
		return rtrim( $this->getConnection()->getBaseUrl(), '/' ).'/';
	}

	/**
	 * @return string[]
	 */
	protected function getCriticalRequestItems() :array {
		return [];
	}

	protected function getHttpRequest() :Client {
		if ( !$this->httpClient instanceof Client ) {
			$this->httpClient = new Client( [ 'base_uri' => $this->getBaseUrl() ] );
		}
		return $this->httpClient;
	}

	protected function getHttpRequestMethod() :string {
		$method = strtolower( static::REQUEST_METHOD );
		if ( !in_array( $method, [ 'get', 'head', 'patch', 'post', 'put', 'delete' ] ) ) {
			$method = 'get';
		}
		return $method;
	}

	protected function getUrlEndpoint() :string {
		return '';
	}

	public function getLastApiResponse() :?ResponseInterface {
		return $this->lastResponse;
	}

	public function getLastError() :?RequestException {
		return $this->lastError;
	}

	public function getLastErrorContent() :string {
		return $this->hasError() ? $this->getLastError()->getResponse()->getBody()->getContents() : '';
	}

	public function getRequestData() :array {
		return $this->reqdata;
	}

	public function getDataChannel() :string {

		switch ( $this->getRequestContentType() ) {

			case 'application/x-www-form-urlencoded':
				$channel = 'form_params';
				break;

			case 'application/json':
			case 'application/vnd.api+json':
			default:
				$channel = ( $this->getHttpRequestMethod() == 'get' ) ? 'query' : 'json';
				break;
		}
		return $channel;
	}

	/**
	 * @return mixed|null
	 */
	public function getRequestDataItem( string $key ) {
		return $this->reqdata[ $key ] ?? null;
	}

	public function getRequestHeaders() :array {
		if ( empty( $this->request_headers ) ) {
			$this->request_headers = [
				'Accept'       => $this->getRequestContentType(),
				'Content-Type' => $this->getRequestContentType(),
			];
		}
		return $this->request_headers;
	}

	public function getRequestContentType() :string {
		return $this->request_content_type;
	}

	/**
	 * This allows us to set Query Params separately to the body of an API request, ?asdf=ghijk
	 */
	public function getRequestQueryData() :array {
		return $this->reqquery;
	}

	/**
	 * @return int[]
	 */
	public function getSuccessfulResponseCodes() :array {
		return [ 200, 201, 202, 204 ];
	}

	public function hasError() :bool {
		return $this->getLastError() instanceof RequestException;
	}

	public function hasLastApiResponse() :bool {
		return $this->getLastApiResponse() instanceof ResponseInterface;
	}

	public function hasRequestDataItem( string $key ) :bool {
		return array_key_exists( $key, $this->getRequestData() );
	}

	public function isDecodeAsArray() :bool {
		return (bool)$this->decode_response_as_array;
	}

	public function isLastRequestSuccess() :bool {
		return !$this->hasError() && $this->hasLastApiResponse() &&
			   in_array( $this->getLastApiResponse()->getStatusCode(), $this->getSuccessfulResponseCodes() );
	}

	public function removeRequestDataItem( string $key ) :self {
		$aData = $this->getRequestData();
		if ( array_key_exists( $key, $aData ) ) {
			unset( $aData[ $key ] );
			$this->setRequestData( $aData, false );
		}
		return $this;
	}

	public function setDecodeAsArray( bool $decodeAsArray ) :self {
		$this->decode_response_as_array = $decodeAsArray;
		return $this;
	}

	public function setLastApiResponse( ?ResponseInterface $lastApiResponse ) :self {
		$this->lastResponse = $lastApiResponse;
		return $this;
	}

	public function setLastError( ?RequestException $lastError = null ) :self {
		$this->lastError = $lastError;
		return $this;
	}

	public function setRequestContentType( string $type ) :self {
		$this->request_content_type = $type;
		return $this;
	}

	public function setRequestHeader( string $key, string $value ) :self {
		$headers = $this->getRequestHeaders();
		$headers[ $key ] = $value;
		return $this->setRequestHeaders( $headers );
	}

	public function setRequestHeaders( array $headers ) :self {
		$this->request_headers = $headers;
		return $this;
	}

	public function setRequestData( array $newData, bool $merge = true ) :self {
		$this->reqdata = $merge ? array_merge( $this->getRequestData(), $newData ) : $newData;
		return $this;
	}

	public function setRequestDataItem( string $key, $mValue ) :self {
		return $this->setRequestData( [ $key => $mValue ] );
	}

	public function setRequestQueryData( $newData, $merge = true ) :self {
		$this->reqquery = $merge ? array_merge( $this->getRequestQueryData(), $newData ) : $newData;
		return $this;
	}

	public function setRequestQueryDataItem( $key, $mValue ) :self {
		return $this->setRequestQueryData( [ $key => $mValue ] );
	}

	/**
	 * This is called right at the point of setting the data for the HTTP Request and should only
	 * ever be used for that purpose.  use getRequestData() otherwise.
	 */
	public function getRequestDataFinal() :array {
		return $this->getRequestData();
	}
}