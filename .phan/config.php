<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['suppress_issue_types'] = [
	'PhanUndeclaredClassReference',
	'PhanUndeclaredTypeParameter',
	'PhanUndeclaredTypeProperty',
	'PhanUndeclaredClassMethod',
	// Phan Gets lost with submodule setups dependencies
	'SecurityCheck-LikelyFalsePositive'
];

// Explicitly set minimum and target PHP versions for Phan to avoid suggesting features not yet available in all
// versions we run while still offering forward-compatibility warnings.
$cfg['minimum_target_php_version'] = '8.0';
$cfg['target_php_version'] = '8.0';

return $cfg;
