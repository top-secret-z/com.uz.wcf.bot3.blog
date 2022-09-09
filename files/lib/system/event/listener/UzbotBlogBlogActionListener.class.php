<?php
namespace blog\system\event\listener;
use blog\data\blog\Blog;
use wcf\data\user\User;
use wcf\data\user\UserList;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\system\background\BackgroundQueueHandler;
use wcf\system\background\uzbot\NotifyScheduleBackgroundJob;
use wcf\system\cache\builder\UzbotValidBotCacheBuilder;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\language\LanguageFactory;
use wcf\system\WCF;

/**
 * Listen to blog actions for Bot
 * 
 * @author		2014-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.wcf.bot3.blog
 */
class UzbotBlogBlogActionListener implements IParameterizedEventListener {
	/**
	 * @inheritDoc
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		// check module
		if (!MODULE_UZBOT) return;
		
		$defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());
		$action = $eventObj->getActionName();
		
		// Blog publication on create
		if ($action == 'create') {
			// no guests
			if (!WCF::getUser()->userID) return;
			
			// get blog data
			$params = $eventObj->getParameters();
			$accessLevel = $params['data']['accessLevel'];
			$authors = $params['authors'];
			$retValues = $eventObj->getReturnValues();
			$blog = $retValues['returnValues'];
			
			// new blog
			// Read all active, valid activity bots, abort if none
			$bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'blog_blogNew']);
			if (count($bots)) {
				foreach ($bots as $bot) {
					$affectedUserIDs = $countToUserID = $placeholders = [];
					$count = 1;
					
					// check for conditions, if exist, no guests
					$conditions = $bot->getUserConditions();
					if (count($conditions)) {
						$userList = new UserList();
						$userList->getConditionBuilder()->add('user_table.userID = ?', [WCF::getUser()->userID]);
						foreach ($conditions as $condition) {
							$condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
						}
						$userList->readObjectIDs();
						if (!count($userList->getObjectIDs())) continue;
					}
					
					$affectedUserIDs[] = WCF::getUser()->userID;
					$countToUserID[WCF::getUser()->userID] = WCF::getUser()->blogs;
					
					// save blogID for entry in affected blog, support open
					$bot->blogID = $blog->blogID;
					
					// get access level string
					$access = 'wcf.uzbot.blog.access.';
					if ($accessLevel == Blog::ACCESS_EVERYONE) $access .= 'everyone';
					elseif ($accessLevel == Blog::ACCESS_REGISTERED) $access .= 'registered';
					elseif ($accessLevel == Blog::ACCESS_FOLLOWING) $access .= 'following';
					elseif ($accessLevel == Blog::ACCESS_OWNER) $access .= 'nobody';
					
					// get authors
					$authors = $blog->getAuthors();
					$names = [];
					if (count($authors)) {
						foreach ($authors as $author) {
							$names[] = $author->username;
						}
					}
					
					$placeholders['count'] = 1;
					$placeholders['count-user'] = WCF::getUser()->blogs + 1;
					$placeholders['blog-access'] = $access;
					$placeholders['blog-authors'] = count($names) ? implode(', ', $names) : ' ';
					$placeholders['blog-id'] = $blog->blogID;
					$placeholders['blog-link'] = $blog->getLink();
					$placeholders['blog-title'] = $blog->getTitle();
					$placeholders['translate'] = ['blog-access'];
					
					// log action
					if ($bot->enableLog) {
						if (!$bot->testMode) {
							UzbotLogEditor::create([
									'bot' => $bot,
									'count' => 1,
									'additionalData' => $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.user.affected', [
											'total' => 1,
											'userIDs' => implode(', ', $affectedUserIDs)
									])
							]);
						}
						else {
							$result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.test', [
									'objects' => 1,
									'users' => count($affectedUserIDs),
									'userIDs' => implode(', ', $affectedUserIDs)
							]);
							if (mb_strlen($result) > 64000) $result = mb_substr($result, 0, 64000) . ' ...';
							UzbotLogEditor::create([
									'bot' => $bot,
									'count' => 1,
									'testMode' => 1,
									'additionalData' => serialize(['', '', $result])
							]);
						}
					}
					
					// check for and prepare notification
					$notify = $bot->checkNotify(true, true);
					if ($notify === null) continue;
					
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
		
		// blog change by user
		elseif ($action == 'update' || $action == 'delete' || $action == 'setAsFeatured' || $action == 'unsetAsFeatured') {
			$blog = $eventObj->getObjects()[0]->getDecoratedObject();
			
			if ($blog->userID && WCF::getUser()->userID == $blog->userID) {
				$bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'blog_blogChange']);
				
				if (count($bots)) {
					//preset data
					$user = WCF::getUser();
					
					// get reason
					$params = $eventObj->getParameters();
					$reason = '';
					if (isset($params['reason'])) $reason = $params['reason'];
					elseif (isset($params['data']['editReason'])) $reason = $params['data']['editReason'];
					
					foreach ($bots as $bot) {
						$affectedUserIDs = $countToUserID = $placeholders = [];
						$count = 1;
						
						$conditions = $bot->getUserConditions();
						if (count($conditions)) {
							$userList = new UserList();
							$userList->getConditionBuilder()->add('user_table.userID = ?', [$user->userID]);
							foreach ($conditions as $condition) {
								$condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
							}
							$userList->readObjectIDs();
							if (!count($userList->getObjectIDs())) continue;
						}
						
						// found one
						$affectedUserIDs[] = $user->userID;
						$countToUserID[$user->userID] = $user->blogs;
						
						// get access level string
						$access = 'wcf.uzbot.blog.access.';
						$accessLevel = $blog->accessLevel;
						
						if ($accessLevel == Blog::ACCESS_EVERYONE) $access .= 'everyone';
						elseif ($accessLevel == Blog::ACCESS_REGISTERED) $access .= 'registered';
						elseif ($accessLevel == Blog::ACCESS_FOLLOWING) $access .= 'following';
						elseif ($accessLevel == Blog::ACCESS_OWNER) $access .= 'nobody';
						
						// get authors
						$names = [];
						$authors = $blog->getAuthors();
						if (count($authors)) {
							foreach ($authors as $author) {
								$names[] = $author->username;
							}
						}
						
						// set placeholders
						$placeholders['action'] = 'wcf.uzbot.blog.action.' . $action;
						$placeholders['blog-access'] = $access;
						$placeholders['blog-authors'] = count($names) ? implode(', ', $names) : ' ';
						$placeholders['blog-id'] = $blog->blogID;
						$placeholders['blog-link'] = $blog->getLink();
						$placeholders['blog-title'] = $blog->getTitle();
						$placeholders['count'] = 1;
						$placeholders['count-user'] = $user->blogs;
						$placeholders['translate'] = ['action', 'blog-access'];
						
						// save blogID for entry in affected blog
						$bot->blogID = $blog->blogID;
						
						// log action
						if ($bot->enableLog) {
							if (!$bot->testMode) {
								UzbotLogEditor::create([
										'bot' => $bot,
										'count' => 1,
										'additionalData' => $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.user.affected', [
												'total' => 1,
												'userIDs' => implode(', ', $affectedUserIDs)
										])
								]);
							}
							else {
								$result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.test', [
										'objects' => 1,
										'users' => count($affectedUserIDs),
										'userIDs' => implode(', ', $affectedUserIDs)
								]);
								if (mb_strlen($result) > 64000) $result = mb_substr($result, 0, 64000) . ' ...';
								UzbotLogEditor::create([
										'bot' => $bot,
										'count' => 1,
										'testMode' => 1,
										'additionalData' => serialize(['', '', $result])
								]);
							}
						}
						
						// check for and prepare notification
						$notify = $bot->checkNotify(true, true);
						if ($notify === null) continue;
						
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
		}
	}
}
