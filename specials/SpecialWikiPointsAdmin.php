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
			case 'revoke':
				$this->revokePoints();
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

		$user = new User;
		$form = [];
		$globalId = false;

		$form['username'] = $this->wgRequest->getVal('user_name');
		$form['user_id'] = $this->wgRequest->getInt('user_id');

		if (!empty($form['username'])) {
			$form['username'] = User::getCanonicalName($form['username'], 'valid');
			if ($form['username'] !== false) {
				$user = User::newFromName($form['username']);
			}
		} elseif ($form['user_id'] > 0) {
			$user = User::newFromId($form['user_id']);
		}

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

		$this->content = TemplateWikiPointsAdmin::lookup($user, $pointsLog, $form);
	}

	/**
	 * Adjust points by an arbitrary integer amount.
	 *
	 * @access	private
	 * @return	void	[Outputs to screen]
	 */
	private function adjustPoints() {
		$amount = $this->wgRequest->getInt('amount');
		$user_id = $this->wgRequest->getInt('user_id');
		if ($amount && $user_id) {
			$credit = EditPoints::arbitraryCredit($user_id, $amount);
			$credit->save();
			$credit->updatePointTotals();
		}

		$userEscaped = urlencode($this->wgRequest->getVal('user_name'));
		$page = Title::newFromText('Special:WikiPointsAdmin');
		$this->output->redirect($page->getFullURL()."/Special:WikiPointsAdmin?action=lookup&pointsAdjusted=1&user_name={$userEscaped}");
	}

	/**
	 * Revoke points for selected edits.
	 *
	 * @access	private
	 * @return	void	[Outputs to screen]
	 */
	private function revokePoints() {
		$revokeList = [];
		$unrevokeList = [];
		$edit_ids = $this->wgRequest->getArray('revoke_list');
		foreach ($edit_ids as $id => $revoke) {
			if ($revoke) {
				$revokeList[] = intval($id);
			} else {
				$unrevokeList[] = intval($id);
			}
		}
		if (count($revokeList)) {
			EditPoints::revoke($revokeList);
		}
		if (count($unrevokeList)) {
			EditPoints::unrevoke($unrevokeList);
		}

		$userEscaped = urlencode($this->wgRequest->getVal('user_name'));
		$page = Title::newFromText('Special:WikiPointsAdmin');
		$this->output->redirect($page->getFullURL()."?action=lookup&user_name={$userEscaped}");
	}

	/**
	 * Retotal points this wiki.
	 *
	 * @access	private
	 * @return	void
	 */
	private function retotalPoints() {
		$status = false;
		if ($this->wgRequest->wasPosted()) {
			$userId = $this->wgRequest->getInt('user_id');
			if ($userId) {
				$status = EditPoints::retotalUser($userId);
			}
		}

		$userEscaped = urlencode($this->wgRequest->getVal('user_name'));
		$page = Title::newFromText('Special:WikiPointsAdmin');
		$this->output->redirect($page->getFullURL("action=lookup&pointsRetotaled=".intval($status)."&user_name={$userEscaped}"));
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
