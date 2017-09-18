<?php
/**
 * Curse Inc.
 * Cheevos
 * Wiki Points Multipliers Special Page
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
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

		$this->output->addModules(['ext.cheevos.wikiPoints', 'ext.wikiSelect', 'ext.dynamicSettings', 'mediawiki.ui.input', 'mediawiki.ui.button']);

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
		try {
			$promotions = \Cheevos\Cheevos::getPointsPromotions(null, true);
		} catch (\Cheevos\CheevosException $e) {
			return false;
		}

		$this->output->setPageTitle(wfMessage('wikipointsmultipliers'));
		$this->content = TemplateWikiPointsMultipliers::pointsMultipliersList($promotions);
	}

	/**
	 * Points Multipliers Form
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function pointsMultipliersForm() {
		$this->multiplier = new \Cheevos\CheevosSiteEditPointsPromotion;
		if ($this->wgRequest->getInt('multiplier_id')) {
			$multiplierId = $this->wgRequest->getInt('multiplier_id');

			try {
				$this->multiplier = \Cheevos\Cheevos::getPointsPromotion($multiplierId);
			} catch (\Cheevos\CheevosException $e) {
				wfDebug(__METHOD__.": Error getting points promotion {$multiplierId}.");
			}

			if (!$this->multiplier) {
				$this->output->showErrorPage('multipliers_error', 'error_no_promo');
				return;
			}
		}

		$errors = $this->pointsMultipliersSave();

		if ($this->multiplier->getId()) {
			$this->output->setPageTitle(wfMessage('edit_multiplier'));
		} else {
			$this->output->setPageTitle(wfMessage('add_multiplier'));
		}
		$this->content = TemplateWikiPointsMultipliers::pointsMultipliersForm($this->multiplier, $errors);
	}

	/**
	 * Saves submitted Points Multipliers Forms.
	 *
	 * @access	private
	 * @return	array	Array containing an array of processed form information and array of corresponding errors.
	 */
	private function pointsMultipliersSave() {
		$errors = [];
		if ($this->wgRequest->getVal('do') == 'save' && $this->wgRequest->wasPosted()) {
			$_multiplier = floatval($this->wgRequest->getText('multiplier'));
			if ($_multiplier < 0.1 || $_multiplier > 100) {
				$errors['multiplier'] = wfMessage('error_invalid_multiplier_multiplier');
			}
			$this->multiplier->setMultiplier($_multiplier);

			$this->multiplier->setBegins($this->wgRequest->getInt('begins'));

			$this->multiplier->setExpires($this->wgRequest->getInt('expires'));
			if (($this->multiplier->getExpires() < time() || $this->multiplier->getExpires() < $this->multiplier->getBegins()) && $this->multiplier->getBegins() > 0) {
				$errors['expires'] = wfMessage('error_invalid_multiplier_expires');
			}

			$wikis = $this->wgRequest->getVal('wikis');
			$wikis = @json_decode($wikis, true);

			if (isset($wikis['single']) && !empty($wikis['single'])) {
				$this->multiplier->setSite_Key($wikis['single']);
			}

			if (!count($errors)) {
				try {
					\Cheevos\Cheevos::putPointsPromotion($this->multiplier, $this->multiplier->getId());
					$page = Title::newFromText('Special:WikiPointsMultipliers');
					$this->output->redirect($page->getFullURL());
					return;
			    } catch (\Cheevos\CheevosException $e) {
					throw new \ErrorPageError(wfMessage('cheevos_api_error_title'), wfMessage('cheevos_api_error', $e->getMessage()));
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
			$multiplierId = $this->wgRequest->getInt('multiplier_id');
			try {
				$multiplier = \Cheevos\Cheevos::getPointsPromotion($multiplierId);
			} catch (\Cheevos\CheevosException $e) {
				wfDebug(__METHOD__.": Error getting points promotion {$multiplierId}.");
			}

			if (!$multiplier) {
				$this->output->showErrorPage('multipliers_error', 'error_no_promo');
				return;
			}

			if ($this->wgRequest->getVal('confirm') == 'true' && $this->wgRequest->wasPosted()) {
				try {
					\Cheevos\Cheevos::deletePointsPromotion($multiplierId);
				} catch (\Cheevos\CheevosException $e) {
					wfDebug(__METHOD__.": Error getting points promotion {$multiplierId}.");
				}

				$page = Title::newFromText('Special:WikiPointsMultipliers');
				$this->output->redirect($page->getFullURL());
			}

			$this->output->setPageTitle(wfMessage('delete_multiplier')->escaped());
			$this->content = TemplateWikiPointsMultipliers::pointsMultipliersDelete($multiplier);
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
