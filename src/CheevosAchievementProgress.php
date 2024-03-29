<?php
/**
 * Cheevos
 * Cheevos Achievement Progress Model
 *
 * @package   Cheevos
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos;

class CheevosAchievementProgress extends CheevosModel {
	/**
	 * Constructor
	 *
	 * @param array|null $data Associated array of property values initializing the model.
	 *
	 * @return void
	 */
	public function __construct( array $data = null ) {
		$this->container['id'] = isset( $data['id'] ) && is_int( $data['id'] ) ? $data['id'] : 0;
		$this->container['achievement_id'] = isset( $data['achievement_id'] ) &&
											 is_int( $data['achievement_id'] ) ? $data['achievement_id'] : 0;
		$this->container['user_id'] = isset( $data['user_id'] ) && is_int( $data['user_id'] ) ? $data['user_id'] : 0;
		$this->container['site_id'] = isset( $data['site_id'] ) && is_int( $data['site_id'] ) ? $data['site_id'] : 0;
		$this->container['site_key'] = isset( $data['site_key'] ) &&
									   is_string( $data['site_key'] ) ? $data['site_key'] : '';
		$this->container['earned'] = isset( $data['earned'] ) && is_bool( $data['earned'] ) && $data['earned'];
		$this->container['manual_award'] =
			isset( $data['manual_award'] ) && is_bool( $data['manual_award'] ) && $data['manual_award'];
		$this->container['awarded_at'] = isset( $data['awarded_at'] ) &&
										 is_int( $data['awarded_at'] ) ? $data['awarded_at'] : 0;
		$this->container['notified'] = isset( $data['notified'] ) && is_bool( $data['notified'] ) && $data['notified'];
	}

	/**
	 * Copy the progress from another to this one.
	 * Typically used for copying progress from a parent into the child for display purposes.
	 *
	 * @return void
	 */
	public function copyFrom( CheevosAchievementProgress $progress ) {
		$data = $progress->toArray();
		$data['id'] = $this->container['id'];
		$data['achievement_id'] = $this->container['achievement_id'];
		$this->container = $data;
	}
}
