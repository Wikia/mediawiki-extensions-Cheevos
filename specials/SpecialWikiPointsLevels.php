<?php
/**
 * Curse Inc.
 * Cheevos
 * Wiki Points Levels Page
 *
 * @package   Cheevos
 * @author    Alexia E. Smith
 * @copyright (c) 2014 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
**/

use Cheevos\Points\PointLevels;

class SpecialWikiPointsLevels extends HydraCore\SpecialPage {
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
		parent::__construct('WikiPointsLevels', 'wiki_points_admin');
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

		$this->templateWikiPointsLevels = new TemplateWikiPointsLevels;

		$this->output->addModuleStyles(['ext.cheevos.wikiPoints.styles']);
		$this->output->addModules(['ext.cheevos.wikiPoints.scripts']);

		$this->setHeaders();

		switch ($this->wgRequest->getVal('action')) {
			default:
			case 'levels':
				$this->levelsForm();
				break;
		}

		$this->output->addHTML($this->content);
	}

	/**
	 * Levels Form
	 *
	 * @return void	[Outputs to Screen]
	 */
	public function levelsForm() {
		$levels = PointLevels::getLevels();

		$errors = [];
		$success = null;
		if ($this->wgRequest->getVal('do') == 'save') {
			$lids = $this->wgRequest->getArray('lid');
			$points = $this->wgRequest->getArray('points');
			$text = $this->wgRequest->getArray('text');
			$image_icon = $this->wgRequest->getArray('image_icon');
			$image_large = $this->wgRequest->getArray('image_large');

			$levels = [];
			if (count($lids)) {
				foreach ($lids as $index => $lid) {
					$levels[] = [
						'points'		=> intval($points[$index]),
						'text'			=> $text[$index],
						'image_icon'	=> $image_icon[$index],
						'image_large'	=> $image_large[$index]
					];
				}
			}

			if (is_array($levels)) {
				$success = PointLevels::saveLevels($levels);
			}
		}

		$this->output->setPageTitle(wfMessage('wikipointslevels'));
		$this->content = $this->templateWikiPointsLevels->levelsForm($levels, $errors, $success);
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
