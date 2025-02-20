<?php
/**
 * Cheevos
 * Cheevos Achievement Criteria Model
 *
 * @package   Cheevos
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos;

/**
 * @method null setStreak_Progress_Required( int $getInt )
 * @method null setStreak_Reset_To_Zero( bool $getBool )
 * @method null setStreak( string $getText )
 * @method null setValue( int $getInt )
 * @method null setStats( array|null $getArray )
 * @method null setPer_Site_Progress_Maximum( int $getInt )
 * @method null setDate_Range_Start( int $getInt )
 * @method null setDate_Range_End( int $getInt )
 * @method null setCategory_Id( int $getInt )
 * @method null setAchievement_Ids( int[]|null $getIntArray )
 */
class CheevosAchievementCriteria extends CheevosModel {

	private const FIELDS = [
		'stats',
		'value',
		'streak',
		'streak_progress_required',
		'streak_reset_to_zero',
		'per_site_progress_maximum',
		'date_range_start',
		'date_range_end',
		'category_id',
		'achievement_ids'
	];

	/**
	 * Constructor
	 *
	 * @param array|null $data Associated array of property values initializing the model.
	 */
	public function __construct( ?array $data = null ) {
		$this->container['stats'] = isset( $data['stats'] ) && is_array( $data['stats'] ) ? $data['stats'] : [];
		$this->container['value'] = isset( $data['value'] ) && is_int( $data['value'] ) ? $data['value'] : 0;
		$this->container['streak'] = isset( $data['streak'] ) && is_string( $data['streak'] ) ? $data['streak'] : '';
		$this->container['streak_progress_required'] = isset( $data['streak_progress_required'] ) &&
													   is_int( $data['streak_progress_required'] ) ?
															$data['streak_progress_required'] :
															0;
		$this->container['streak_reset_to_zero'] =
			isset( $data['streak_reset_to_zero'] ) && is_bool( $data['streak_reset_to_zero'] ) &&
			$data['streak_reset_to_zero'];
		$this->container['per_site_progress_maximum'] = isset( $data['per_site_progress_maximum'] ) &&
														is_int( $data['per_site_progress_maximum'] ) ?
															$data['per_site_progress_maximum'] :
															0;
		$this->container['date_range_start'] = isset( $data['date_range_start'] ) &&
											   is_int( $data['date_range_start'] ) ? $data['date_range_start'] : 0;
		$this->container['date_range_end'] = isset( $data['date_range_end'] ) &&
											 is_int( $data['date_range_end'] ) ? $data['date_range_end'] : 0;
		$this->container['category_id'] = isset( $data['category_id'] ) &&
										  is_int( $data['category_id'] ) ? $data['category_id'] : 0;
		$this->container['achievement_ids'] = isset( $data['achievement_ids'] ) &&
											  is_array( $data['achievement_ids'] ) ? $data['achievement_ids'] : [];
	}

	/**
	 * Do these criteria roughly equal another criteria?
	 */
	public function sameAs( CheevosModel $model ): bool {
		foreach ( self::FIELDS as $field ) {
			if ( $this->container[$field] instanceof CheevosModel ) {
				if ( !$this->container[$field]->sameAs( $model->container[$field] ) ) {
					return false;
				}
				continue;
			}
			if ( $this->container[$field] !== $model[$field] ) {
				return false;
			}
		}
		return true;
	}
}
