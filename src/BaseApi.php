<?php

namespace FernleafSystems\ApiWrappers\Base;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * Class BaseApi
 * @package FernleafSystems\ApiWrappers\Base
 */
abstract class BaseApi {

	use ConnectionConsumer,
		StdClassAdapter;
	const REQUEST_METHOD = 'post';

	/**
	 * @var Client
	 */
	protected $oHttp;

	/**
	 * @var RequestException
	 */
	protected $oLastError;

	/**
	 * @var ResponseInterface
	 */
	protected $oLastResponse;

	/**
	 * @param Connection $oConnection
	 */
	public function __construct( $oConnection = null ) {
		$this->setConnection( $oConnection );
	}

	/**
	 * Takes a unix timestamp and converts it to the standard format for sending dates for the particular API
	 * @param int $nTimestamp
	 * @return string|int|mixed
	 */
	static public function convertToStdDateFormat( $nTimestamp ) {
		return $nTimestamp;
	}

	/**
	 * @return $this
	 */
	public function req() {
		try {
			$this->send();
		}
		catch ( \Exception $oE ) {
		}
		return $this;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	public function send() {
		$this->preSendVerification();
		$this->preFlight();

		$oClient = $this->getHttpRequest();

		$oRequest = new Request(
			$this->getHttpRequestMethod(),
			$this->getUrlEndpoint()
		);
		try {
			$this->setLastError( null )
				 ->setLastApiResponse( $oClient->send( $oRequest, $this->prepFinalRequestData() ) );
		}
		catch ( RequestException $oRE ) {
			$this->setLastError( $oRE );
		}
		return $this;
	}

	/**
	 * Assumes response body is json-encode.  Override for anything else
	 * @return array
	 */
	public function getDecodedResponseBody() {
		$sResponse = [];
		if ( !$this->hasError() ) {
			$sResponse = json_decode( $this->getResponseBodyContentRaw(), $this->isDecodeAsArray() );
		}
		return $sResponse;
	}

	/**
	 * @return BaseVO|mixed
	 */
	protected function getVO() {
		return new BaseVO();
	}

	/**
	 * @return string
	 */
	public function getResponseBodyContentRaw() {
		$sContent = '';
		if ( $this->hasLastApiResponse() ) {
			$oBody = $this->getLastApiResponse()->getBody();
			$oBody->rewind();
			$sContent = $oBody->getContents();
		}
		return $sContent;
	}

	/**
	 * @return array
	 */
	protected function prepFinalRequestData() {
		$aFinal = [
			'headers' => $this->getRequestHeaders()
		];

		$sChannel = $this->getDataChannel();
		if ( $sChannel == 'query' ) {
			$aD = array_merge( $this->getRequestDataFinal(), $this->getRequestQueryData() );
			if ( !empty( $aD ) ) {
				$aFinal[ 'query' ] = $aD;
			}
		}
		else {
			$aD = $this->getRequestQueryData();
			if ( !empty( $aD ) ) {
				$aFinal[ 'query' ] = $aD;
			}

			$aD = $this->getRequestDataFinal();
			if ( !empty( $aD ) ) {
				$aFinal[ $sChannel ] = $aD;
			}
		}

		// maybe use array_filter instead of all the ifs, what about non-array values?
		return $aFinal;
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
		if ( is_null( $this->getConnection() ) ) {
			throw new \Exception( 'Attempting to make API request without a Connection' );
		}

		array_map(
			function ( $sItemKey ) {
				if ( !$this->hasRequestDataItem( $sItemKey ) ) {
					throw new \Exception( sprintf( 'Request Data Item "%s" must be provided', $sItemKey ) );
				}
			},
			$this->getCriticalRequestItems()
		);
	}

	/**
	 * Let the Connection class handle this, but there is the option to override how
	 * this URL is formed here for weird APIs/
	 * Base URLs should generally have a trailing slash
	 * @return string
	 */
	protected function getBaseUrl() {
		return rtrim( $this->getConnection()->getBaseUrl(), '/' ).'/';
	}

	/**
	 * @return string[]
	 */
	protected function getCriticalRequestItems() {
		return [];
	}

	/**
	 * @return Client
	 */
	protected function getHttpRequest() {
		if ( empty( $this->oHttp ) ) {
			$this->oHttp = new Client( [ 'base_uri' => $this->getBaseUrl() ] );
		}
		return $this->oHttp;
	}

	/**
	 * @return string
	 */
	protected function getHttpRequestMethod() {
		$sMethod = strtolower( static::REQUEST_METHOD );
		if ( !in_array( $sMethod, [ 'get', 'head', 'patch', 'post', 'put', 'delete' ] ) ) {
			$sMethod = 'get';
		}
		return $sMethod;
	}

	/**
	 * @return string
	 */
	protected function getUrlEndpoint() {
		return '';
	}

	/**
	 * @return ResponseInterface
	 */
	public function getLastApiResponse() {
		return $this->oLastResponse;
	}

	/**
	 * @return RequestException
	 */
	public function getLastError() {
		return $this->oLastError;
	}

	/**
	 * @return string
	 */
	public function getLastErrorContent() {
		return $this->hasError() ? $this->getLastError()->getResponse()->getBody()->getContents() : '';
	}

	/**
	 * @return array
	 */
	public function getRequestData() {
		return $this->getArrayParam( 'reqdata' );
	}

	/**
	 * @return string
	 */
	public function getDataChannel() {

		switch ( $this->getRequestContentType() ) {

			case 'application/x-www-form-urlencoded':
				$sCh = 'form_params';
				break;

			case 'application/json':
			case 'application/vnd.api+json':
			default:
				$sCh = ( $this->getHttpRequestMethod() == 'get' ) ? 'query' : 'json';
				break;
		}
		return $sCh;
	}

	/**
	 * @param string $sKey
	 * @return mixed|null
	 */
	public function getRequestDataItem( $sKey ) {
		$aData = $this->getRequestData();
		return isset( $aData[ $sKey ] ) ? $aData[ $sKey ] : null;
	}

	/**
	 * @return array
	 */
	public function getRequestHeaders() {
		return $this->getArrayParam(
			'request_headers',
			[
				'Accept'       => $this->getRequestContentType(),
				'Content-Type' => $this->getRequestContentType(),
			]
		);
	}

	/**
	 * @return bool
	 */
	public function getRequestContentType() {
		return $this->getStringParam( 'request_content_type', 'application/json' );
	}

	/**
	 * This allows us to set Query Params separately to the body of an API request, ?asdf=ghijk
	 * @return array
	 */
	public function getRequestQueryData() {
		return $this->getArrayParam( 'reqquery' );
	}

	/**
	 * @return int[]
	 */
	public function getSuccessfulResponseCodes() {
		return [ 200, 201, 202, 204 ];
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function hasRequestDataItem( $sKey ) {
		return array_key_exists( $sKey, $this->getRequestData() );
	}

	/**
	 * @return bool
	 */
	public function isLastRequestSuccess() {
		return !$this->hasError() && $this->hasLastApiResponse() &&
			   in_array( $this->getLastApiResponse()->getStatusCode(), $this->getSuccessfulResponseCodes() );
	}

	/**
	 * @return bool
	 */
	public function hasError() {
		return $this->getLastError() instanceof RequestException;
	}

	/**
	 * @return bool
	 */
	public function hasLastApiResponse() {
		return $this->getLastApiResponse() instanceof ResponseInterface;
	}

	/**
	 * @return bool
	 */
	public function isDecodeAsArray() {
		return (bool)$this->getParam( 'decode_response_as_array', true );
	}

	/**
	 * @param string $sItemKey
	 * @return $this
	 */
	public function removeRequestDataItem( $sItemKey ) {
		$aData = $this->getRequestData();
		if ( array_key_exists( $sItemKey, $aData ) ) {
			unset( $aData[ $sItemKey ] );
			$this->setRequestData( $aData, false );
		}
		return $this;
	}

	/**
	 * @param bool $bDecodeAsArray
	 * @return $this
	 */
	public function setDecodeAsArray( $bDecodeAsArray ) {
		return $this->setParam( 'decode_response_as_array', $bDecodeAsArray );
	}

	/**
	 * @param ResponseInterface $oLastApiResponse
	 * @return $this
	 */
	public function setLastApiResponse( $oLastApiResponse ) {
		$this->oLastResponse = $oLastApiResponse;
		return $this;
	}

	/**
	 * @param RequestException $oLastError
	 * @return $this
	 */
	public function setLastError( $oLastError ) {
		$this->oLastError = $oLastError;
		return $this;
	}

	/**
	 * @param string $sType
	 * @return $this
	 */
	public function setRequestContentType( $sType ) {
		return $this->setParam( 'request_content_type', $sType );
	}

	/**
	 * @param string $sKey
	 * @param string $sValue
	 * @return $this
	 */
	public function setRequestHeader( $sKey, $sValue ) {
		$aHeaders = $this->getRequestHeaders();
		$aHeaders[ $sKey ] = $sValue;
		return $this->setRequestHeaders( $aHeaders );
	}

	/**
	 * @param array $aHeaders
	 * @return $this
	 */
	public function setRequestHeaders( $aHeaders ) {
		return $this->setParam( 'request_headers', $aHeaders );
	}

	/**
	 * @param array $aNewData
	 * @param bool  $bMerge
	 * @return $this
	 */
	public function setRequestData( $aNewData, $bMerge = true ) {
		if ( $bMerge ) {
			$aNewData = array_merge( $this->getRequestData(), $aNewData );
		}
		return $this->setParam( 'reqdata', $aNewData );
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mValue
	 * @return $this
	 */
	public function setRequestDataItem( $sKey, $mValue ) {
		$aData = $this->getRequestData();
		$aData[ $sKey ] = $mValue;
		$this->setRequestData( $aData, false );
		return $this;
	}

	/**
	 * @param array $aNewData
	 * @param bool  $bMerge
	 * @return $this
	 */
	public function setRequestQueryData( $aNewData, $bMerge = true ) {
		if ( $bMerge ) {
			$aNewData = array_merge( $this->getRequestQueryData(), $aNewData );
		}
		return $this->setParam( 'reqquery', $aNewData );
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mValue
	 * @return $this
	 */
	public function setRequestQueryDataItem( $sKey, $mValue ) {
		$aData = $this->getRequestQueryData();
		$aData[ $sKey ] = $mValue;
		$this->setRequestQueryData( $aData, false );
		return $this;
	}

	/**
	 * This is call right at the point of setting the data for the HTTP Request and should only
	 * ever be used for that purpose.  use getRequestData() otherwise.
	 * @return array
	 */
	public function getRequestDataFinal() {
		return $this->getRequestData();
	}
}