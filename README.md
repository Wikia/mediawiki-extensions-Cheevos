**Cheevos** is a Mediawiki extension that tracks an users progress as a wiki editor and awards achievement icons, badges, and points when meeting certain criteria.  It works primarily against the Mediawiki system of hooks to link into the core and other extensions.

* **Source Code:** [Cheevos on GitLab](https://gitlab.com/hydrawiki/extensions/cheevos/tree/develop)
* **Bugs:** [Hydra GitLab Project](https://gitlab.com/hydrawiki/extensions/cheevos/issues)
* **Licensing:** Cheevos is released under the [GNU GPLv2](https://gitlab.com/hydrawiki/extensions/cheevos/blob/develop/LICENSE).


# Installation

Download and place the file(s) in a directory called Achievements in your extensions/ folder.

For pre-Mediawiki 1.25 installations add the following code at the bottom of your LocalSettings.php:

`require_once("$IP/extensions/Achievements/Achievements.php");`

For Mediawiki 1.25 and higher installations use the wfLoadExtensions() function call:

`wfLoadExtensions(['Achievements']);`

Done! Navigate to "Special:Version" on the wiki to verify that the extension is successfully installed.


# Configuration

## Variables
**$achImageDomainWhiteList** - A white list of domains that are allowed to be used for achievement image URLs.  If none are configured then any are allowed.  The code performs a simple string position look up on the white list entry provided to ensure it exists in the provided image URL.
* Default: `['gamepedia.com', 'gamepedia.wiki', 'cursecdn.com', 'cursecdn.local']`

**$wgAchPointAbbreviation** - The achievement point abbreviation that appears after points display.  This can be any valid HTML.
* Default: `'<img src="/extensions/Cheevos/images/gp30.png"/>'`

**$achMegaNameTemplate** - When generating a default mega achievement this template is used to format the name for the mega achievement.  It should accept one variable for string formatting which the given variable will be the wiki name.
* Default: `'Master of %1$s'`

**$achMegaDescriptionTemplate** - When generating a default mega achievement this template is used to format the description for the mega achievement.  It should accept one variable for string formatting which the given variable will be the wiki name.
* Default: `'Earned several achievements in every category on %1$s.'`

**$achMegaDefaultImage** - This is default image for mega achievements when generating a default mega achievement.
* Default: `'https://hydra-media.cursecdn.com/commons.cursetech.com/b/b2/Mega_Achievement_Default.png'`

## User Rights
* `achievement_admin` - Allowed to perform administrator tasks on achievements.

* `edit_achievements` - Allowed to edit achievements.

* `edit_meta_achievements` - Allowed to edit meta style local achievements.

* `delete_achievements` - Allowed to delete achievements.

* `restore_achievements` - Allowed to restore deleted achievements.

* `award_achievements` - Allowed to manually award or unaward achievements.