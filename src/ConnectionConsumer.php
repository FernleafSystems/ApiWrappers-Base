<?php

namespace FernleafSystems\ApiWrappers\Base;

/**
 * Trait ConnectionConsumer
 * @package FernleafSystems\ApiWrappers\Base
 */
trait ConnectionConsumer {

	/**
	 * @var Connection
	 */
	private $oConnection;

	/**
	 * @return Connection|null
	 */
	public function getConnection() {
		return empty( $this->oConnection ) ? $this->getDefaultConnection() : $this->oConnection;
	}

	/**
	 * @return Connection|null
	 */
	public function getDefaultConnection() {
		return null;
	}

	/**
	 * @param Connection $oConnection
	 * @return $this
	 */
	public function setConnection( $oConnection ) {
		$this->oConnection = $oConnection;
		return $this;
	}
}