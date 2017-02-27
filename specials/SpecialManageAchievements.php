<?php
/**
 * Cheevos
 * Cheevos Special Page
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

class SpecialManageAchievements extends SpecialPage {
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
		parent::__construct('ManageAchievements');

		$this->wgRequest	= $this->getRequest();
		$this->wgUser		= $this->getUser();
		$this->output		= $this->getOutput();
		$this->api			= new Cheevos\Cheevos();
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @param	string	Sub page passed in the URL.
	 * @return	void	[Outputs to screen]
	 */
	public function execute($subpage) {
		$this->templates = new TemplateManageAchievements;
		$this->output->addModules(['ext.achievements.styles', 'ext.achievements.js']);
		$this->setHeaders();


		switch ($subpage) {
			default:
			case 'view':
				$this->achievementsList();
				break;
			case 'add':
				$this->addAchievement();
				break;
			/*case 'admin':
				$this->achievementsForm();
				break;
			case 'delete':
			case 'restore':
				$this->achievementsDelete($subpage);
				break;*/
		}

		$this->output->addHTML($this->content);
	}

	/**
	 * Cheevos List
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function achievementsList() {

		$site_id = 0; // int | The site id to use for locally overridden achievements.
		$limit = 25; // int | Maximum number of items in the result.
		$offset = 0; // int | Number of items to skip in the result.  Defaults to 0.


		$achievements = $this->api->getAchievements();

		//$categories = $this->api->getCategories();
		//var_dump($categories);


		$this->output->setPageTitle(wfMessage('achievements')->escaped());
		$this->content = $this->templates->achievementsList($achievements, $categories, $progress, $hide, $searchTerm);
	}


	public function addAchievement() {
		$api_instance = new Swagger\Client\Api\DefaultApi();
		$body = new \Swagger\Client\Model\Achievement(); // \Swagger\Client\Model\Achievement |


		    $result = $api_instance->achievementPut($body);
		    print_r($result);

		die();
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
