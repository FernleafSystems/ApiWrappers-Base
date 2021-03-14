<?php declare( strict_types=1 );

namespace FernleafSystems\ApiWrappers\Base;

/**
 * Class Connection
 * @package FernleafSystems\ApiWrappers\Base
 * @property string $account_id
 * @property string $api_key
 * @property string $override_api_url
 * @property string $override_api_version
 */
abstract class Connection extends \FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass {

	const API_URL = '';
	const API_VERSION = '1';

	public function getBaseUrl() :string {
		return sprintf( $this->getApiUrl(), $this->getApiVersion() );
	}

	public function getApiUrl() :string {
		return $this->override_api_url ?? static::API_URL;
	}

	public function getApiVersion() :string {
		return $this->override_api_version ?? static::API_VERSION;
	}

	public function hasApiKey() :bool {
		return !empty( $this->api_key );
	}

	public function setApiKey( string $key ) :self {
		$this->api_key = $key;
		return $this;
	}
}
