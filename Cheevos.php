<?php
/**
 * Cheevos
 * Cheevos Mediawiki Settings
 *
 * @package   Cheevos
 * @author    Hydra Wiki Platform Team
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 **/

if (function_exists('wfLoadExtension')) {
	wfLoadExtension('Cheevos');
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Cheevos'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for Cheevos extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die('This version of the Cheevos extension requires MediaWiki 1.25+');
}
