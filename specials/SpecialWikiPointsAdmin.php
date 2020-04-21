<?php
/**
 * Curse Inc.
 * Cheevos
 * A contributor scoring system
 *
 * @package   Cheevos
 * @author    Noah Manneschmidt
 * @copyright (c) 2014 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
**/

use Cheevos\Cheevos;
use Cheevos\CheevosException;

class SpecialWikiPointsAdmin extends HydraCore\SpecialPage {
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
		parent::__construct('WikiPointsAdmin', 'wiki_points_admin');
	}

	/**
	 * Main Executor
	 *
	 * @param string	Sub page passed in the URL.
	 *
	 * @return void	[Outputs to screen]
	 */
	public function execute($subpage) {
		$this->checkPermissions();

		$this->output->addModuleStyles(['ext.cheevos.wikiPoints.styles']);

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
	 * @return void	[Outputs to screen]
	 */
	private function lookUpUser() {
		global $dsSiteKey;

		$user = null;
		$pointsLog = [];
		$form = [];
		$globalId = false;

		$form['username'] = $this->wgRequest->getVal('user');

		if (!empty($form['username'])) {
			$user = User::newFromName($form['username']);

			if ($user !== false && $user->getId()) {
				$globalId = Cheevos::getUserIdForService($user);
			}

			$pointsLog = [];
			if ($globalId > 0) {
				try {
					$pointsLog = Cheevos::getWikiPointLog(['user_id' => $globalId, 'site_key' => $dsSiteKey, 'limit' => 100]);
				} catch (CheevosException $e) {
					throw new ErrorPageError(wfMessage('cheevos_api_error_title'), wfMessage('cheevos_api_error', $e->getMessage()));
				}
				if (empty($form['username'])) {
					$form['username'] = $user->getName();
				}
			} elseif ($globalId === false) {
				$form['error'] = wfMessage('error_wikipoints_user_not_found')->escaped();
			}
		}

		$this->output->setPageTitle(($user ? wfMessage('wiki_points_admin_lookup', $user->getName()) : wfMessage('wikipointsadmin')));
		$this->content = TemplateWikiPointsAdmin::lookup($user, $pointsLog, $form);
	}

	/**
	 * Adjust points by an arbitrary integer amount.
	 *
	 * @return void	[Outputs to screen]
	 */
	private function adjustPoints() {
		if (!$this->wgUser->isAllowed('wpa_adjust_points')) {
			throw new PermissionsError('wpa_adjust_points');
		}

		$page = Title::newFromText('Special:WikiPointsAdmin');
		$userName = $this->wgRequest->getVal('user');
		if ($this->wgRequest->wasPosted()) {
			$amount = $this->wgRequest->getInt('amount');
			if ($amount > 0) {
				$amount = min($amount, 10000);
			} else {
				$amount = max($amount, -10000);
			}
			$user = User::newFromName($userName);

			if ($amount && $user->getId()) {
				CheevosHooks::increment('wiki_points', intval($amount), $user);
			}

			$userEscaped = urlencode($this->wgRequest->getVal('user'));
			$this->output->redirect($page->getFullURL(['user' => $userName, 'pointsAdjusted' => 1]));
		} else {
			$this->output->redirect($page->getFullURL(['user' => $userName]));
		}
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'wikipoints';
	}
}
