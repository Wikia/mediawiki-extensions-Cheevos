<?php

namespace Cheevos;


class CheevosStatDelta extends CheevosModel
{
	/**
	 * Constructor
	 * @param mixed[] $data Associated array of property values initializing the model
	 */
	public function __construct(array $data = null)
	{
		$this->container['stat'] = isset($data['stat']) ? $data['stat'] : null;
		$this->container['delta'] = isset($data['delta']) ? $data['delta'] : null;
	}
}
