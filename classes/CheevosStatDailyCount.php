<?php
/**
 * Cheevos
 * Cheevos Stat Daily Count Model
 *
 * @package   Cheevos
 * @author    Alexia E. Smith
 * @copyright (c) 2018 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos;

class CheevosStatDailyCount extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @param array|null $data Associated array of property values initializing the model.
	 */
	public function __construct( array $data = null ) {
		$this->container['count'] = isset( $data['count'] ) && is_int( $data['count'] ) ? $data['count'] : 0;
		$this->container['day'] = isset( $data['day'] ) && is_int( $data['day'] ) ? $data['day'] : 0;
		$this->container['site_id'] = isset( $data['site_id'] ) &&
									  is_int( $data['site_id'] ) ? $data['site_id'] : 0;
		$this->container['site_key'] = isset( $data['site_key'] ) &&
									   is_string( $data['site_key'] ) ? $data['site_key'] : '';
		$this->container['stat'] = isset( $data['stat'] ) && is_string( $data['stat'] ) ? $data['stat'] : '';
		$this->container['stat_id'] = isset( $data['stat_id'] ) && is_int( $data['stat_id'] ) ? $data['stat_id'] : 0;
	}
}
