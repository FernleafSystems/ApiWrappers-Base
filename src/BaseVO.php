<?php

namespace FernleafSystems\ApiWrappers\Base;

/**
 * Class BaseVO
 * @package FernleafSystems\ApiWrappers\Base
 */
class BaseVO {

	use \FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

	/**
	 * @return bool
	 */
	public function isValid() {
		return count( $this->getRawDataAsArray() ) > 0;
	}
}