<?php
/**
 * Curse Inc.
 * Wiki Points
 * Wiki Points Multipliers Special Page
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		Wiki Points
 * @link		http://www.curse.com/
 *
**/

class SpecialWikiPointsMultipliers extends HydraCore\SpecialPage {
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
		parent::__construct('WikiPointsMultipliers', 'wiki_points_multipliers');
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

		$this->redis = RedisCache::getClient('cache');
		$this->templateWikiPointsMultipliers = new TemplateWikiPointsMultipliers;

		$this->output->addModules(['ext.cheevos.wikiPoints', 'ext.wikiSelect', 'ext.dynamicSettings']);

		switch ($this->wgRequest->getVal('section')) {
			default:
			case 'list':
				$this->pointsMultipliersList();
				break;
			case 'form':
				$this->pointsMultipliersForm();
				break;
			case 'delete':
				$this->pointsMultipliersDelete();
				break;
		}

		$this->setHeaders();

		$this->output->addHTML($this->content);
	}

	/**
	 * Points Multipliers List
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function pointsMultipliersList() {
		$multipliers = PointsMultiplier::loadAll();

		$this->output->setPageTitle(wfMessage('wikipointsmultipliers'));
		$this->content = $this->templateWikiPointsMultipliers->pointsMultipliersList($multipliers);
	}

	/**
	 * Points Multipliers Form
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function pointsMultipliersForm() {
		if ($this->wgRequest->getInt('multiplier_id')) {
			$multiplierId = $this->wgRequest->getInt('multiplier_id');

			// Time to grab all the promos and wiki sites so we can use them through out all the form stuff
			$this->multiplier = PointsMultiplier::loadFromId($multiplierId);

			if (!$this->multiplier) {
				$this->output->showErrorPage('multipliers_error', 'error_no_promo');
				return;
			}
		} else {
			$this->multiplier = PointsMultiplier::loadFromNew();
		}

		$errors = $this->pointsMultipliersSave();

		$_wikis = $this->multiplier->getWikis();
		if (is_array($_wikis) && count($_wikis)) {
			foreach ($_wikis as $data) {
				$key = null;
				if ($data['override'] == 1) {
					$key = 'added';
				}
				if ($data['override'] == -1) {
					$key = 'removed';
				}

				$wikis[$key][] = $data['site_key'];
			}
		}

		if ($this->multiplier->getDatabaseId()) {
			$this->output->setPageTitle(wfMessage('edit_multiplier'));
		} else {
			$this->output->setPageTitle(wfMessage('add_multiplier'));
		}
		$this->content = $this->templateWikiPointsMultipliers->pointsMultipliersForm($this->multiplier, $wikis, $errors);
	}

	/**
	 * Saves submitted Points Multipliers Forms.
	 *
	 * @access	private
	 * @return	array	Array containing an array of processed form information and array of corresponding errors.
	 */
	private function pointsMultipliersSave() {
		if ($this->wgRequest->getVal('do') == 'save') {
			if (!$this->multiplier->setMultiplier($this->wgRequest->getText('multiplier'))) {
				$errors['multiplier'] = wfMessage('error_invalid_multiplier_name');
			}

			$this->multiplier->setBegins($this->wgRequest->getInt('begins'));

			$this->multiplier->setExpires($this->wgRequest->getInt('expires'));
			if (($this->multiplier->getExpires() < time() || $this->multiplier->getExpires() < $this->multiplier->getBegins()) && $this->multiplier->getBegins() > 0) {
				$errors['expires'] = wfMessage('error_invalid_multiplier_expires');
			}

			$this->multiplier->setEnabledEverywhere($this->wgRequest->getCheck('everywhere'));

			$wikis = $this->wgRequest->getVal('wikis');
			$wikis = @json_decode($wikis, true);

			$this->multiplier->clearWikis();
			if (is_array($wikis['added']) && count($wikis['added'])) {
				foreach ($wikis['added'] as $siteKey) {
					$this->multiplier->addWiki($siteKey, 1);
				}
			}
			if (is_array($wikis['removed']) && count($wikis['removed'])) {
				foreach ($wikis['removed'] as $siteKey) {
					$this->multiplier->addWiki($siteKey, -1);
				}
			}

			if (!count($errors)) {
				if ($this->multiplier->save()) {
					$page = Title::newFromText('Special:WikiPointsMultipliers');
					$this->output->redirect($page->getFullURL());
					return;
				}
			}
		}
		return $errors;
	}

	/**
	 * Delete Points Multipliers.
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function pointsMultipliersDelete() {
		if ($this->wgRequest->getVal('do') == 'delete') {
			$multiplier = PointsMultiplier::loadFromId($this->wgRequest->getInt('multiplier_id'));

			if (!$multiplier) {
				$this->output->showErrorPage('multipliers_error', 'points_not_found');
				return;
			}

			if ($this->wgRequest->getVal('confirm') == 'true') {
				$this->DB->delete(
					'wiki_points_multipliers',
					['mid' => $multiplier->getDatabaseId()],
					__METHOD__
				);
				$this->DB->delete(
					'wiki_points_multipliers_sites',
					['multiplier_id' => $multiplier->getDatabaseId()],
					__METHOD__
				);
				$this->DB->commit();

				$this->redis->hDel('wikipoints:multiplier:everywhere', $multiplier->getDatabaseId());

				foreach ($multiplier->getWikis() as $data) {
					$this->redis->del('wikipoints:multiplier:'.$data['site_key']);
				}

				$page = Title::newFromText('Special:WikiPointsMultipliers');
				$this->output->redirect($page->getFullURL());
			}

			$this->output->setPageTitle(wfMessage('delete_multiplier')->escaped());
			$this->content = $this->templateWikiPointsMultipliers->pointsMultipliersDelete($multiplier);
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
