<?php
/**
 * Cheevos
 * Cheevos Increment Job
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Cheevos
 * @link		http://www.curse.com/
 *
 **/

class CheevosIncrementJob extends SyncService\Job {
	/**
	 * Sets the default priority to normal. Overwrite in subclasses to run at a different priority.
	 * @var		int		sets the priority at which this service will run
	 */
	public static $priority = self::PRIORITY_NORMAL;

	/**
	 * Overwrite in subclasses and set to true to use a key in redis as a lock file.
	 * While the lock exists, other instances will immediately die with an error message.
	 * @var		bool	enables single-instance mode for a job
	 */
	public static $forceSingleInstance = false;

	/**
	 * Give this job a different name to use for its redis key when queueing. Overrides $priority setting.
	 * Useful when a job should be run by a custom worker instance, rather than the default SyncService worker.
	 * @var		string
	 */
	public static $customJobQueue;

	/**
	 * Example Job
	 *
	 * @access	public
	 * @param	array	Named arguments passed by the command that queued this job.
	 * - example_1	First argument passed to ExampleJob::queue().
	 * - example_2	Second argument passed to ExampleJob::queue().
	 * - ...
	 * @return	boolean	Success, reported to Worker class to set the exit status of the process.
	 */
	public function execute($increment) {
		try {
			$lookup = CentralIdLookup::factory();
			$return = \Cheevos\Cheevos::increment($increment);
			if (isset($return['earned'])) {
				foreach ($return['earned'] as $achievement) {
					$achievement = new \Cheevos\CheevosAchievement($achievement);
					\CheevosHooks::displayAchievement($achievement, $increment['site_key'], $increment['user_id']);
					Hooks::run('AchievementAwarded', [$achievement, $increment['user_id']]);
				}
			}
			return ($return === false ? false : true);
		} catch (\Cheevos\CheevosException $e) {
			self::queue($increment); //Requeue in case of unintended failure.
			return false;
		}
	}
}