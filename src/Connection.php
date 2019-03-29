<?php

namespace FernleafSystems\ApiWrappers\Base;

/**
 * Class Connection
 * @package FernleafSystems\ApiWrappers\Base
 * @property string $account_id
 * @property string $api_key
 * @property string $override_api_url
 * @property string $override_api_version
 */
abstract class Connection {

	use \FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;
	const API_URL = '';
	const API_VERSION = '1';

	/**
	 * @deprecated
	 * @var string
	 */
	protected $sApiKey;

	/**
	 * @return string
	 */
	public function getBaseUrl() {
		return sprintf( static::API_URL, $this->getApiVersion() );
	}

	/**
	 * @return string
	 */
	public function getApiUrl() {
		return isset( $this->override_api_url ) ? $this->override_api_url : static::API_URL;
	}

	/**
	 * @return string
	 */
	public function getApiVersion() {
		return isset( $this->override_api_version ) ? $this->override_api_version : static::API_VERSION;
	}

	/**
	 * @deprecated
	 * @return string
	 */
	public function getApiKey() {
		return isset( $this->api_key ) ? $this->api_key : $this->sApiKey;
	}

	/**
	 * @return bool
	 */
	public function hasApiKey() {
		return !empty( $this->api_key ) || !empty( $this->sApiKey );
	}

	/**
	 * @param string $sApiKey
	 * @return $this
	 */
	public function setApiKey( $sApiKey ) {
		$this->api_key = $sApiKey;
		$this->sApiKey = $sApiKey;
		return $this;
	}
}
