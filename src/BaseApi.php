<?php

namespace FernleafSystems\Apis\Base;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\ResponseInterface;

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
	 * @return $this
	 * @throws \Exception
	 */
	public function send() {
		$this->preSendVerification();

		$oClient = $this->getHttpRequest();
		$oRequest = $oClient->createRequest(
			$this->getHttpRequestMethod(),
			$this->getUrlEndpoint(),
			$this->prepFinalRequestData()
		);
		try {
			$this->setLastError( null )
				 ->setLastApiResponse( $oClient->send( $oRequest ) );
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
	protected function getDecodedResponseBody() {
		$sResponse = array();
		if ( !$this->hasError() ) {
			$sResponse = json_decode( $this->getLastApiResponse()->getBody()->getContents(), $this->isDecodeAsArray() );
		}
		return $sResponse;
	}

	/**
	 * @return array
	 */
	protected function prepFinalRequestData() {
		$aFinal = array(
			'headers' => $this->getRequestHeaders()
		);

		$sDataBodyKey = ( $this->getHttpRequestMethod() == 'get' ) ? 'query' : 'json';
		$aFinal[ $sDataBodyKey ] = $this->getRequestDataFinal();

		return $aFinal;
	}

	/**
	 * @throws \Exception
	 */
	protected function preSendVerification() {
		if ( is_null( $this->getConnection() ) ) {
			throw new \Exception( 'Attempting to make API request without a Connection' );
		}
	}

	/**
	 * @return string
	 */
	abstract protected function getBaseUrl();

	/**
	 * @return Client
	 */
	protected function getHttpRequest() {
		if ( empty( $this->oHttp ) ) {
			$this->oHttp = new Client( array( 'base_url' => $this->getBaseUrl() ) );
		}
		return $this->oHttp;
	}

	/**
	 * @return string
	 */
	protected function getHttpRequestMethod() {
		$sMethod = static::REQUEST_METHOD;
		if ( !in_array( $sMethod, array( 'get', 'head', 'patch', 'post', 'put', 'delete' ) ) ) {
			$sMethod = 'get';
		}
		return strtolower( $sMethod );
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
	 * @return array
	 */
	public function getRequestData() {
		return $this->getArrayParam( 'reqdata' );
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
			array(
				'Accept'       => $this->getRequestContentType(),
				'Content-Type' => $this->getRequestContentType(),
			)
		);
	}

	/**
	 * @return bool
	 */
	public function getRequestContentType() {
		return $this->getStringParam( 'request_content_type', 'application/json' );
	}

	/**
	 * @return bool
	 */
	public function hasError() {
		return !is_null( $this->getLastError() );
	}

	/**
	 * @return bool
	 */
	public function isDecodeAsArray() {
		return (bool)$this->getParam( 'decode_response_as_array', true );
	}

	/**
	 * @param bool $bDecodeAsObject
	 * @return $this
	 */
	public function setDecodeAsArray( $bDecodeAsObject ) {
		return $this->setRawDataItem( 'decode_response_as_array', $bDecodeAsObject );
	}

	/**
	 * @param string $sType
	 * @return $this
	 */
	public function setRequestContentType( $sType ) {
		return $this->setRawDataItem( 'request_content_type', $sType );
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
	 * @param $aHeaders
	 * @return $this
	 */
	public function setRequestHeaders( $aHeaders ) {
		return $this->setRawDataItem( 'request_headers', $aHeaders );
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
	 * This is call right at the point of setting the data for the HTTP Request and should only
	 * ever be used for that purpose.  use getRequestData() otherwise.
	 * @return array
	 */
	public function getRequestDataFinal() {
		return $this->getRequestData();
	}
}