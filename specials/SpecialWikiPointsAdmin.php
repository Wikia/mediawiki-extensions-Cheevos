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
			case 'recent':
				$this->recentPoints();
				break;
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
		$user = new User;
		$points = [];
		$form = [];

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
			$result = $this->DB->select(
				[
					'wiki_points',
					'page'
				],
				[
					'wiki_points.*',
					'page.page_title'
				],
				['wiki_points.user_id' => $user->getId()],
				__METHOD__,
				[
					'ORDER BY'	=> 'wiki_points.created DESC',
					'LIMIT'		=> 250
				],
				[
					'page' => [
						'LEFT JOIN', 'wiki_points.article_id = page.page_id'
					]
				]
			);

			while ($row = $result->fetchRow()) {
				$points[] = $row;
			}
			if (empty($form['username'])) {
				$form['username'] = $user->getName();
			}
		} else {
			$form['error'] = wfMessage('error_wikipoints_admin_user_not_found')->escaped();
		}

		$this->content = TemplateWikiPointsAdmin::lookup($user, $points, $form);
	}

	/**
	 * Shows only the recently earned points.
	 *
	 * @access	private
	 * @return	void	[Outputs to screen]
	 */
	private function recentPoints() {
		$result = $this->DB->select(
			[
				'wiki_points',
				'user',
				'page'
			],
			[
				'wiki_points.*',
				'user.user_name',
				'page.page_title'
			],
			null,
			__METHOD__,
			[
				'ORDER BY'	=> 'wiki_points.created DESC',
				'LIMIT'		=> 500
			],
			[
				'user' => [
					'LEFT JOIN', 'user.user_id = wiki_points.user_id'
				],
				'page' => [
					'LEFT JOIN', 'wiki_points.article_id = page.page_id'
				]
			]
		);

		$pointsData = [];
		while ($row = $result->fetchRow()) {
			$pointsData[] = $row;
		}
		$this->content = TemplateWikiPointsAdmin::recentTable($pointsData);
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
