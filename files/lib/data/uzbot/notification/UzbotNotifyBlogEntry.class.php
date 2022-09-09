<?php 
namespace blog\data\uzbot\notification;
use blog\data\blog\Blog;
use blog\data\entry\EntryAction;
use wcf\data\uzbot\Uzbot;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\system\exception\SystemException;
use wcf\system\html\input\HtmlInputProcessor;
use wcf\system\language\LanguageFactory;
use wcf\system\WCF;
use wcf\util\MessageUtil;
use wcf\util\StringUtil;

/**
 * Creates threads for Bot
 * 
 * @author		2014-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.wcf.bot3.blog
 */
class UzbotNotifyBlogEntry {
	public function send(Uzbot $bot, $content, $subject, $teaser, $language, $receiver, $tags) {
		// prepare text and data
		$defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());
		
		$content = MessageUtil::stripCrap($content);
		$subject = MessageUtil::stripCrap(StringUtil::stripHTML($subject));
		if (mb_strlen($subject) > 255) $subject = mb_substr($subject, 0, 250) . '...';
		
		// set publication time
		$publicationTime = TIME_NOW;
		if (isset($bot->publicationTime) && $bot->publicationTime) {
			$publicationTime = $bot->publicationTime;
		}
		
		if (!$bot->testMode) {
			$htmlInputProcessor = new HtmlInputProcessor();
			$htmlInputProcessor->process($content, 'com.woltlab.blog.entry', 0);
			
			// get blog / notification data
			$blogEntryData = unserialize($bot->blogEntryData);
			
			// tags to include feedreader
			if (!MODULE_TAGGING) {
				$tags = [];
			}
			else {
				if (isset($bot->feedreaderUseTags) && $bot->feedreaderUseTags) {
					if (isset($bot->feedreaderTags) && !empty($bot->feedreaderTags)) {
						$tags = array_unique(array_merge($tags, $bot->feedreaderTags));
					}
				}
			}
			
			// create entry
			// preset blogID (automtic)
			$blogID = $bot->blogID;
			if ($blogEntryData['blogID']) $blogID = $blogEntryData['blogID'];
			
			try {
				$blog = new Blog($blogID);
				
				$data = [
						'blogID' => $blog->blogID,
						'languageID' => $language->languageID,
						'accessLevel' => $blog->accessLevel,
						'subject' => $subject,
						'time' => $publicationTime,
						'userID' => $bot->senderID,
						'username' => $bot->sendername,
						'enableComments' => $blogEntryData['enableComments'],
						'isDisabled' => $blogEntryData['isDisabled'],
						'isFeatured' => $blogEntryData['isFeatured'],
						'isDraft' => $blogEntryData['isDraft'],
						'isPublished' => $blogEntryData['isDraft'] ? 0 :1,
						'isUzbot' => 1
				];
				
				$entryData = [
						'data' => $data,
						'categoryIDs' => unserialize($blogEntryData['categoryIDs']),
						'attachmentHandler' => null,
						'htmlInputProcessor' => $htmlInputProcessor,
						'tags' => $tags
				];
				
				$this->objectAction = new EntryAction([], 'create', $entryData);
				$resultValues = $this->objectAction->executeAction();
			}
			catch (SystemException $e) {
				// users may get lost; check sender again to abort
				if (!$bot->checkSender(true, true)) return false;
				
				// report any other error und continue
				if ($bot->enableLog) {
					$error = $defaultLanguage->get('wcf.acp.uzbot.log.notify.error') . ' ' . $e->getMessage();
					
					UzbotLogEditor::create([
							'bot' => $bot,
							'status' => 1,
							'count' => 1,
							'additionalData' => $error
					]);
				}
			}
		}
		else {
			if (mb_strlen($content) > 63500) $content = mb_substr($content, 0, 63500) . ' ...';
			$result = serialize([$subject, $teaser, $content]);
			
			UzbotLogEditor::create([
					'bot' => $bot,
					'count' => 1,
					'testMode' => 1,
					'additionalData' => $result
			]);
		}
	}
}
