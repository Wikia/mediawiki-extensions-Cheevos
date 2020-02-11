CREATE TABLE /*_*/points_comp_report_user (
  `ru_id` int(14) NOT NULL,
  `report_id` int(10) DEFAULT '0',
  `user_id` int(16) DEFAULT NULL,
  `points` int(8) NOT NULL,
  `start_time` int(14) NOT NULL DEFAULT '0',
  `end_time` int(14) NOT NULL DEFAULT '0',
  `comp_new` int(10) NOT NULL DEFAULT '0',
  `comp_extended` int(10) NOT NULL DEFAULT '0',
  `comp_failed` int(10) NOT NULL DEFAULT '0',
  `comp_skipped` int(10) NOT NULL DEFAULT '0',
  `current_comp_expires` int(14) NOT NULL DEFAULT '0',
  `new_comp_expires` int(14) NOT NULL DEFAULT '0',
  `comp_performed` tinyint(1) NOT NULL DEFAULT '0',
  `email_sent` tinyint(1) NOT NULL DEFAULT '0'
) /*$wgDBTableOptions*/;

ALTER TABLE /*_*/points_comp_report_user
  ADD PRIMARY KEY (`ru_id`),
  ADD UNIQUE KEY `report_id_user_id` (`report_id`,`user_id`),
  ADD KEY `start_time_end_time` (`start_time`,`end_time`);

ALTER TABLE /*_*/points_comp_report_user
  MODIFY `ru_id` int(14) NOT NULL AUTO_INCREMENT;