<?php
/**
 * Cheevos
 * Cheevos Special Page
 *
 * @package   Cheevos
 * @author    Hydra Wiki Platform Team
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos\Specials;

use Cheevos\AchievementService;
use Cheevos\CheevosAchievement;
use Cheevos\CheevosAchievementCategory;
use Cheevos\CheevosAchievementCriteria;
use Cheevos\CheevosException;
use Cheevos\CheevosHelper;
use Cheevos\Templates\TemplateManageAchievements;
use MediaWiki\User\UserIdentityLookup;
use MWException;
use OutputPage;
use PermissionsError;
use SpecialPage;
use WebRequest;
use Wikimedia\Assert\Assert;

class SpecialManageAchievements extends SpecialPage {
	private ?string $siteKey;
	private bool $isMaster;

	private TemplateManageAchievements $template;
	/**
	 * @var CheevosAchievement|false|mixed
	 */
	private mixed $achievement;

	public function __construct(
		private UserIdentityLookup $userIdentityLookup,
		private AchievementService $achievementService
	) {
		parent::__construct(
			'ManageAchievements',
			'achievement_admin',
			$this->getUser()->isAllowed( 'achievement_admin' )
		);

		$this->siteKey = CheevosHelper::getSiteKey();
		if ( empty( $this->siteKey ) ) {
			throw new MWException( 'Could not determined the site key for use for Achievements.' );
		}

		$this->isMaster = CheevosHelper::isCentralWiki();
		$this->template = new TemplateManageAchievements();
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$output = $this->getOutput();
		$output->addModuleStyles( [
			'ext.cheevos.styles',
			"ext.hydraCore.button.styles",
			'ext.hydraCore.pagination.styles',
			'mediawiki.ui.button',
			'mediawiki.ui.input'
		] );
		$output->addModules( [ 'ext.cheevos.js' ] );
		$this->setHeaders();

		if ( !$this->getUser()->isAllowed( 'edit_achievements' ) ) {
			throw new PermissionsError( 'edit_achievements' );
		}

		$request = $this->getRequest();

		switch ( $subPage ) {
			default:
			case 'view':
				$this->achievementsList( $output );
				return;
			case 'add':
			case 'edit':
			case 'admin':
				$this->achievementsForm( $output, $request );
				return;
			case 'delete':
			case 'restore':
				$this->achievementsDelete( $subPage, $output, $request );
				return;
			case 'revert':
				$this->achievementsRevert( $output, $request );
				return;
			case 'award':
				$this->awardForm( $output, $request );
				return;
			case 'invalidatecache':
				$this->invalidateCache( $output );
				return;
		}
	}

	/** Cheevos List */
	private function achievementsList( OutputPage $output ): void {
		$achievements = $this->achievementService->getAchievements( $this->siteKey );
		$categories = $this->achievementService->getCategories();

		if ( $this->isMaster ) {
			foreach ( $achievements as $i => $a ) {
				if ( $a->getSite_Key() !== $this->siteKey ) {
					unset( $achievements[$i] );
				}
			}
		}

		$revertHints = [];
		foreach ( $achievements as $achievement ) {
			if ( !$achievement->getParent_Id() || !isset( $achievements[$achievement->getParent_Id()] ) ) {
				continue;
			}
			if ( !$achievement->sameAs( $achievements[$achievement->getParent_Id()] ) ) {
				$revertHints[$achievement->getId()] = true;
			}
		}

		// Fix requires achievement child IDs for display purposes.
		$achievements = CheevosAchievement::correctCriteriaChildAchievements( $achievements );
		// Remove achievements that should not be shown in this context.
		[ $achievements, ] = CheevosAchievement::pruneAchievements( [ $achievements, [] ], true, false );

		$output->setPageTitle( $this->msg( 'manage_achievements' )->escaped() );
		$output->addHTML( $this->template->achievementsList( $achievements, $categories, $revertHints ) );
	}

	/** Achievements Form */
	private function achievementsForm( OutputPage $output, WebRequest $request ): void {
		$output->addModules( [ 'ext.achievements.triggerBuilder.js' ] );
		$achievementId = $request->getInt( 'aid' );

		$allAchievements = $this->achievementService->getAchievements( $this->siteKey );
		$allAchievements = CheevosAchievement::correctCriteriaChildAchievements( $allAchievements );
		[ $allAchievements, ] = CheevosAchievement::pruneAchievements( [ $allAchievements, [] ], false, true );

		if ( $achievementId ) {
			$this->achievement = false;
			if ( isset( $allAchievements[$achievementId] ) ) {
				$this->achievement = $allAchievements[$achievementId];
			}

			if ( $this->achievement === false || $achievementId != $this->achievement->getId() ) {
				$output->showErrorPage( 'achievements_error', 'error_bad_achievement_id' );
				return;
			}
			if (
				!CheevosHelper::isCentralWiki() &&
				( $this->achievement->isProtected() || $this->achievement->isGlobal() )
			) {
				$output->showErrorPage( 'achievements_error', 'error_achievement_protected_global' );
				return;
			}
		} else {
			$this->achievement = new CheevosAchievement();
		}

		$errors = $this->achievementsSave( $request );

		if ( $this->achievement->exists() ) {
			$output->setPageTitle(
				$this->msg( 'edit_achievement' )->escaped() .
				' - ' . $this->msg( 'manage_achievements' )->escaped() .
				' - ' . $this->achievement->getName()
			);
		} else {
			$output->setPageTitle(
				$this->msg( 'add_achievement' )->escaped() .
				' - ' . $this->msg( 'manage_achievements' )->escaped()
			);
		}

		$html = $this->template->achievementsForm(
			$this->achievement,
			$this->achievementService->getCategories(),
			$allAchievements,
			$errors
		);
		$output->addHTML( $html );
	}

	private function achievementsSave( WebRequest $request ): array {
		if ( $request->getVal( 'do' ) !== 'save' || !$request->wasPosted() ) {
			return [];
		}

		$errors = [];
		$forceCreate = false;
		if (
			!empty( $this->siteKey ) &&
			empty( $this->achievement->getSite_Key() ) &&
			$this->achievement->getId() > 0
		) {
			$forceCreate = true;
			$this->achievement->setParent_Id( $this->achievement->getId() );
			$this->achievement->setId( 0 );
		}
		$this->achievement->setSite_Key( $this->siteKey );

		$criteria = new CheevosAchievementCriteria( $this->achievement->getCriteria()->toArray() );
		$criteria->setStats( $request->getArray( "criteria_stats", [] ) );
		$criteria->setValue( $request->getInt( "criteria_value" ) );
		$criteria->setStreak( $request->getText( "criteria_streak" ) );
		$criteria->setStreak_Progress_Required( $request->getInt( "criteria_streak_progress_required" ) );
		$criteria->setStreak_Reset_To_Zero( $request->getBool( "criteria_streak_reset_to_zero" ) );
		if ( $this->isMaster ) {
			$criteria->setPer_Site_Progress_Maximum(
				$request->getInt( "criteria_per_site_progress_maximum" )
			);
		}
		$criteria->setDate_Range_Start( $request->getInt( "date_range_start" ) );
		$criteria->setDate_Range_End( $request->getInt( "date_range_end" ) );
		$criteria->setCategory_Id( $request->getInt( "criteria_category_id" ) );
		$criteria->setAchievement_Ids( $request->getIntArray( "criteria_achievement_ids", [] ) );
		$this->achievement->setCriteria( $criteria );

		$name = $request->getText( 'name' );
		if ( !$name || strlen( $name ) > 50 ) {
			$errors['name'] = $this->msg( 'error_invalid_achievement_name' )->escaped();
		} else {
			$this->achievement->setName( $name );
		}

		$description = $request->getText( 'description' );
		if ( !$description || strlen( $description ) > 150 ) {
			$errors['description'] = $this->msg( 'error_invalid_achievement_description' )->escaped();
		} else {
			$this->achievement->setDescription( $description );
		}

		$this->achievement->setImage( $request->getVal( 'image' ) );
		$this->achievement->setPoints( $request->getInt( 'points' ) );

		$categoryId = $request->getInt( 'category_id' );
		$categoryName = trim( $request->getText( 'category' ) );
		$category = $this->achievementService->getCategory( $categoryId );
		$categories = $this->achievementService->getCategories( true );
		if (
			$category !== false &&
			$categoryId > 0 &&
			$categoryId == $category->getId() &&
			$categoryName == $category->getName()
		) {
			$this->achievement->setCategory( $category );
		} elseif ( !empty( $categoryName ) ) {
			$found = false;
			foreach ( $categories as $_category ) {
				if ( $categoryName == $_category->getName() ) {
					$this->achievement->setCategory( $_category );
					$found = true;
					break;
				}
			}
			if ( !$found ) {
				$category = new CheevosAchievementCategory();
				$category->setName( $categoryName );
				$category->setCreated_At( time() );
				$category->setCreated_By( $this->getUser()->getId() );
				$return = $category->save();
				if ( isset( $return['object_id'] ) ) {
					$category = $this->achievementService->getCategory( $return['object_id'] );
					$this->achievement->setCategory( $category );
				} else {
					$category = false;
				}
			}
		} else {
			$category = false;
		}

		if ( $category === false ) {
			$errors['category'] = $this->msg( 'error_invalid_achievement_category' )->escaped();
		}

		$this->achievement->setSecret( $request->getBool( 'secret' ) );
		if ( $this->isMaster ) {
			// Set global to true should always happen after setting the site ID and site key,
			// Otherwise it could create a global achievement with a site ID and site key.
			$this->achievement->setGlobal( $request->getBool( 'global' ) );
			$this->achievement->setProtected( $request->getBool( 'protected' ) );
			$this->achievement->setSpecial( $request->getBool( 'special' ) );
			$this->achievement->setShow_On_All_Sites( $request->getBool( 'show_on_all_sites' ) );
		}

		if ( !count( $errors ) ) {
			$this->achievement->save( $forceCreate );

			$this->invalidateCache( $this->getOutput() );
			return [];
		}
		return $errors;
	}

	public function achievementsRevert( OutputPage $output, WebRequest $request ): void {
		$achievementId = $request->getInt( 'aid' );

		if ( $achievementId ) {
			$achievement = $this->achievementService->getAchievement( $achievementId );

			if ( $achievement === false || $achievementId != $achievement->getId() ) {
				$output->showErrorPage( 'achievements_error', 'error_bad_achievement_id' );
				return;
			}
		}

		if ( $achievement->isDeleted() && !$this->getUser()->isAllowed( 'restore_achievements' ) ) {
			throw new PermissionsError( 'restore_achievements' );
		}

		if ( !$achievement->getParent_Id() ) {
			$output->showErrorPage( 'achievements_error', 'error_achievement_unrevertable' );
			return;
		}

		$parentAch = $this->achievementService->getAchievement( $achievement->getParent_Id() );

		if ( $parentAch === false || $achievement->getParent_Id() != $parentAch->getId() ) {
			$output->showErrorPage( 'achievements_error', 'error_bad_achievement_parent_id' );
			return;
		}

		if ( $request->getVal( 'confirm' ) == 'true' && $request->wasPosted() ) {
			if ( $this->getUser()->isAnon() ) {
				throw new MWException(
					'Could not obtain the global ID for the user attempting to revert an achievement.'
				);
			}

			foreach (
				[ 'name',
				'description',
				'image',
				'category',
				'points',
				'global',
				'protected',
				'secret',
				'special',
				'show_on_all_sites',
				'created_at',
				'updated_at',
				'deleted_at',
				'created_by',
				'updated_by',
				'deleted_by',
				'criteria' ] as $field ) {
				$achievement[$field] = $parentAch[$field];
			}

			$achievement->save();

			$this->invalidateCache( $output );
			return;
		}

		$output->setPageTitle(
			$this->msg( 'revert_achievement_title' )->escaped() . ' - ' . $achievement->getName()
		);
		$output->addHTML( $this->template->achievementStateChange( $achievement, 'revert' ) );
	}

	/**
	 * Achievements Delete/Restore
	 *
	 * @param string $action Delete or Restore action take.
	 *
	 * @return void	[Outputs to screen]
	 */
	public function achievementsDelete( string $action, OutputPage $output, WebRequest $request ): void {
		$user = $this->getUser();
		Assert::precondition(
			in_array( $action, [ 'delete', 'restore' ] ),
			'$action had to be one of delete, restore'
		);

		if ( $action === 'delete' && !$user->isAllowed( 'delete_achievements' ) ) {
			throw new PermissionsError( 'delete_achievements' );
		}

		if ( $action === 'restore' && !$user->isAllowed( 'restore_achievements' ) ) {
			throw new PermissionsError( 'restore_achievements' );
		}

		$achievementId = $request->getInt( 'aid' );

		if ( $achievementId ) {
			$achievement = $this->achievementService->getAchievement( $achievementId );

			if ( $achievement === false || $achievementId != $achievement->getId() ) {
				$output->showErrorPage( 'achievements_error', 'error_bad_achievement_id' );
				return;
			}
		}

		if ( $request->getVal( 'confirm' ) == 'true' && $request->wasPosted() ) {
			$forceCreate = false;
			if ( !$achievement->getParent_Id() && !$this->isMaster ) {
				$forceCreate = true;
				$achievement->setParent_Id( $achievement->getId() );
				$achievement->setId( 0 );
			}
			$achievement->setSite_Key( $this->siteKey );
			$achievement->setDeleted_At( $action === 'restore' ? 0 : time() );
			$achievement->setDeleted_By( $action === 'restore' ? 0 : $user->getId() );

			$achievement->save( $forceCreate );

			$this->invalidateCache( $output );
			return;
		}

		$output->setPageTitle(
			$this->msg( $action . '_achievement_title' )->escaped() . ' - ' . $achievement->getName()
		);
		$output->addHTML( $this->template->achievementStateChange( $achievement, $action ) );
	}

	public function awardForm( OutputPage $output, WebRequest $request ): void {
		if ( !$this->getUser()->isAllowed( 'award_achievements' ) ) {
			throw new PermissionsError( 'award_achievements' );
		}
		$this->checkPermissions();

		$return = $this->awardSave( $request );

		// Using the 'master' site key for the awarding form.
		[ $allAchievements, ] = CheevosAchievement::pruneAchievements(
			[ $this->achievementService->getAchievements( $this->siteKey ), [] ]
		);

		$output->setPageTitle( $this->msg( 'awardachievement' )->escaped() );
		$output->addHTML( $this->template->awardForm( $return, $allAchievements ) );
	}

	/**
	 * Saves submitted award forms.
	 *
	 * @return array Array containing an array of processed form information and array of corresponding errors.
	 */
	private function awardSave( WebRequest $request ): array {
		// This will break logic below if "Award" and "Unaward" are ever localized.  --Alexia 2017-04-07
		$do = strtolower( $request->getText( 'do', '' ) );
		if ( !in_array( $do, [ 'award', 'unaward' ] ) || !$request->wasPosted() ) {
			return [ 'save' => [], 'errors' => [], 'success' => null ];
		}

		$errors = [];
		$username = $request->getVal( 'username' );
		if ( empty( $username ) ) {
			$errors[] = [
				'username' => $username,
				'message' => $this->msg( 'error_award_bad_user' )->escaped()
			];
		}

		$achievementId = $request->getInt( 'achievement_id' );
		$achievement = $this->achievementService->getAchievement( $achievementId );
		if ( $achievement === false ) {
			$errors[] = [
				'username' => $username,
				'message' => $this->msg( 'error_award_bad_achievement' )->escaped()
			];
		}

		$save = [ 'username' => $username, 'achievement_id' => $achievementId ];
		if ( count( $errors ) ) {
			return [ 'save' => $save, 'errors' => $errors, 'success' => false ];
		}

		$awarded = [];
		foreach ( explode( ',', $username ) as $getUser ) {
			$userIdentity = $this->userIdentityLookup->getUserIdentityByName( trim( $getUser ) );
			if ( !$userIdentity || !$userIdentity->isRegistered() ) {
				$errors[] = [
					'username' => $getUser,
					'message' => $this->msg( 'error_award_bad_user' )->escaped()
				];
				continue;
			}

			$globalId = $userIdentity->getId();
			$award = [];

			$currentProgress = $this->achievementService->getAchievementProgress(
				[
					'user_id' => $userIdentity->getId(),
					'achievement_id' => $achievement->getId(),
					'site_key' => $this->siteKey
				]
			);

			$currentProgress = is_array( $currentProgress ) ? array_pop( $currentProgress ) : null;

			if ( !$currentProgress && $do === 'award' ) {
				try {
					$award = $this->achievementService->putProgress(
						[
							'achievement_id' => $achievement->getId(),
							'site_key' => ( !$achievement->isGlobal() ? $this->siteKey : '' ),
							'user_id' => $globalId,
							'earned' => true,
							'manual_award' => true,
							'awarded_at' => time(),
							'notified' => false
						]
					);
					$this->achievementService->broadcastAchievement( $achievement, $this->siteKey, $globalId );
				} catch ( CheevosException $e ) {
					$errors[] = [
						'username' => $username,
						'message' => "There was an API failure attempting to putProgress: " . $e->getMessage()
					];
				}
			} elseif ( $do === 'award' ) {
				$award = [ 'message' => 'nochange' ];
			}

			if ( $currentProgress !== null && $currentProgress->getId() && $do === 'unaward' ) {
				try {
					$award = $this->achievementService->deleteProgress( $currentProgress->getId() );
				} catch ( CheevosException $e ) {
					$errors[] = [
						'username' => $username,
						'message' => "There was an API failure attempting to deleteProgress: {$e->getMessage()}"
					];
				}
			} elseif ( $do === 'unaward' ) {
				$award = [ 'message' => 'nochange' ];
			}

			$award['username'] = $userIdentity->getName();
			$awarded[] = $award;
		}

		return [ 'save' => $save, 'errors' => $errors, 'success' => $awarded ];
	}

	/** Invalidates the cache and redirects to ManageAchievements */
	private function invalidateCache( OutputPage $outputPage ): void {
		$this->achievementService->invalidateCache();
		$outputPage->redirect( SpecialPage::getSafeTitleFor( 'ManageAchievements' )->getFullURL() );
	}

	/** @inheritDoc */
	public function isListed() {
		return $this->getUser()->isAllowed( 'achievement_admin' );
	}

	/** @inheritDoc */
	public function isRestricted() {
		return true;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'users';
	}
}
