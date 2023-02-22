<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = [
	'../../extensions/RedisCache'
];

$cfg['exclude_analysis_directory_list'] = [
	'../../extensions/RedisCache'
];

return $cfg;
