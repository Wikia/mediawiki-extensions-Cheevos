<?php
/**
 * Cheevos
 * Cheevos Mediawiki Settings
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

if ( function_exists( 'wfLoadExtension' ) ) {
    wfLoadExtension( 'Cheevos' );
    // Keep i18n globals so mergeMessageFileList.php doesn't break
    $wgMessagesDirs['Cheevos'] = __DIR__ . '/i18n';
    wfWarn(
 	   'Deprecated PHP entry point used for Cheevos extension. Please use wfLoadExtension instead, ' .
 	   'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
    );
    return;
 } else {
    die( 'This version of the Cheevos extension requires MediaWiki 1.25+' );
 }



/*
$credits = [
	'path'				=> __FILE__,
	'name'				=> 'Achievements',
	'author'			=> ['Alexia E. Smith'],
	'descriptionmsg'	=> 'achievements_description',
	'version'			=> '2.0'
];
$wgExtensionCredits['parserhook'][] = $credits;


$extDir = __DIR__;
define('ACH_EXT_DIR', $extDir);

$wgAvailableRights[] = 'achievement_admin';
$wgAvailableRights[] = 'mega_achievement_admin';
$wgAvailableRights[] = 'edit_achievements';
$wgAvailableRights[] = 'edit_meta_achievements';
$wgAvailableRights[] = 'edit_mega_achievements';
$wgAvailableRights[] = 'edit_achievement_triggers';
$wgAvailableRights[] = 'delete_achievements';
$wgAvailableRights[] = 'restore_achievements';
$wgAvailableRights[] = 'award_achievements';


$wgExtensionMessagesFiles['Achievements']			= "{$extDir}/Achievements.i18n.php";
$wgExtensionMessagesFiles['SpecialAchievements']	= "{$extDir}/Achievements.alias.php";
$wgMessagesDirs['Achievements']						= "{$extDir}/i18n";

$wgAutoloadClasses['CheevosHooks']				= "{$extDir}/Achievements.hooks.php";
$wgAutoloadClasses['AchievementsAPI']				= "{$extDir}/Achievements.api.php";
$wgAutoloadClasses['SpecialAchievements']			= "{$extDir}/specials/SpecialAchievements.php";
$wgAutoloadClasses['SpecialAwardAchievement']		= "{$extDir}/specials/SpecialAwardAchievement.php";
$wgAutoloadClasses['SpecialMegaAchievements']		= "{$extDir}/specials/SpecialMegaAchievements.php";
$wgAutoloadClasses['Cheevos\Achievement']		= "{$extDir}/classes/Achievement.php";
$wgAutoloadClasses['Cheevos\Category']			= "{$extDir}/classes/Category.php";
$wgAutoloadClasses['Cheevos\FakeAchievement']	= "{$extDir}/classes/Achievement.php";
$wgAutoloadClasses['Cheevos\Progress']			= "{$extDir}/classes/Progress.php";
$wgAutoloadClasses['Cheevos\MegaAchievement']	= "{$extDir}/classes/MegaAchievement.php";
$wgAutoloadClasses['Cheevos\MegaProgress']		= "{$extDir}/classes/MegaProgress.php";
$wgAutoloadClasses['Cheevos\MegaService']		= "{$extDir}/classes/MegaService.php";
$wgAutoloadClasses['Cheevos\SiteMegaUpdate']	= "{$extDir}/classes/SiteMegaUpdate.php";
$wgAutoloadClasses['TemplateAchievements']			= "{$extDir}/templates/TemplateAchievements.php";
$wgAutoloadClasses['TemplateAwardAchievement']		= "{$extDir}/templates/TemplateAwardAchievement.php";
$wgAutoloadClasses['TemplateMegaAchievements']		= "{$extDir}/templates/TemplateMegaAchievements.php";

$wgSpecialPages['Achievements']						= 'SpecialAchievements';
$wgSpecialPages['AwardAchievement']					= 'SpecialAwardAchievement';

if (!defined('RUN_MAINTENANCE_IF_MAIN')) {
	$wgHooks['SetupAfterCache'][]					= 'CheevosHooks::register';
}
$wgHooks['LoadExtensionSchemaUpdates'][]			= 'CheevosHooks::onLoadExtensionSchemaUpdates';  //Database updates.
$wgHooks['SkinAfterBottomScripts'][]				= 'CheevosHooks::onSkinAfterBottomScripts';  //Display pop up awards.
$wgHooks['PersonalUrls'][]							= 'CheevosHooks::onPersonalUrls';  //Display achievements page link in user tools drop down.
$wgHooks['WikiSitesMenuOptions'][]					= 'CheevosHooks::onWikiSitesMenuOptions';  //Add Achievements options into WikiSites menu.
$wgHooks['AchievementAwarded'][]					= 'CheevosHooks::onAchievementAwarded';  //Whenever an achievement is awarded inside the Achievement class.
$wgHooks['AchievementUnawarded'][]					= 'CheevosHooks::onAchievementUnawarded';  //Whenever an achievement is unawarded inside the Achievement class.
//Custom Coded Hook Triggers
$wgHooks['ParserAfterTidy'][]						= 'CheevosHooks::onParserAfterTidy';
$wgHooks['WikiPointsSave'][]						= 'CheevosHooks::onWikiPointsSave';

//API Setup
$wgAPIModules['achievements']						= 'AchievementsAPI';

//SyncService
$extSyncServices[] = 'Cheevos\SiteMegaUpdate';

$wgResourceModules['ext.achievements.styles'] = [
	'localBasePath'	=> __DIR__,
	'remoteExtPath'	=> 'Achievements',
	'styles'		=> ['css/achievements.css'],
	'dependencies'	=> ['ext.hydraCore.button', 'ext.hydraCore.pagination'],
	'targets'		=> ['desktop', 'mobile'],
	'position'		=> 'top'
];
$wgResourceModules['ext.achievements.js'] = [
	'localBasePath'	=> __DIR__,
	'remoteExtPath'	=> 'Achievements',
	'dependencies'	=> ['ext.achievements.resumable.js'],
	'scripts'		=> ['js/achievements.js'],
	'targets'		=> ['desktop', 'mobile'],
	'position'		=> 'top'
];
$wgResourceModules['ext.achievements.triggerBuilder.js'] = [
	'localBasePath'	=> __DIR__,
	'remoteExtPath'	=> 'Achievements',
	'scripts'		=> ['js/achievements.triggerBuilder.js'],
	'dependencies'	=> ['ext.achievements.resumable.js'],
	'messages'		=> [
		'add_condition',
		'add_trigger',
		'cancel_trigger',
		'delete_condition',
		'delete_trigger',
		'save_trigger',
		'trigger_builder',
		'error_connecting_to_site',
		'bad_site_key',
		'choose_existing_hook'
	],
	'targets'		=> ['desktop', 'mobile'],
	'position'		=> 'top'
];
$wgResourceModules['ext.achievements.resumable.js'] = [
	'localBasePath'	=> __DIR__,
	'remoteExtPath'	=> 'Achievements',
	'scripts'		=> ['js/achievements.resumable.js'],
	'targets'		=> ['desktop', 'mobile'],
	'position'		=> 'top'
];
$wgResourceModules['ext.achievements.notice.js'] = [
	'localBasePath'	=> __DIR__,
	'remoteExtPath'	=> 'Achievements',
	'scripts'		=> ['js/achievements.notice.js'],
	'targets'		=> ['desktop', 'mobile'],
	'position'		=> 'top'
];



//Setup sysop to have this by default.
$wgGroupPermissions['sysop']['achievement_admin']			= true;
$wgGroupPermissions['sysop']['edit_achievements']			= true;
$wgGroupPermissions['sysop']['edit_meta_achievements']		= true;
$wgGroupPermissions['sysop']['edit_achievement_triggers']	= true;
$wgGroupPermissions['sysop']['delete_achievements']			= true;
$wgGroupPermissions['sysop']['restore_achievements']		= true;
$wgGroupPermissions['sysop']['award_achievements']			= true;

$wgGroupPermissions['hydra_staff']['achievement_admin']			= true;
$wgGroupPermissions['hydra_staff']['edit_achievements']			= true;
$wgGroupPermissions['hydra_staff']['edit_meta_achievements']		= true;
$wgGroupPermissions['hydra_staff']['edit_achievement_triggers']	= true;
$wgGroupPermissions['hydra_staff']['delete_achievements']			= true;
$wgGroupPermissions['hydra_staff']['restore_achievements']		= true;
$wgGroupPermissions['hydra_staff']['award_achievements']			= true;

$wgGroupPermissions['hydra_admin']['achievement_admin']			= true;
$wgGroupPermissions['hydra_admin']['edit_achievements']			= true;
$wgGroupPermissions['hydra_admin']['edit_meta_achievements']	= true;
$wgGroupPermissions['hydra_admin']['edit_achievement_triggers']	= true;
$wgGroupPermissions['hydra_admin']['delete_achievements']		= true;
$wgGroupPermissions['hydra_admin']['restore_achievements']		= true;
$wgGroupPermissions['hydra_admin']['award_achievements']		= true;
if (defined('MASTER_WIKI') && MASTER_WIKI === true) {
	define("ACHIEVEMENTS_MASTER", true);

	$wgSpecialPages['MegaAchievements']					= 'SpecialMegaAchievements';

	$wgGroupPermissions['hydra_admin']['mega_achievement_admin']	= true;
	$wgGroupPermissions['hydra_admin']['edit_mega_achievements']	= true;
} else {
	$wgGroupPermissions['hydra_admin']['mega_achievement_admin']	= false;
	$wgGroupPermissions['hydra_admin']['edit_mega_achievements']	= false;
}

$achImageDomainWhiteList = [
	'gamepedia.com',
	'gamepedia.local',
	'cursecdn.com',
	'cursecdn.local'
];

$achPointAbbreviation = '<img src="/extensions/WikiPoints/images/gp30.png"/>';

$achMegaNameTemplate = 'Master of %1$s';

$achMegaDescriptionTemplate = 'Earned several achievements in every category on %1$s.';

$achMegaDefaultImage = 'https://hydra-media.cursecdn.com/commons.cursetech.com/b/b2/Mega_Achievement_Default.png';

$achServiceConfig = [
	'service_url'	=> 'http://achievement-service.curse.us/',
	'site_id'		=> '5555001',
	'host_name'		=> parse_url($wgServer, PHP_URL_HOST),
	'site_key'		=> $dsSiteKey
];
if ($_SERVER['PHP_ENV'] == 'development') {
	$achServiceConfig['service_url'] = 'http://achievement-service.curse.local/';
}
*/
