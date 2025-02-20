<?php

// TODO: stubs for internal code like WikiDomain

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$mediawikiPath = getenv( 'MEDIAWIKI_PATH' ) ?? 'mediawiki/';
$extensionsPath = getenv( 'EXTENSIONS_PATH' ) ?? 'mediawiki/extensions/';

$cfg['autoload_internal_extension_signatures'] = [
	'redis' => 'mediawiki/.phan/internal_stubs/redis.phan_php',
];

$mediawikiDirs = array_map(
	fn ( $path ) => $mediawikiPath . $path,
	[
		'includes/',
		'languages/',
		'maintenance/',
		'mw-config/',
		'resources/',
		'vendor/',
	] );

$extensionDependencyDirs = array_map(
	fn ( $path ) => $extensionsPath . $path,
	[
		'RedisCache',
		'Subscription',
		'HydraCore',
	],
);

$cfg['directory_list'] = array_merge(
	$mediawikiDirs,
	$extensionDependencyDirs,
	[ $extensionsPath . 'Cheevos' ],
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$mediawikiDirs,
	$extensionDependencyDirs,
	[
		$extensionsPath . 'Cheevos/vendor/',
		$extensionsPath . 'Cheevos/.phan/',
	]
);

$cfg['scalar_implicit_cast'] = true;

$cfg['suppress_issue_types'] = [
	...$cfg['suppress_issue_types'],
	// Horribly noisy / not useful due to our extensive use of empty() to check for things like empty strings,
	// especially in older code.
	'MediaWikiNoEmptyIfDefined',
	// Too many FPs to justify usage.
	'PhanParamTooFewUnpack',
	'PhanUndeclaredClassReference',
	'PhanUndeclaredTypeParameter',
	'PhanUndeclaredTypeProperty',
	'PhanUndeclaredClassMethod',
	// Phan Gets lost with submodule setups dependencies
	'SecurityCheck-LikelyFalsePositive'
];

// Explicitly set minimum and target PHP versions for Phan to avoid suggesting features not yet available in all
// versions we run while still offering forward-compatibility warnings.
$cfg['minimum_target_php_version'] = '8.2';
$cfg['target_php_version'] = '8.2';

return $cfg;
