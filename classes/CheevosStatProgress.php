<?php
/**
 * Cheevos
 * Cheevos Stat Progress Model
 *
 * @package   Cheevos
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos;

class CheevosStatProgress extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @param array|null $data Associated array of property values initializing the model.
	 *
	 * @return void
	 */
	public function __construct( array $data = null ) {
		$this->container['stat'] = isset( $data['stat'] ) && is_string( $data['stat'] ) ? $data['stat'] : '';
		$this->container['stat_id'] = isset( $data['stat_id'] ) && is_int( $data['stat_id'] ) ? $data['stat_id'] : 0;
		$this->container['user_id'] = isset( $data['user_id'] ) && is_int( $data['user_id'] ) ? $data['user_id'] : 0;
		$this->container['site_id'] = isset( $data['site_id'] ) && is_int( $data['site_id'] ) ? $data['site_id'] : 0;
		$this->container['site_key'] = isset( $data['site_key'] ) && is_string( $data['site_key'] ) ? $data['site_key'] : '';
		$this->container['streak_achievement_id'] = isset( $data['streak_achievement_id'] ) && is_int( $data['streak_achievement_id'] ) ? $data['streak_achievement_id'] : 0;
		$this->container['count'] = isset( $data['count'] ) && is_int( $data['count'] ) ? $data['count'] : 0;
		$this->container['last_incremented'] = isset( $data['last_incremented'] ) && is_int( $data['last_incremented'] ) ? $data['last_incremented'] : 0;
	}
}
