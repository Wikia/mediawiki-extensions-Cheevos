<?php
/**
 * Curse Inc.
 * Cheevos
 * Points Comp Report
 *
 * @package   Cheevos
 * @author    Alexia E. Smith
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki/extensions/cheevos
 */

namespace Cheevos\Points;

use Cheevos\AchievementService;
use Cheevos\CheevosStatMonthlyCount;
use DateInterval;
use DateTime;
use ExtensionRegistry;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use MWException;
use Status;
use Subscription\Providers\GamepediaPro;
use Subscription\SubscriptionProvider;
use User;

/**
 * Class containing some business and display logic for points blocks
 */
class PointsCompReport {

	private const STATS = [
		'comp_new',
		'comp_extended',
		'comp_failed',
		'comp_skipped',
		'comp_performed',
		'email_sent'
	];

	/**
	 * Report Data
	 * [{database row}]
	 *
	 * @var array
	 */
	private array $reportData = [];

	/**
	 * Report User Data
	 * [$userId => {database row}]
	 *
	 * @var array
	 */
	private array $reportUser = [];

	/**
	 * Main Constructor
	 *
	 * @return void
	 */
	public function __construct( int $reportId = 0 ) {
		$this->reportData['report_id'] = $reportId;
	}

	/**
	 * Load a new report object from the report ID.
	 *
	 * @param int $id Report ID
	 *
	 * @return PointsCompReport|null object or null if it does not exist.
	 */
	public static function newFromId( $id ): ?PointsCompReport {
		$report = new self( $id );

		$success = $report->load();

		return ( $success ? $report : null );
	}

	/**
	 * Load a new report object from a database row.
	 *
	 * @param array $row Report Row from the Database
	 */
	private static function newFromRow( $row ): PointsCompReport {
		$report = new self( $row['report_id'] );
		$report->reportData = $row;

		return $report;
	}

	/**
	 * Load information from the database.
	 *
	 * @return bool Sucess
	 */
	private function load(): bool {
		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		$result = $db->select(
			[ 'points_comp_report' ],
			[ '*' ],
			[ 'report_id' => $this->reportData['report_id'] ],
			__METHOD__
		);
		$report = $result->fetchObject();
		if ( empty( $report ) ) {
			return false;
		}
		$this->reportData = (array)$report;

		$result = $db->select(
			[ 'points_comp_report_user' ],
			[ '*' ],
			[ 'report_id' => $this->reportData['report_id'] ],
			__METHOD__,
			[
				'ORDER BY'	=> 'user_id ASC'
			]
		);

		if ( !empty( $this->reportUser ) ) {
			$this->reportUser = [];
		}
		while ( $row = $result->fetchObject() ) {
			if ( empty( $row ) || $row->user_id == 0 ) {
				continue;
			}

			$this->reportUser[$row->user_id] = (array)$row;
		}

		return (bool)$this->reportData[ 'report_id' ];
	}

	/**
	 * Save to database.
	 *
	 * @return bool Success
	 */
	public function save(): bool {
		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		$this->reportData['run_time'] = time();
		$reportData = $this->reportData;
		unset( $reportData['report_id'] );
		$db->startAtomic( __METHOD__ );
		if ( $this->reportData['report_id'] < 1 ) {
			$success = $db->insert(
				'points_comp_report',
				$reportData,
				__METHOD__
			);

			$this->reportData['report_id'] = $db->insertId();
			if ( !$success || !$this->reportData['report_id'] ) {
				throw new MWException( __METHOD__ . ': Could not get a new report ID.' );
			}
		} else {
			$db->update(
				'points_comp_report',
				$reportData,
				[ 'report_id' => $this->reportData['report_id'] ],
				__METHOD__
			);
		}

		foreach ( $this->reportUser as $data ) {
			$data['report_id'] = $this->reportData['report_id'];
			$data['start_time'] = $this->reportData['start_time'];
			$data['end_time'] = $this->reportData['end_time'];
			$db->upsert(
				'points_comp_report_user',
				$data,
				[ 'report_id_user_id' ],
				[
					'comp_new'			=> $data['comp_new'],
					'comp_extended'		=> $data['comp_extended'],
					'comp_failed'		=> $data['comp_failed'],
					'comp_skipped'		=> $data['comp_skipped'] ?? 0,
					'comp_performed'	=> $data['comp_performed'],
					'email_sent'		=> $data['email_sent']
				],
				__METHOD__
			);
		}
		$db->endAtomic( __METHOD__ );

		$this->updateStats();

		return true;
	}

	/**
	 * Update the report statistics into the database.
	 */
	public function updateStats(): void {
		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		foreach ( self::STATS as $stat ) {
			$result = $db->select(
				[ 'points_comp_report_user' ],
				[ 'count(' . $stat . ') as total' ],
				[
					$stat => 1,
					'report_id' => $this->reportData['report_id']
				],
				__METHOD__
			);
			$total = $result->fetchRow();
			$data[$stat] = (int)$total[ 'total' ];
		}
		$db->update(
			'points_comp_report',
			$data,
			[ 'report_id' => $this->reportData['report_id'] ],
			__METHOD__
		);
	}

	/**
	 * Load a list of basic report information.
	 *
	 * @param int $start Start Position
	 * @param int $itemsPerPage Maximum Items to Return
	 *
	 * @return array Multidimensional array of ['total' => $total, $reportId => [{reportUser}]]
	 */
	public static function getReportsList( int $start = 0, int $itemsPerPage = 50 ): array {
		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		$result = $db->select(
			[ 'points_comp_report' ],
			[ '*' ],
			[],
			__METHOD__,
			[
				'ORDER BY'	=> 'report_id DESC',
				'OFFSET'	=> $start,
				'LIMIT'		=> $itemsPerPage
			]
		);

		$reports = [];
		while ( $row = $result->fetchObject() ) {
			$reports[$row->report_id] = self::newFromRow( (array)$row );
		}

		$result = $db->select(
			[ 'points_comp_report' ],
			[ 'count(*) as total' ],
			[],
			__METHOD__,
			[
				'ORDER BY'	=> 'report_id DESC'
			]
		);
		$total = $result->fetchRow();
		$total = (int)$total[ 'total' ];

		return [ 'total' => $total, 'reports' => $reports ];
	}

	/**
	 * Get the report ID.
	 *
	 * @return int This report ID.
	 */
	public function getReportId(): int {
		return $this->reportData['report_id'];
	}

	/**
	 * Get when this reported was generated.
	 *
	 * @return int Run time Unix timestamp.
	 */
	public function getRunTime(): int {
		return $this->reportData['run_time'];
	}

	/**
	 * Get the minimum point threshold for this report.
	 *
	 * @return int Minimum point threshold.
	 */
	public function getMinPointThreshold(): int {
		return (int)$this->reportData[ 'min_points' ];
	}

	/**
	 * Set the minimum point threshold for this report.
	 *
	 * @param int $minPointThreshold Minimum point threshold for this report.
	 *
	 * @return void
	 */
	public function setMinPointThreshold( $minPointThreshold ): void {
		$this->reportData['min_points'] = (int)$minPointThreshold;
	}

	/**
	 * Get the maximum point threshold for this report.
	 *
	 * @return int|null Maximum point threshold.
	 */
	public function getMaxPointThreshold(): ?int {
		return ( $this->reportData['max_points'] === null ? null : (int)$this->reportData[ 'max_points' ] );
	}

	/**
	 * Set the maximum point threshold for this report.
	 *
	 * @param mixed|null $maxPointThreshold Maximum point threshold for this report or null for no maximum.
	 *
	 * @return void
	 */
	public function setMaxPointThreshold( mixed $maxPointThreshold = null ): void {
		$this->reportData['max_points'] = ( $maxPointThreshold === null ? null : (int)$maxPointThreshold );
	}

	/**
	 * Validate point thresholds.
	 *
	 * @param int $minPointThreshold Minimum Point Threshold
	 * @param int|null $maxPointThreshold [Optional] Maximum Point Threshold
	 *
	 * @return Status
	 */
	public static function validatePointThresholds( int $minPointThreshold, ?int $maxPointThreshold = null ): Status {
		if ( $maxPointThreshold !== null ) {
			if ( $maxPointThreshold <= 0 || $maxPointThreshold < $minPointThreshold ) {
				return Status::newFatal( 'invalid_maximum_threshold' );
			}
		}

		if ( $minPointThreshold < 0 ) {
			return Status::newFatal( 'invalid_minimum_threshold' );
		}

		return Status::newGood();
	}

	/**
	 * Get the time period start timestamp.
	 *
	 * @return int Unix timestamp for the time period start.
	 */
	public function getStartTime(): int {
		return (int)$this->reportData[ 'start_time' ];
	}

	/**
	 * Set the time period start timestamp.
	 *
	 * @param int $startTime Unix timestamp for the time period start.
	 *
	 * @return void
	 */
	public function setStartTime( int $startTime ): void {
		$this->reportData['start_time'] = $startTime;
	}

	/**
	 * Get the time period end timestamp.
	 *
	 * @return int Unix timestamp for the time period end.
	 */
	public function getEndTime(): int {
		return (int)$this->reportData[ 'end_time' ];
	}

	/**
	 * Set the time period end timestamp.
	 *
	 * @param int $endTime Unix timestamp for the time period end.
	 *
	 * @return void
	 */
	public function setEndTime( int $endTime ): void {
		$this->reportData['end_time'] = $endTime;
	}

	/**
	 * Validate time range.
	 *
	 * @param int $startTime Start Timestamp
	 * @param int $endTime End Timestamp
	 *
	 * @return Status
	 */
	public static function validateTimeRange( int $startTime, int $endTime ): Status {
		if ( $endTime <= 0 || $endTime < $startTime ) {
			return Status::newFatal( 'invalid_end_time' );
		}

		if ( $startTime < 0 ) {
			// Yes, nothing before 1970 exists.
			return Status::newFatal( 'invalid_start_time' );
		}

		if ( $startTime == $endTime ) {
			return Status::newFatal( 'invalid_start_end_time_equal' );
		}

		return Status::newGood();
	}

	/**
	 * Return the total new comps.
	 *
	 * @return int Total new comps.
	 */
	public function getTotalNew(): int {
		return (int)$this->reportData[ 'comp_new' ];
	}

	/**
	 * Return the total extended comps.
	 *
	 * @return int Total extended comps.
	 */
	public function getTotalExtended(): int {
		return (int)$this->reportData[ 'comp_extended' ];
	}

	/**
	 * Return the total failed comps.
	 *
	 * @return int Total failed comps.
	 */
	public function getTotalFailed(): int {
		return (int)$this->reportData[ 'comp_failed' ];
	}

	/**
	 * Return the total skipped comps.
	 *
	 * @return int Total skipped comps.
	 */
	public function getTotalSkipped(): int {
		return (int)$this->reportData[ 'comp_skipped' ];
	}

	/**
	 * Return the total comps actually performed.
	 *
	 * @return int Total comps actually performed.
	 */
	public function getTotalPerformed(): int {
		return (int)$this->reportData[ 'comp_performed' ];
	}

	/**
	 * Return the total users emailed.
	 *
	 * @return int Total users emailed.
	 */
	public function getTotalEmailed(): int {
		return (int)$this->reportData[ 'email_sent' ];
	}

	/**
	 * Is this report finished running?
	 *
	 * @return bool Report Finished
	 */
	public function isFinished(): int {
		return (bool)$this->reportData[ 'finished' ];
	}

	/**
	 * Set if the report is finished running.
	 *
	 * @param bool $finished Report Finished
	 *
	 * @return void
	 */
	public function setFinished( bool $finished = false ): void {
		$this->reportData['finished'] = (int)$finished;
	}

	/**
	 * Add new report row.
	 * Will overwrite existing rows with the same global ID.
	 *
	 * @param int $userId Global User ID
	 * @param int $points Aggegrate Points for the month range.
	 * @param bool $compNew Is this a new comp for this month range?(User did not have previously or consecutively.)
	 * @param bool $compExtended Is this an extended comp from a previous one?
	 * @param bool $compFailed Did the billing system fail to do the comp?(Or did we just not run it yet?)
	 * @param int $currentCompExpires Unix timestamp for when the current comp expires.
	 * @param int $newCompExpires Unix timestamp for when the new comp expires.(If applicable.)
	 * @param bool $compPerformed Was the new comp actually performed?
	 * @param bool $emailSent User emailed to let them know about their comp?
	 *
	 * @return void
	 */
	public function addRow(
		int $userId,
		int $points,
		bool $compNew,
		bool $compExtended,
		bool $compFailed,
		int $currentCompExpires,
		int $newCompExpires,
		bool $compPerformed = false,
		bool $emailSent = false
	): void {
		$data = [
			'user_id'				=> $userId,
			'points'				=> $points,
			'comp_new'				=> $compNew,
			'comp_extended'			=> $compExtended,
			'comp_failed'			=> $compFailed,
			'current_comp_expires'	=> $currentCompExpires,
			'new_comp_expires'		=> $newCompExpires,
			'comp_performed'		=> $compPerformed,
			'email_sent'			=> $emailSent
		];

		if ( empty( $data['user_id'] ) ) {
			throw new MWException( __METHOD__ . ': Invalid global user ID provided.' );
		}

		if ( isset( $this->reportUser[$userId] ) ) {
			$this->reportUser[$userId] = array_merge( $this->reportUser[$userId], $data );
		} else {
			$this->reportUser[$userId] = $data;
		}
	}

	/**
	 * Get the next row in the report data.
	 *
	 * @return mixed Report row data or false for no more values.
	 */
	public function getNextRow(): mixed {
		$return = current( $this->reportUser );
		next( $this->reportUser );
		return $return;
	}

	/**
	 * Run the report.
	 * Threshold, Start Time, and End Time are ignored if the report was already run previously.
	 * Their previous values will be used.
	 *
	 * @param int|null $minPointThreshold [Optional] Minimum Point Threshold
	 * @param int|null $maxPointThreshold [Optional] Maximum Point Threshold
	 * @param int $timeStart [Optional] Unix timestamp of the start time.
	 * @param int $timeEnd [Optional] Unix timestamp of the end time.
	 * @param bool $final [Optional] Actually run comps.
	 * @param bool $email [Optional] Send email to affected users.
	 *
	 * @return void
	 */
	public function run(
		?int $minPointThreshold = null,
		?int $maxPointThreshold = null,
		int $timeStart = 0,
		int $timeEnd = 0,
		bool $final = false,
		bool $email = false
	) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Subscription' ) ) {
			throw new MWException( __METHOD__ . ": Extension:Subscription must be loaded for this functionality." );
		}

		if ( $this->reportData['report_id'] > 0 ) {
			$minPointThreshold = $this->getMinPointThreshold();
			$maxPointThreshold = $this->getMaxPointThreshold();
			$timeStart = $this->getStartTime();
			$timeEnd = $this->getEndTime();
		}

		$config = MediaWikiServices::getInstance()->getMainConfig();

		if ( $minPointThreshold === null ) {
			$minPointThreshold = (int)$config->get( 'CompedSubscriptionThreshold' );
		}

		$status = self::validatePointThresholds( $minPointThreshold, $maxPointThreshold );
		if ( !$status->isGood() ) {
			throw new MWException( __METHOD__ . ': ' . $status->getMessage() );
		}

		if ( $timeEnd <= $timeStart || $timeStart == 0 || $timeEnd == 0 ) {
			throw new MWException( __METHOD__ . ': The time range is invalid.' );
		}

		$this->setMinPointThreshold( $minPointThreshold );
		$this->setMaxPointThreshold( $maxPointThreshold );
		$this->setStartTime( $timeStart );
		$this->setEndTime( $timeEnd );

		// Number of complimentary months someone is given.
		$compedSubscriptionMonths = (int)$config->get( 'CompedSubscriptionMonths' );
		$newExpiresDT = new DateTime( 'now' );
		$newExpiresDT->add( new DateInterval( 'P' . $compedSubscriptionMonths . 'M' ) );
		$newExpires = $newExpiresDT->getTimestamp();

		$gamepediaPro = MediaWikiServices::getInstance()->getService( GamepediaPro::class );

		$limit = 200;
		$filters = [
			'stat'		=> 'wiki_points',
			'limit'		=> $limit,
			'offset'	=> 0,
			'global'	=> true,
			'month'		=> $timeStart,
		];

		while ( true ) {
			$statMonthly = MediaWikiServices::getInstance()->getService( AchievementService::class )
				->getStatMonthlyCount( $filters );
			$finished = false;

			foreach ( $statMonthly as $monthly ) {
				$this->updateUser( $gamepediaPro, $newExpires, $final, $email, $monthly );

				if ( $monthly->getCount() < $this->getMinPointThreshold() ) {
					$finished = true;
					break;
				}
			}

			if ( $finished || count( $statMonthly ) < $limit ) {
				break;
			}
			$filters['offset'] += $limit;
		}
		$this->setFinished( true );
		$this->save();
	}

	/**
	 * Handle an individual user's stat count.
	 */
	private function updateUser(
		SubscriptionProvider $gamepediaPro,
		int $newExpires,
		bool $final,
		bool $email,
		CheevosStatMonthlyCount $monthly
	): void {
		$isExtended = false;

		if ( $monthly->getCount() < $this->getMinPointThreshold() ) {
			return;
		}

		$maxPointThreshold = $this->getMaxPointThreshold();
		if ( $maxPointThreshold !== null && $monthly->getCount() > $maxPointThreshold ) {
			return;
		}

		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $monthly->getUser_Id() );
		if ( !$user || $user->getId() < 1 ) {
			return;
		}

		$success = false;

		$subscription = $this->getSubscription( $user, $gamepediaPro );
		if ( $subscription['paid'] ) {
			// Do not mess with paid subscriptions.
			$this->addRow(
				$user->getId(),
				$monthly->getCount(),
				false,
				false,
				false,
				$subscription['expires'],
				0
			);
			return;
		} elseif ( $subscription['hasSubscription'] && $newExpires > $subscription['expires'] ) {
			$isExtended = true;
		}

		$emailSent = false;
		if ( $final ) {
			if ( $isExtended ) {
				$gamepediaPro->cancelCompedSubscription( $user->getId() );
			}
			$config = MediaWikiServices::getInstance()->getMainConfig();
			$comp = $gamepediaPro->createCompedSubscription(
				$user->getId(),
				(int)$config->get( 'CompedSubscriptionMonths' )
			);

			if ( $comp !== false ) {
				$success = true;
				if ( $email ) {
					$emailSent = $this->sendUserEmail( $user );
				}
			}
		}

		$this->addRow(
			$user->getId(),
			$monthly->getCount(),
			!$isExtended,
			$isExtended,
			!$success,
			(int)$subscription[ 'expires' ],
			$newExpires,
			$success,
			$emailSent
		);
	}

	/**
	 * Get current subscription status.
	 *
	 * @param UserIdentity $userIdentity User
	 * @param SubscriptionProvider $provider Subscription Provider
	 *
	 * @return array Array of boolean status flags.
	 */
	public function getSubscription( UserIdentity $userIdentity, SubscriptionProvider $provider ): array {
		$subscription = $provider->getSubscription( $userIdentity->getId() );
		if ( !is_array( $subscription ) ) {
			return [ 'hasSubscription' => false, 'paid' => false, 'expires' => null ];
		}

		$expires = $subscription['expires'] !== false ? (int)$subscription['expires']->getTimestamp( TS_UNIX ) : 0;
		return [
			'hasSubscription' => true,
			'paid' => $subscription['plan_id'] !== 'complimentary',
			'expires' => $expires
		];
	}

	/**
	 * Run through all users and comp subscriptions.
	 *
	 * @return void
	 */
	public function compAllSubscriptions(): void {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$compedSubscriptionMonths = (int)$config->get( 'CompedSubscriptionMonths' );
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		foreach ( $this->reportUser as $userId => $data ) {
			$this->compSubscription( $userFactory->newFromId( $userId ), $compedSubscriptionMonths );
		}
	}

	/**
	 * Create a subscription compensation in the billing service.
	 * Will fail if a valid paid or comped subscription already exists and is longer than the proposed new comp length.
	 *
	 * @return bool Success
	 */
	public function compSubscription( UserIdentity $userIdentity, int $numberOfMonths ): bool {
		$gamepediaPro = MediaWikiServices::getInstance()->getService( GamepediaPro::class );

		$newExpiresDT = new DateTime( 'now' );
		$newExpiresDT->add( new DateInterval( 'P' . $numberOfMonths . 'M' ) );
		$newExpires = $newExpiresDT->getTimestamp();

		$subscription = $this->getSubscription( $userIdentity, $gamepediaPro );
		if ( $subscription['paid'] === true ) {
			// Do not mess with paid subscriptions.
			return false;
		}

		if ( $subscription['hasSubscription'] && $newExpires > $subscription['expires'] ) {
			$gamepediaPro->cancelCompedSubscription( $userIdentity->getId() );
		}

		$comp = $gamepediaPro->createCompedSubscription( $userIdentity->getId(), $numberOfMonths );

		if ( $comp !== false ) {
			$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
			$db->update(
				'points_comp_report_user',
				[
					'comp_failed'		=> 0,
					'comp_performed'	=> 1
				],
				[
					'report_id'	=> $this->reportData['report_id'],
					'user_id'	=> $userIdentity->getId()
				],
				__METHOD__
			);
			$this->updateStats();
			return true;
		}
		return false;
	}

	/**
	 * Run through all users and send emails.
	 *
	 * @return void
	 */
	public function sendAllEmails(): void {
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		foreach ( $this->reportUser as $userId => $data ) {
			$this->sendUserEmail( $userFactory->newFromId( $userId ) );
		}
	}

	/**
	 * Send user comp email.
	 *
	 * @param User $user
	 *
	 * @return bool Success
	 */
	public function sendUserEmail( User $user ): bool {
		$success = false;

		$body = [
			'text' => wfMessage( 'automatic_comp_email_body_text', $user->getName() )->text(),
			'html' => wfMessage( 'automatic_comp_email_body', $user->getName() )->text()
		];
		$status = $user->sendMail( wfMessage( 'automatic_comp_email_subject' )->parse(), $body );
		if ( $status->isGood() ) {
			$success = true;
		}

		if ( $success ) {
			$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
			$success = $db->update(
				'points_comp_report_user',
				[ 'email_sent' => 1 ],
				[
					'report_id'	=> $this->reportData['report_id'],
					'user_id'	=> $user->getId()
				],
				__METHOD__
			);
			$this->updateStats();
		}

		return $success;
	}

	/**
	 * Get the number of active subscriptions.
	 *
	 * @return int Number of active subscriptions.
	 */
	public static function getNumberOfActiveSubscriptions(): int {
		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		return $db->selectRowCount(
			[ 'points_comp_report_user' ],
			[ 'user_id' ],
			[
				'comp_performed' => 1,
				"current_comp_expires > " . time() . " OR new_comp_expires > " . time()
			],
			__METHOD__,
			[
				'GROUP BY'	=> 'user_id'
			]
		);
	}
}
