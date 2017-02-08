**Achievements** is a Mediawiki extension that tracks an users progress as a wiki editor and awards achievement icons, badges, and points when meeting certain criteria.  It works primarily against the Mediawiki system of hooks to link into the core and other extensions.

* **Source Code:** [Part of the Hydra Stash Project](https://stash.curse.us/projects/HAIL/repos/hydra/browse)
* **Bugs:** [Hydra JIRA Project](https://jira.curse.us/browse/HYD/)
* **Licensing:** Cheevos is All Rights Reserved.


#Installation

Download and place the file(s) in a directory called Achievements in your extensions/ folder.

For pre-Mediawiki 1.25 installations add the following code at the bottom of your LocalSettings.php:

	require_once("$IP/extensions/Achievements/Achievements.php");

For Mediawiki 1.25 and higher installations use the wfLoadExtensions() function call:

	wfLoadExtensions(['Achievements']);

Done! Navigate to "Special:CheevosVersion" on the wiki to verify that the extension is successfully installed.


#Configuration

##Variables
	$achImageDomainWhiteList
A white list of domains that are allowed to be used for achievement image URLs.  If none are configured then any are allowed.  The code performs a simple string position look up on the white list entry provided to ensure it exists in the provided image URL.
Default:
	['gamepedia.com', 'gamepedia.local', 'cursecdn.com', 'cursecdn.local']

	$achPointAbbreviation
The achievement point abbreviation that appears after points display.  This can be any valid HTML.
Default:
	'<img src="/extensions/WikiPoints/images/gp30.png"/>'

	$achMegaNameTemplate
When generating a default mega achievement this template is used to format the name for the mega achievement.  It should accept one variable for string formatting which the given variable will be the wiki name.
Default:
	'Master of %1$s'

	$achMegaDescriptionTemplate
When generating a default mega achievement this template is used to format the description for the mega achievement.  It should accept one variable for string formatting which the given variable will be the wiki name.
Default:
	'Earned several achievements in every category on %1$s.'

	$achMegaDefaultImage
This is default image for mega achievements when generating a default mega achievement.
Default:
	'https://hydra-media.cursecdn.com/commons.cursetech.com/b/b2/Mega_Achievement_Default.png'

##User Rights
	achievement_admin
Allowed to perform administrator tasks on achievements.

	edit_achievements
Allowed to edit achievements.

	edit_meta_achievements
Allowed to edit meta style local achievements.

	edit_mega_achievements
Allowed to edit mega achievements up to the global service.

	edit_achievement_triggers
Allowed to edit triggers and conditions for an achievement.

	delete_achievements
Allowed to delete achievements.

	restore_achievements
Allowed to restore deleted achievements.

	award_achievements
Allowed to manually award or unaward achievements.