<?php 
namespace blog\system\cronjob;
use wcf\data\cronjob\Cronjob;
use wcf\data\user\User;
use wcf\data\user\UserList;
use wcf\data\uzbot\UzbotEditor;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\system\background\BackgroundQueueHandler;
use wcf\system\background\uzbot\NotifyScheduleBackgroundJob;
use wcf\system\cache\builder\UzbotValidBotCacheBuilder;
use wcf\system\cronjob\AbstractCronjob;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\language\LanguageFactory;
use wcf\system\WCF;

/**
 * Cronjob for Top Blogger for Bot.
 * 
 * @author		2014-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.wcf.bot3.blog
 */
class UzbotBlogTopBloggerCronjob extends AbstractCronjob {
	/**
	 * @inheritDoc
	 */
	public function execute(Cronjob $cronjob) {
		parent::execute($cronjob);
		
		if (!MODULE_UZBOT) return;
		
		// Read all active, valid activity bots, abort if none
		$bots = UzbotValidBotCacheBuilder::getInstance()->getData(array('typeDes' => 'blog_topBlogger'));
		if (empty($bots)) return;
		
		$defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());
		
		// Step through all bots and get top blogger
		foreach ($bots as $bot) {
			// set first next if 0
			if (!$bot->topBloggerNext) {
				$month = date('n');
				$year = date('Y');
				
				switch ($bot->topBloggerInterval) {
					case 1:
						$next = strtotime('next Monday');
						break;
					case 2:
						$next = gmmktime(0, 0, 0, $month != 12 ? $month + 1 : 1, 1, $month != 12 ? $year : $year + 1);
						break;
					case 3:
						if ($months >= 10) $next = gmmktime(0, 0, 0, 1, 1, $year + 1);
						elseif ($months >= 7) $next = gmmktime(0, 0, 0, 10, 1, $year);
						elseif ($months >= 4) $next = gmmktime(0, 0, 0, 7, 1, $year);
						else $next = gmmktime(0, 0, 0, 4, 1, $year);
						break;
				}
				
				$editor = new UzbotEditor($bot);
				$editor->update(['topBloggerNext' => $next]);
				UzbotEditor::resetCache();
				
				$bot->topBloggerNext = $next;
			}
			
			// leave if time does not match, unless test mode
			if (!$bot->testMode) {
				if ($bot->topBloggerNext > TIME_NOW) continue;
			}
			
			// must execute
			$end = $bot->topBloggerNext;
			$month = date('n');
			$year = date('Y');
			
			switch ($bot->topBloggerInterval) {
				case 1:
					$start  = $end - 7 * 86400;
					$next = $end + 7 * 86400;
					break;
				case 2:
					$start = gmmktime(0, 0, 0, $month > 1 ? $month - 1 : 12, 1, $month > 1 ? $year : $year - 1);
					$next = gmmktime(0, 0, 0, $month != 12 ? $month + 1 : 1, 1, $month != 12 ? $year : $year + 1);
					break;
				case 3:
					$start = gmmktime(0, 0, 0, $month > 3 ? $month - 3 : 12, 1, $month > 3 ? $year : $year - 1);
					if ($months >= 10) $next = gmmktime(0, 0, 0, 1, 1, $year + 1);
					elseif ($months >= 7) $next = gmmktime(0, 0, 0, 10, 1, $year);
					elseif ($months >= 4) $next = gmmktime(0, 0, 0, 7, 1, $year);
					else $next = gmmktime(0, 0, 0, 4, 1, $year);
					break;
			}
			
			// update bot, unless test mode
			if (!$bot->testMode) {
				$editor = new UzbotEditor($bot);
				$editor->update(['topBloggerNext' => $next]);
				UzbotEditor::resetCache();
			}
			
			// get top blogger
			$affectedUserIDs = $countToUserID = $placeholders = $userIDs = [];
			$rank = 0;
			
			$conditions = $bot->getUserConditions();
			if (count($conditions)) {
				$userList = new UserList();
				foreach ($conditions as $condition) {
					$condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
				}
				$userList->readObjectIDs();
				$userIDs = $userList->getObjectIDs();
			}
			
			$conditionBuilder = new PreparedStatementConditionBuilder();
			if (count($userIDs)) $conditionBuilder->add('entry.userID IN (?)', [$userIDs]);
			else $conditionBuilder->add('entry.userID > ?', [0]);
			$conditionBuilder->add('entry.isDeleted = ?', [0]);
			$conditionBuilder->add('entry.isDraft = ?', [0]);
			$conditionBuilder->add('entry.isPublished = ?', [1]);
			$conditionBuilder->add('entry.time > ?', [$start]);
			$conditionBuilder->add('entry.time < ?', [$end]);
			
			$sql = "SELECT 		userID as topID, COUNT(*) as count
					FROM		blog".WCF_N."_entry entry
					".$conditionBuilder."
					GROUP BY	topID
					ORDER BY	count DESC";
			
			$statement = WCF::getDB()->prepareStatement($sql, $bot->topBloggerCount);
			$statement->execute($conditionBuilder->getParameters());
			while ($row = $statement->fetchArray()) {
				$rank ++;
				$affectedUserIDs[] = $row['topID'];
				$countToUserID[$row['topID']] = $row['count'];
				$placeholders['ranks'][$row['topID']] = $rank;
			}
			
			// data
			if ($bot->enableLog) {
				if (!$bot->testMode) {
					UzbotLogEditor::create([
							'bot' => $bot,
							'count' => count($affectedUserIDs),
							'additionalData' => $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.user.affected', [
									'total' => count($affectedUserIDs),
									'userIDs' => implode(', ', $affectedUserIDs)
							])
					]);
				}
				else {
					$result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.test', [
							'objects' => count($affectedUserIDs),
							'users' => count($affectedUserIDs),
							'userIDs' => implode(', ', $affectedUserIDs)
					]);
					if (mb_strlen($result) > 64000) $result = mb_substr($result, 0, 64000) . ' ...';
					UzbotLogEditor::create([
							'bot' => $bot,
							'count' => count($affectedUserIDs),
							'testMode' => 1,
							'additionalData' => serialize(['', '', $result])
					]);
				}
			}
			
			// notification
			if (!count($affectedUserIDs)) continue;
			
			$notify = $bot->checkNotify(true, true);
			if ($notify === null) continue;
			
			$placeholders['date-from'] = $placeholders['time-from'] = $start;
			$placeholders['date-to'] = $placeholders['time-to'] = $end - 1;
			
			// send to scheduler
			$data = [
					'bot' => $bot,
					'placeholders' => $placeholders,
					'affectedUserIDs' => $affectedUserIDs,
					'countToUserID' => $countToUserID
			];
			
			$job = new NotifyScheduleBackgroundJob($data);
			BackgroundQueueHandler::getInstance()->performJob($job);
		}
	}
}
