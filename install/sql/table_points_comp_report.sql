CREATE TABLE /*_*/points_comp_report (
  `id` int(14) NOT NULL,
  `report_id` int(10) DEFAULT '0',
  `global_id` int(14) NOT NULL DEFAULT '0',
  `run_time` int(14) NOT NULL DEFAULT '0',
  `points` int(8) NOT NULL,
  `month_start` int(14) NOT NULL DEFAULT '0',
  `month_end` int(14) NOT NULL DEFAULT '0',
  `comp_new` int(10) NOT NULL DEFAULT '0',
  `comp_extended` int(10) NOT NULL DEFAULT '0',
  `comp_failed` int(10) NOT NULL,
  `current_comp_expires` int(14) NOT NULL DEFAULT '0',
  `new_comp_expires` int(14) NOT NULL DEFAULT '0',
  `comp_performed` tinyint(1) NOT NULL DEFAULT '0',
  `email_sent` tinyint(1) NOT NULL DEFAULT '0'
) /*$wgDBTableOptions*/;

ALTER TABLE /*_*/points_comp_report ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `report_id_global_id` (`report_id`,`global_id`), ADD KEY `month_start_month_end` (`month_start`,`month_end`), ADD KEY `current_comp_expires` (`current_comp_expires`);

ALTER TABLE /*_*/points_comp_report MODIFY `id` int(14) NOT NULL AUTO_INCREMENT;