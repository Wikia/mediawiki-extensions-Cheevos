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
 **/

class SpecialAchievements extends SpecialPage {
	/**
	 * Output HTML
	 *
	 * @var string
	 */
	private $content;

	/**
	 * Main Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		global $dsSiteKey;
		parent::__construct('Achievements');

		$this->wgRequest	= $this->getRequest();
		$this->output		= $this->getOutput();
		$this->siteKey		= $dsSiteKey;
	}

	/**
	 * Main Executor
	 *
	 * @param  string	Sub page passed in the URL.
	 *
	 * @return void	[Outputs to screen]
	 */
	public function execute($subpage) {
		$this->templates = new TemplateAchievements;
		$this->output->addModuleStyles(['ext.cheevos.styles']);
		$this->output->addModules(['ext.cheevos.js']);
		$this->setHeaders();
		$this->achievementsList($subpage);
		$this->output->addHTML($this->content);
	}

	/**
	 * Achievements List
	 *
	 * @param  mixed	Passed subpage parameter to be intval()'ed for a Global ID.
	 *
	 * @return void	[Outputs to screen]
	 */
	public function achievementsList($subpage = null) {
		global $dsSiteKey;

		$lookup = CentralIdLookup::factory();

		$globalId = false;
		if ($this->getUser()->isLoggedIn()) {
			if ($this->getUser()->getId() > 0) {
				// This is unrelated to the user look up.  Just trigger this statistic if a logged in user visits an achievement page.
				CheevosHooks::increment('achievement_engagement', 1, $this->getUser());
			}

			$globalId = $lookup->centralIdFromLocalUser($this->getUser(), CentralIdLookup::AUDIENCE_RAW);
			$user = $this->getUser();
		}

		if (!empty($subpage) && !is_numeric($subpage)) {
			$lookupUser = User::newFromName($subpage);
			if ($lookupUser && $lookupUser->getId()) {
				$user = $lookupUser;
				$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
			}
			if ($globalId < 1 || !$lookupUser->getId()) {
				throw new ErrorPageError('achievements', 'no_user_to_display_achievements');
			}
		}
		if (intval($subpage) > 0) {
			$globalId = intval($subpage);
			$user = $lookup->localUserFromCentralId($globalId);
			if ($globalId < 1 || $user === null) {
				throw new ErrorPageError('achievements', 'no_user_to_display_achievements');
			}
		}

		if ($globalId < 1 || $user === null) {
			throw new UserNotLoggedIn('login_to_display_achievements', 'achievements');
		}

		try {
			$check = \Cheevos\Cheevos::checkUnnotified($globalId, $this->siteKey, true); // Just a helper to fix cases of missed achievements.
			if (isset($check['earned'])) {
				foreach ($check['earned'] as $earned) {
					$earnedAchievement = new \Cheevos\CheevosAchievement($earned);
					\CheevosHooks::displayAchievement($earnedAchievement, $this->siteKey, $globalId);
					Hooks::run('AchievementAwarded', [$earnedAchievement, $globalId]);
				}
			}
			$_statuses = \Cheevos\Cheevos::getAchievementStatus($globalId, $this->siteKey);
			$achievements = \Cheevos\Cheevos::getAchievements($dsSiteKey);
		} catch (\Cheevos\CheevosException $e) {
			throw new ErrorPageError('achievements', 'error_cheevos_service', [$e->getMessage()]);
		}

		$categories = [];
		if (!empty($achievements)) {
			foreach ($achievements as $aid => $achievement) {
				if (!array_key_exists($achievement->getCategory()->getId(), $categories)) {
					$categories[$achievement->getCategory()->getId()] = $achievement->getCategory();
				}
			}
		}

		if ($user) {
			$title = wfMessage('achievements-title-for', $user->getName())->escaped();
		} else {
			$title = wfMessage('achievements-title')->escaped();
		}

		// Fix requires achievement child IDs for display purposes.
		$achievements = \Cheevos\CheevosAchievement::correctCriteriaChildAchievements($achievements);
		// Remove achievements that should not be shown in this context.
		list($achievements, $_statuses) = \Cheevos\CheevosAchievement::pruneAchievements([$achievements, $_statuses], true, true);

		// @TODO: This fuckery of the $statuses array is backwards compatibility for the template.  If we fix the template to be able to handle more than one wiki at a time this piece of code needs to be removed.
		if (!empty($_statuses)) {
			foreach ($_statuses as $_status) {
				$statuses[$_status->getAchievement_Id()] = $_status;
			}
		}

		$this->output->setPageTitle($title);
		$this->content = $this->templates->achievementsList($achievements, $categories, $statuses, $user, $globalId);
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'users';
	}
}
