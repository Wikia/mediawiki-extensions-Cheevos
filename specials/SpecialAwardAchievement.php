<?php
/**
 * Cheevos
 * Award Achievement Special Page
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

class SpecialAwardAchievement extends SpecialPage {
	/**
	 * Output HTML
	 *
	 * @var		string
	 */
	private $content;

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct('AwardAchievement', 'award_achievements', $this->getUser()->isAllowed('award_achievements'));

		$this->wgRequest	= $this->getRequest();
		$this->output		= $this->getOutput();
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @param	string	Sub page passed in the URL.
	 * @return	void	[Outputs to screen]
	 */
	public function execute($subpage) {
		$this->templates = new TemplateAwardAchievement;

		$this->output->addModules(['ext.achievements.styles', 'ext.achievements.js']);

		$this->setHeaders();

		if (CheevosHooks::inMaintenance()) {
			$this->output->showErrorPage('achievements_error', 'error_maintenance_mode');
			return;
		}

		$this->awardForm();

		$this->output->addHTML($this->content);
	}

	/**
	 * Award Form
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function awardForm() {
		$this->checkPermissions();

		$return = $this->awardSave();

		$this->output->setPageTitle(wfMessage('awardachievement')->escaped());
		$this->content = $this->templates->awardForm($return, \Cheevos\Achievement::getAll(), \Cheevos\MegaAchievement::getAll(true));
	}

	/**
	 * Saves submitted award forms.
	 *
	 * @access	private
	 * @return	array	Array containing an array of processed form information and array of corresponding errors.
	 */
	private function awardSave() {
		$do = strtolower($this->wgRequest->getVal('do'));
		$save = [];
		$errors = [];
		$awarded = null;
		if (($do == 'award' || $do == 'unaward') && $this->wgRequest->wasPosted()) {
			$awarded = false;
			$save['username'] = $this->wgRequest->getVal('username');
			if (empty($save['username'])) {
				$errors['username'] = wfMessage('error_award_bad_user')->escaped();
			} else {
				$user = User::newFromName($save['username']);
				$user->load();
				$lookup = CentralIdLookup::factory();
				$globalId = $lookup->centralIdFromLocalUser($user, CentralIdLookup::AUDIENCE_RAW);
				if (!$user || !$user->getId() || !$globalId) {
					$errors['username'] = wfMessage('error_award_bad_user')->escaped();
				}
			}

			if ($this->wgRequest->getVal('type') != 'mega') {
				$save['achievement_id'] = $this->wgRequest->getInt('achievement_id');
				$achievement = \Cheevos\Achievement::newFromId($save['achievement_id']);
				if ($achievement === false) {
					$errors['achievement_id'] = wfMessage('error_award_bad_achievement')->escaped();
				}
			} else {
				$save['achievement_id'] = $this->wgRequest->getInt('achievement_id');
				$achievement = \Cheevos\MegaAchievement::newFromId($save['achievement_id']);
				if ($achievement === false) {
					$errors['achievement_id'] = wfMessage('error_award_bad_achievement')->escaped();
				}
			}

			if (!count($errors)) {
				if ($do == 'award') {
					$awarded = $achievement->award($user, $achievement->getIncrement());
				} elseif ($do == 'unaward') {
					$awarded = $achievement->unaward($user, true);
				}
			}
			if (!$awarded && $this->wgRequest->getVal('type') == 'mega') {
				$errors['mega_service_error'] = $achievement->getLastError();
			}
		}
		return [
			'save'		=> $save,
			'errors'	=> $errors,
			'success'	=> $awarded
		];
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @access protected
	 * @return string
	 */
	protected function getGroupName() {
		return 'users';
	}
}
