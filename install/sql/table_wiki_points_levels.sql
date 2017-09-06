CREATE TABLE /*_*/wiki_points_levels (
  `lid` int(14) NOT NULL AUTO_INCREMENT,
  `points` int(14) NOT NULL DEFAULT '0',
  `text` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `image_icon` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `image_large` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`lid`)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/points ON /*_*/wiki_points_levels (points);
