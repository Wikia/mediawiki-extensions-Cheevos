<?php
/**
 * Curse Inc.
 * Wiki Points
 * Wiki Points Levels Page
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2014 Curse Inc.
 * @license		All Rights Reserved
 * @package		Wiki Points
 * @link		http://www.curse.com/
 *
**/

class SpecialWikiPointsLevels extends HydraCore\SpecialPage {
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
		parent::__construct('WikiPointsLevels', 'wiki_points_admin');
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

		$this->templateWikiPointsLevels = new TemplateWikiPointsLevels;

		$this->output->addModules('ext.cheevos.wikiPoints');

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
	 * @access	public
	 * @return	void	[Outputs to Screen]
	 */
	public function levelsForm() {
		$levels = \Cheevos\Points\PointLevels::getLevels();

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

			if (!count($errors) && is_array($levels)) {
				$success = \Cheevos\Points\PointLevels::saveLevels($levels);
			}
		}

		$this->output->setPageTitle(wfMessage('wikipointslevels'));
		$this->content = $this->templateWikiPointsLevels->levelsForm($levels, $errors, $success);
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
