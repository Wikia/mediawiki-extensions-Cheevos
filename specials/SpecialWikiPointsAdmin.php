<?php
/**
 * Curse Inc.
 * Wiki Points
 * A contributor scoring system
 *
 * @author		Noah Manneschmidt
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		Wiki Points
 * @link		http://www.curse.com/
 *
**/

class SpecialWikiPointsAdmin extends HydraCore\SpecialPage {
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
		parent::__construct('WikiPointsAdmin', 'wiki_points_admin');
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @param	string	Sub page passed in the URL.
	 * @return	void	[Outputs to screen]
	 */
	public function execute($subpage) {
		$this->checkPermissions();

		$this->output->addModules(['ext.cheevos.wikiPoints', 'mediawiki.ui', 'mediawiki.ui.input', 'mediawiki.ui.button']);

		$this->setHeaders();

		switch ($this->wgRequest->getVal('action')) {
			default:
			case 'lookup':
				$this->lookUpUser();
				break;
			case 'adjust':
				$this->adjustPoints();
				break;
		}

		$this->output->addHTML($this->content);
	}

	/**
	 * Shows points only from the searched user, if found.
	 *
	 * @access	private
	 * @return	void	[Outputs to screen]
	 */
	private function lookUpUser() {
		global $dsSiteKey;

		$user = null;
		$pointsLog = [];
		$form = [];
		$globalId = false;

		$form['username'] = $this->wgRequest->getVal('user_name');

		if (!empty($form['username'])) {
			$user = User::newFromName($form['username']);

			if ($user->getId()) {
				$lookup = \CentralIdLookup::factory();
				$globalId = $lookup->centralIdFromLocalUser($user);
			}

			$pointsLog = [];
			if ($globalId > 0) {
				try {
					$pointsLog = \Cheevos\Cheevos::getWikiPointLog(['user_id' => $globalId, 'site_key' => $dsSiteKey, 'limit' => 100]);
				} catch (\Cheevos\CheevosException $e) {
					throw new \ErrorPageError(wfMessage('cheevos_api_error_title'), wfMessage('cheevos_api_error', $e->getMessage()));
				}
				if (empty($form['username'])) {
					$form['username'] = $user->getName();
				}
			} elseif ($globalId === false) {
				$form['error'] = wfMessage('error_wikipoints_admin_user_not_found')->escaped();
			}
		}

		$this->output->setPageTitle(($user ? wfMessage('wiki_points_admin_lookup', $user->getName()) : wfMessage('wikipointsadmin')));
		$this->content = TemplateWikiPointsAdmin::lookup($user, $pointsLog, $form);
	}

	/**
	 * Adjust points by an arbitrary integer amount.
	 *
	 * @access	private
	 * @return	void	[Outputs to screen]
	 */
	private function adjustPoints() {
		$page = Title::newFromText('Special:WikiPointsAdmin');
		$userName = $this->wgRequest->getVal('user_name');
		if ($this->wgRequest->wasPosted()) {
			$amount = $this->wgRequest->getInt('amount');
			$user = User::newFromName($userName);

			if ($amount && $user->getId()) {
				CheevosHooks::increment('wiki_points', intval($amount), $user);
			}

			$userEscaped = urlencode($this->wgRequest->getVal('user_name'));
			$this->output->redirect($page->getFullURL(['action' => 'lookup', 'user_name' => $userName, 'pointsAdjusted' => 1]));
		} else {
			$this->output->redirect($page->getFullURL(['action' => 'lookup', 'user_name' => $userName]));
		}
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @access	protected
	 * @return	string
	 */
	protected function getGroupName() {
		return 'wikipoints';
	}
}
