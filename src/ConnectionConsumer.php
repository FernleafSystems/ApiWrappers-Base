<?php declare( strict_types=1 );

namespace FernleafSystems\ApiWrappers\Base;

trait ConnectionConsumer {

	/**
	 * @var Connection
	 */
	private ?Connection $apiConnection = null;

	public function getConnection() :Connection {
		return empty( $this->apiConnection ) ? $this->getDefaultConnection() : $this->apiConnection;
	}

	public function getDefaultConnection() :?Connection {
		return null;
	}

	public function setConnection( Connection $conn ) :self {
		$this->apiConnection = $conn;
		return $this;
	}
}