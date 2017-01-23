<?php
/**
 * Cheevos
 * Site Mega Updater
 *
 * @author		Wiki Platform Team
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

namespace Cheevos;

class SiteMegaUpdate extends \SyncService\Job {
	/**
	 * Updates site mega main achievements for all or individual sites.
	 *
	 * @access	public
	 * @param	array	Named arguments passed by the command that queued this job.
	 * - task	string	Task to handle.
	 * - site_key	string	The MD5 site key for the wiki.
	 * @return	boolean	Success
	 */
	public function execute($args = []) {
		global $achMegaNameTemplate, $achMegaDescriptionTemplate, $achMegaDefaultImage;

		$siteKey	= $args['site_key'];
		$wiki = \DynamicSettings\Wiki::loadFromHash($siteKey);
		if ($wiki !== false) {
			//Error checks pass, get and send data.
			try {
				$siteDB = $wiki->getDatabaseLB()->getConnection(DB_MASTER);

				$results = $siteDB->select(
					['achievement'],
					['unique_hash'],
					[
						'deleted'				=> 0,
						'secret'				=> 0,
						'manual_award'			=> 0,
						'part_of_default_mega'	=> 1,
					],
					__METHOD__
				);

				$defaultMegaRequires = [];
				while ($row = $results->fetchRow()) {
					$defaultMegaRequires[] = $row['unique_hash'];
				}

				$result = $this->DB->select(
					['achievement_site_mega'],
					['*'],
					['site_key' => $wiki->getSiteKey()],
					__METHOD__
				);
				$existingMega = $result->fetchRow();

				if (!empty($existingMega) && $existingMega['mega_id'] > 0) {
					$megaAchievement = \Cheevos\MegaAchievement::newFromId($existingMega['mega_id']);
				} else {
					$megaAchievement = new \Cheevos\MegaAchievement;
				}
				if ($megaAchievement !== false) {
					$megaAchievement->setName(sprintf($achMegaNameTemplate, $wiki->getName()));
					$megaAchievement->setDescription(sprintf($achMegaDescriptionTemplate, $wiki->getName()));
					$megaAchievement->setSiteKey($wiki->getSiteKey());
					$megaAchievement->setImageUrl($achMegaDefaultImage);
					$megaAchievement->setRequires($defaultMegaRequires);

					if ($megaAchievement->save()) {
						if ($existingMega['asmid'] > 0) {
							$this->DB->update(
								'achievement_site_mega',
								[
									'timestamp' => time()
								],
								"asmid = ".intval($existingMega['asmid'])
							);
							$this->DB->update(
								'achievement_site_mega',
								['timestamp' => time()],
								['asmid'	=> $existingMega['asmid']],
								__METHOD__
							);
						} else {
							$this->DB->insert(
								'achievement_site_mega',
								[
									'site_key'	=> $megaAchievement->getSiteKey(),
									'mega_id'	=> $megaAchievement->getId(),
									'timestamp' => time()
								],
								__METHOD__
							);
						}
						$this->outputLine('Successfully added/updated default site mega achievement for '.$wiki->getDomains()->getDomain(), time());
						return 0;
					} else {
						$this->outputLine('Failed to add/update default site mega achievement for '.$wiki->getDomains()->getDomain(), time());
						return 1;
					}
				} else {
					$this->outputLine('Failed to load a new MegaAchievement for '.$wiki->getDomains()->getDomain(), time());
					return 1;
				}

				$siteDB->close();
			} catch (MWException $e) {
				$this->outputLine('Failed to add/update default site mega achievement for '.$wiki->getDomains()->getDomain(), time());
				$this->outputLine('Reason: '.$e->getMessage(), time());
				return 1;
			} catch (Exception $e) {
				$this->outputLine('Failed to add/update default site mega achievement for '.$wiki->getDomains()->getDomain(), time());
				$this->outputLine('Reason: '.$e->getMessage(), time());
				return 1;
			}
		}
	}
}
