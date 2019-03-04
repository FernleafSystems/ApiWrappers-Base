<?php

namespace FernleafSystems\ApiWrappers\Base;

/**
 * Class Connection
 * @package FernleafSystems\ApiWrappers\Base
 * @property string $account_id
 * @property string $api_key
 * @property string $api_version_override
 */
class Connection {

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
		return static::API_URL;
	}

	/**
	 * @return string
	 */
	public function getApiVersion() {
		return empty( $this->api_version_override ) ? static::API_VERSION : $this->api_version_override;
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
