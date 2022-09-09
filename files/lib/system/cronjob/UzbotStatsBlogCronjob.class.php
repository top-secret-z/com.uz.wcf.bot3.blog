<?php 
namespace blog\system\cronjob;
use blog\data\blog\Blog;
use wcf\data\cronjob\Cronjob;
use wcf\data\uzbot\stats\UzbotStats;
use wcf\data\uzbot\stats\UzbotStatsEditor;
use wcf\system\background\BackgroundQueueHandler;
use wcf\system\background\uzbot\NotifyScheduleBackgroundJob;
use wcf\system\cache\builder\UzbotValidBotCacheBuilder;
use wcf\system\cronjob\AbstractCronjob;
use wcf\system\WCF;

/**
 * Cronjob for Blog Stats for Bot.
 * 
 * @author		2014-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.wcf.bot3.blog
 */
class UzbotStatsBlogCronjob extends AbstractCronjob {
	/**
	 * @inheritDoc
	 */
	public function execute(Cronjob $cronjob) {
		parent::execute($cronjob);
		
		if (!MODULE_UZBOT) return;
		
		// always create stats
		
		// read data
		$statsOld = new UzBotStats(1);
		$stats = new UzBotStats(1);
		
		// Make new stats
		// Blog
		$sql = "SELECT	COUNT(*) as blog,
						COALESCE(SUM(isFeatured), 0) AS blogFeatured,
						COALESCE(SUM(comments), 0) AS blogComments,
						COALESCE(SUM(cumulativeLikes), 0) AS blogLikes,
						COALESCE(SUM(views), 0) AS blogViews,
						COALESCE(SUM(subscribers), 0) AS blogSubscribers
				FROM 	blog".WCF_N."_blog";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute();
		$row = $statement->fetchArray();
		$stats->blog = $row['blog'];
		$stats->blogFeatured = $row['blogFeatured'];
		$stats->blogComments = $row['blogComments'];
		$stats->blogLikes = $row['blogLikes'];
		$stats->blogSubscribers = $row['blogSubscribers'];
		$stats->blogViews = $row['blogViews'];
		
		$sql = "SELECT	COUNT(*) as total
				FROM 	blog".WCF_N."_blog
				WHERE	accessLevel = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute([Blog::ACCESS_EVERYONE]);
		$row = $statement->fetchArray();
		$stats->blogAccessEveryone = $row['total'];
		
		$sql = "SELECT	COUNT(*) as total
				FROM 	blog".WCF_N."_blog
				WHERE	accessLevel = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute([Blog::ACCESS_REGISTERED]);
		$row = $statement->fetchArray();
		$stats->blogAccessRegistered = $row['total'];
		
		$sql = "SELECT	COUNT(*) as total
				FROM 	blog".WCF_N."_blog
				WHERE	accessLevel = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute([Blog::ACCESS_FOLLOWING]);
		$row = $statement->fetchArray();
		$stats->blogAccessFollowing = $row['total'];
		
		$sql = "SELECT	COUNT(*) as total
				FROM 	blog".WCF_N."_blog
				WHERE	accessLevel = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute([Blog::ACCESS_OWNER]);
		$row = $statement->fetchArray();
		$stats->blogAccessOwner = $row['total'];
		
		// Blog Entries
		$sql = "SELECT	COUNT(*) as blogEntry,
						COALESCE(SUM(isDeleted), 0) AS blogEntryDeleted,
						COALESCE(SUM(isDisabled), 0) AS blogEntryDisabled,
						COALESCE(SUM(isDraft), 0) AS blogEntryDraft,
						COALESCE(SUM(isFeatured), 0) AS blogEntryFeatured,
						COALESCE(SUM(isPublished), 0) AS blogEntryPublished,
						COALESCE(SUM(comments), 0) AS blogEntryComments,
						COALESCE(SUM(cumulativeLikes), 0) AS blogEntryLikes,
						COALESCE(SUM(views), 0) AS blogEntryViews
				FROM 	blog".WCF_N."_entry";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute();
		$row = $statement->fetchArray();
		$stats->blogEntry = $row['blogEntry'];
		$stats->blogEntryDeleted = $row['blogEntryDeleted'];
		$stats->blogEntryDisabled = $row['blogEntryDisabled'];
		$stats->blogEntryDraft = $row['blogEntryDraft'];
		$stats->blogEntryFeatured = $row['blogEntryFeatured'];
		$stats->blogEntryPublished = $row['blogEntryPublished'];
		$stats->blogEntryComments = $row['blogEntryComments'];
		$stats->blogEntryLikes = $row['blogEntryLikes'];
		$stats->blogEntryViews = $row['blogEntryViews'];
		
		$sql = "SELECT	COUNT(*) as total
				FROM 	blog".WCF_N."_entry
				WHERE	accessLevel = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute([Blog::ACCESS_EVERYONE]);
		$row = $statement->fetchArray();
		$stats->blogEntryAccessEveryone = $row['total'];
		
		$sql = "SELECT	COUNT(*) as total
				FROM 	blog".WCF_N."_entry
				WHERE	accessLevel = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute([Blog::ACCESS_REGISTERED]);
		$row = $statement->fetchArray();
		$stats->blogEntryAccessRegistered = $row['total'];
		
		$sql = "SELECT	COUNT(*) as total
				FROM 	blog".WCF_N."_entry
				WHERE	accessLevel = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute([Blog::ACCESS_FOLLOWING]);
		$row = $statement->fetchArray();
		$stats->blogEntryAccessFollowing = $row['total'];
		
		$sql = "SELECT	COUNT(*) as total
				FROM 	blog".WCF_N."_entry
				WHERE	accessLevel = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute([Blog::ACCESS_OWNER]);
		$row = $statement->fetchArray();
		$stats->blogEntryAccessOwner = $row['total'];
		
		$sql = "SELECT	COUNT(*) as total
				FROM 	blog".WCF_N."_entry
				WHERE	pollID IS NOT NULL";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute();
		$row = $statement->fetchArray();
		$stats->blogEntryPoll = $row['total'];
		
		// don't update stats here
		
		// Read all active, valid activity bots, abort if none
		$bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'blog_statistics']);
		if (!count($bots)) return;
		
		$result = [
				'blog' => $stats->blog,
				'blogOld' => $statsOld->blog,
				'blogAccessEveryone' => $stats->blogAccessEveryone,
				'blogAccessEveryoneOld' => $statsOld->blogAccessEveryone,
				'blogAccessRegistered' => $stats->blogAccessRegistered,
				'blogAccessRegisteredOld' => $statsOld->blogAccessRegistered,
				'blogAccessFollowing' => $stats->blogAccessFollowing,
				'blogAccessFollowingOld' => $statsOld->blogAccessFollowing,
				'blogAccessOwner' => $stats->blogAccessOwner,
				'blogAccessOwnerOld' => $statsOld->blogAccessOwner,
				'blogFeatured' => $stats->blogFeatured,
				'blogFeaturedOld' => $statsOld->blogFeatured,
				'blogComments' => $stats->blogComments,
				'blogCommentsOld' => $statsOld->blogComments,
				'blogLikes' => $stats->blogLikes,
				'blogLikesOld' => $statsOld->blogLikes,
				'blogSubscribers' => $stats->blogSubscribers,
				'blogSubscribersOld' => $statsOld->blogSubscribers,
				'blogViews' => $stats->blogViews,
				'blogViewsOld' => $statsOld->blogViews,
				
				'blogEntry' => $stats->blogEntry,
				'blogEntryOld' => $statsOld->blogEntry,
				'blogEntryAccessEveryone' => $stats->blogEntryAccessEveryone,
				'blogEntryAccessEveryoneOld' => $statsOld->blogEntryAccessEveryone,
				'blogEntryAccessRegistered' => $stats->blogEntryAccessRegistered,
				'blogEntryAccessRegisteredOld' => $statsOld->blogEntryAccessRegistered,
				'blogEntryAccessFollowing' => $stats->blogEntryAccessFollowing,
				'blogEntryAccessFollowingOld' => $statsOld->blogEntryAccessFollowing,
				'blogEntryAccessOwner' => $stats->blogEntryAccessOwner,
				'blogEntryAccessOwnerOld' => $statsOld->blogEntryAccessOwner,
				'blogEntryDeleted' => $stats->blogEntryDeleted,
				'blogEntryDeletedOld' => $statsOld->blogEntryDeleted,
				'blogEntryDisabled' => $stats->blogEntryDisabled,
				'blogEntryDisabledOld' => $statsOld->blogEntryDisabled,
				'blogEntryDraft' => $stats->blogEntryDraft,
				'blogEntryDraftOld' => $statsOld->blogEntryDraft,
				'blogEntryFeatured' => $stats->blogEntryFeatured,
				'blogEntryFeaturedOld' => $statsOld->blogEntryFeatured,
				'blogEntryPoll' => $stats->blogEntryPoll,
				'blogEntryPollOld' => $statsOld->blogEntryPoll,
				'blogEntryPublished' => $stats->blogEntryPublished,
				'blogEntryPublishedOld' => $statsOld->blogEntryPublished,
				'blogEntryComments' => $stats->blogEntryComments,
				'blogEntryCommentsOld' => $statsOld->blogEntryComments,
				'blogEntryLikes' => $stats->blogEntryLikes,
				'blogEntryLikesOld' => $statsOld->blogEntryLikes,
				'blogEntryViews' => $stats->blogEntryViews,
				'blogEntryViewsOld' => $statsOld->blogEntryViews
		];
		
		$placeholders['stats'] = $result;
		$placeholders['stats-lang'] = 'wcf.uzbot.stats.blog';
		$placeholders['date-from'] = $statsOld->timeBlog;
		$placeholders['time-from'] = $statsOld->timeBlog;
		$placeholders['date-to'] = TIME_NOW;
		$placeholders['time-to'] = TIME_NOW;
		
		// Step through all bots and get updates
		foreach ($bots as $bot) {
			// update stats unless test mode
			if (!$bot->testMode) {
				$editor = new UzbotStatsEditor($stats);
					$editor->update([
							'blog' => $stats->blog,
							'blogAccessEveryone' => $stats->blogAccessEveryone,
							'blogAccessRegistered' => $stats->blogAccessRegistered,
							'blogAccessFollowing' => $stats->blogAccessFollowing,
							'blogAccessOwner' => $stats->blogAccessOwner,
							'blogFeatured' => $stats->blogFeatured,
							'blogComments' => $stats->blogComments,
							'blogLikes' => $stats->blogLikes,
							'blogSubscribers' => $stats->blogSubscribers,
							'blogViews' => $stats->blogViews,
							'blogEntry' => $stats->blogEntry,
							'blogEntryAccessEveryone' => $stats->blogEntryAccessEveryone,
							'blogEntryAccessRegistered' => $stats->blogEntryAccessRegistered,
							'blogEntryAccessFollowing' => $stats->blogEntryAccessFollowing,
							'blogEntryAccessOwner' => $stats->blogEntryAccessOwner,
							'blogEntryDeleted' => $stats->blogEntryDeleted,
							'blogEntryDisabled' => $stats->blogEntryDisabled,
							'blogEntryDraft' => $stats->blogEntryDraft,
							'blogEntryFeatured' => $stats->blogEntryFeatured,
							'blogEntryPoll' => $stats->blogEntryPoll,
							'blogEntryPublished' => $stats->blogEntryPublished,
							'blogEntryComments' => $stats->blogEntryComments,
							'blogEntryLikes' => $stats->blogEntryLikes,
							'blogEntryViews' => $stats->blogEntryViews,
							'timeBlog' => TIME_NOW
					]);
			}
			
			// send to scheduler
			$notify = $bot->checkNotify(true, true);
			if ($notify === null) continue;
				
			$data = [
					'bot' => $bot,
					'placeholders' => $placeholders,
					'affectedUserIDs' => [],
					'countToUserID' => []
			];
				
			$job = new NotifyScheduleBackgroundJob($data);
			BackgroundQueueHandler::getInstance()->performJob($job);
		}
		
	}
}
