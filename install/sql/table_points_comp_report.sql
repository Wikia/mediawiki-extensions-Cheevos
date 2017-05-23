CREATE TABLE IF NOT EXISTS /*_*/points_comp_report (
  `report_id` int(14) NOT NULL,
  `run_time` int(14) NOT NULL DEFAULT '0',
  `min_points` int(8) NOT NULL DEFAULT '0',
  `max_points` int(8) NOT NULL DEFAULT '1000',
  `start_time` int(14) NOT NULL DEFAULT '0',
  `end_time` int(14) NOT NULL DEFAULT '0',
  `comp_new` int(10) NOT NULL DEFAULT '0',
  `comp_extended` int(10) NOT NULL DEFAULT '0',
  `comp_failed` int(10) NOT NULL,
  `comp_performed` tinyint(1) NOT NULL DEFAULT '0',
  `email_sent` tinyint(1) NOT NULL DEFAULT '0'
) /*$wgDBTableOptions*/;

ALTER TABLE /*_*/points_comp_report ADD PRIMARY KEY (`report_id`), ADD KEY `month_start_month_end` (`start_time`,`end_time`);

ALTER TABLE /*_*/points_comp_report
MODIFY `report_id` int(14) NOT NULL AUTO_INCREMENT;