<?php declare( strict_types=1 );

namespace FernleafSystems\ApiWrappers\Base;

class BaseVO extends \FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass {

	public function isValid() :bool {
		return count( $this->getRawData() ) > 0;
	}
}