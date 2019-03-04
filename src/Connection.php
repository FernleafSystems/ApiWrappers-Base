<?php

namespace FernleafSystems\ApiWrappers\Base;

/**
 * Class Connection
 * @package FernleafSystems\ApiWrappers\Base
 * @property string api_key
 */
class Connection {

	use \FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

	/**
	 * @deprecated
	 * @var string
	 */
	protected $sApiKey;

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
