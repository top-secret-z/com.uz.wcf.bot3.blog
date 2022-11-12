<?php

/*
 * Copyright by Udo Zaydowicz.
 * Modified by SoftCreatR.dev.
 *
 * License: http://opensource.org/licenses/lgpl-license.php
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
namespace blog\system\event\listener;

use blog\data\blog\Blog;
use wcf\data\user\User;
use wcf\data\user\UserList;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\data\uzbot\top\UzbotTop;
use wcf\data\uzbot\top\UzbotTopEditor;
use wcf\system\background\BackgroundQueueHandler;
use wcf\system\background\uzbot\NotifyScheduleBackgroundJob;
use wcf\system\cache\builder\UzbotValidBotCacheBuilder;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\language\LanguageFactory;
use wcf\system\WCF;

/**
 * Listen to blog entry actions for Bot
 */
class UzbotBlogEntryActionListener implements IParameterizedEventListener
{
    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        // check module
        if (!MODULE_UZBOT) {
            return;
        }

        $defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());

        // entry publication
        if ($eventObj->getActionName() == 'triggerPublication') {
            // no guests
            if (!WCF::getUser()->userID) {
                return;
            }

            // get entry
            $entry = $eventObj->getObjects()[0]->getDecoratedObject();

            // new entry
            // Read all active, valid activity bots, abort if none
            $bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'blog_entryNew']);
            if (\count($bots)) {
                // disregard uzbot
                if ($entry->isUzbot) {
                    return;
                }

                foreach ($bots as $bot) {
                    $affectedUserIDs = $countToUserID = $placeholders = [];
                    $count = 1;

                    // check for conditions, if exist, no guests
                    $conditions = $bot->getUserConditions();
                    if (\count($conditions)) {
                        $userList = new UserList();
                        $userList->getConditionBuilder()->add('user_table.userID = ?', [WCF::getUser()->userID]);
                        foreach ($conditions as $condition) {
                            $condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
                        }
                        $userList->readObjectIDs();
                        if (!\count($userList->getObjectIDs())) {
                            continue;
                        }
                    }

                    if ($entry->userID) {
                        $affectedUserIDs[] = $entry->userID;
                        $countToUserID[$entry->userID] = WCF::getUser()->blogEntries;
                    }

                    // save blogID for entry in affected blog
                    $bot->blogID = $entry->blogID;

                    // get access level string
                    $access = 'wcf.user.access.';
                    if ($entry->accessLevel == Blog::ACCESS_EVERYONE) {
                        $access .= 'everyone';
                    } elseif ($entry->accessLevel == Blog::ACCESS_REGISTERED) {
                        $access .= 'registered';
                    } elseif ($entry->accessLevel == Blog::ACCESS_FOLLOWING) {
                        $access .= 'following';
                    } elseif ($entry->accessLevel == Blog::ACCESS_OWNER) {
                        $access .= 'nobody';
                    }

                    // set placeholder
                    $blog = $entry->getBlog();

                    // get authors
                    $authors = $blog->getAuthors();
                    $names = [];
                    if (\count($authors)) {
                        foreach ($authors as $author) {
                            $names[] = $author->username;
                        }
                    }

                    $placeholders['count'] = 1;
                    $placeholders['count-user'] = WCF::getUser()->blogEntries + 1;
                    $placeholders['blog-access'] = $access;
                    $placeholders['blog-authors'] = \count($names) ? \implode(', ', $names) : ' ';
                    $placeholders['blog-id'] = $blog->blogID;
                    $placeholders['blog-link'] = $blog->getLink();
                    $placeholders['blog-title'] = $blog->getTitle();
                    $placeholders['entry-id'] = $entry->entryID;
                    $placeholders['entry-link'] = $entry->getLink();
                    $placeholders['entry-teaser'] = $entry->getExcerpt();
                    $placeholders['entry-text'] = $entry->getMessage();
                    $placeholders['entry-title'] = $entry->getTitle();
                    $placeholders['translate'] = ['blog-access'];

                    // log action
                    if ($bot->enableLog) {
                        if (!$bot->testMode) {
                            UzbotLogEditor::create([
                                'bot' => $bot,
                                'count' => 1,
                                'additionalData' => $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.user.affected', [
                                    'total' => 1,
                                    'userIDs' => \implode(', ', $affectedUserIDs),
                                ]),
                            ]);
                        } else {
                            $result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.test', [
                                'objects' => 1,
                                'users' => \count($affectedUserIDs),
                                'userIDs' => \implode(', ', $affectedUserIDs),
                            ]);
                            if (\mb_strlen($result) > 64000) {
                                $result = \mb_substr($result, 0, 64000) . ' ...';
                            }
                            UzbotLogEditor::create([
                                'bot' => $bot,
                                'count' => 1,
                                'testMode' => 1,
                                'additionalData' => \serialize(['', '', $result]),
                            ]);
                        }
                    }

                    // check for and prepare notification
                    $notify = $bot->checkNotify(true, true);
                    if ($notify === null) {
                        continue;
                    }

                    // send to scheduler
                    $data = [
                        'bot' => $bot,
                        'placeholders' => $placeholders,
                        'affectedUserIDs' => $affectedUserIDs,
                        'countToUserID' => $countToUserID,
                    ];

                    $job = new NotifyScheduleBackgroundJob($data);
                    BackgroundQueueHandler::getInstance()->performJob($job);
                }
            }

            // entry count
            // Read all active, valid activity bots, abort if none
            $bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'blog_entryCount']);
            if (\count($bots)) {
                // top blogger
                $top = new UzbotTop(1);
                $topUser = new User($top->blogEntry);

                // total entry count
                $sql = "SELECT COUNT(*) AS count
                        FROM    blog" . WCF_N . "_entry
                        WHERE    isDeleted = ? AND isPublished = ?";
                $statement = WCF::getDB()->prepareStatement($sql);
                $statement->execute([0, 1]);
                $countTotal = $statement->fetchColumn();

                $user = new User($entry->userID);

                foreach ($bots as $bot) {
                    // only users if not entryTop
                    if ($bot->blogEntryCountAction != 'entryTop' && !$user->userID) {
                        continue;
                    }

                    // user condition match relevant on entryX only
                    if ($bot->blogEntryCountAction != 'entryX') {
                        $conditions = $bot->getUserConditions();
                        if (\count($conditions)) {
                            $userList = new UserList();
                            $userList->getConditionBuilder()->add('user_table.userID = ?', [$user->userID]);
                            foreach ($conditions as $condition) {
                                $condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
                            }
                            $userList->readObjectIDs();
                            if (!\count($userList->getObjectIDs())) {
                                continue;
                            }
                        }
                    }

                    // only on count match or new top blogger
                    $counts = \explode(',', $bot->userCount);
                    $hit = false;

                    switch ($bot->blogEntryCountAction) {
                        case 'entryTotal':
                            if (\in_array($countTotal, $counts)) {
                                $hit = true;
                            }
                            break;

                        case 'entryX':
                            if (\in_array($user->blogEntries, $counts)) {
                                $hit = true;
                            }
                            break;

                        case 'entryTop':
                            if ($user->blogEntries > $topUser->blogEntries && $user->userID != $topUser->userID) {
                                $hit = true;
                                if (!$bot->testMode) {
                                    $editor = new UzbotTopEditor($top);
                                    $editor->update(['blogEntry' => $user->userID]);
                                }
                            }
                            break;
                    }

                    if ($hit) {
                        $affectedUserIDs = $countToUserID = $placeholders = [];

                        if ($user->userID) {
                            $affectedUserIDs[] = $user->userID;
                            $countToUserID[$user->userID] = $user->blogEntries;
                        }

                        // save blogID for entry in affected blog
                        $bot->blogID = $entry->blogID;

                        // get access level string
                        $access = 'wcf.user.access.';
                        if ($entry->accessLevel == Blog::ACCESS_EVERYONE) {
                            $access .= 'everyone';
                        } elseif ($entry->accessLevel == Blog::ACCESS_REGISTERED) {
                            $access .= 'registered';
                        } elseif ($entry->accessLevel == Blog::ACCESS_FOLLOWING) {
                            $access .= 'following';
                        } elseif ($entry->accessLevel == Blog::ACCESS_OWNER) {
                            $access .= 'nobody';
                        }

                        // set placeholder
                        $blog = $entry->getBlog();

                        // get authors
                        $authors = $blog->getAuthors();
                        $names = [];
                        if (\count($authors)) {
                            foreach ($authors as $author) {
                                $names[] = $author->username;
                            }
                        }

                        $placeholders['count'] = $countTotal;
                        $placeholders['count-user'] = $user->userID ? $user->blogEntries : 0;
                        $placeholders['blog-access'] = $access;
                        $placeholders['blog-authors'] = \count($names) ? \implode(', ', $names) : ' ';
                        $placeholders['blog-id'] = $blog->blogID;
                        $placeholders['blog-link'] = $blog->getLink();
                        $placeholders['blog-title'] = $blog->getTitle();
                        $placeholders['entry-id'] = $entry->entryID;
                        $placeholders['entry-link'] = $entry->getLink();
                        $placeholders['entry-teaser'] = $entry->getExcerpt();
                        $placeholders['entry-text'] = $entry->getMessage();
                        $placeholders['entry-title'] = $entry->getTitle();
                        $placeholders['translate'] = ['blog-access'];

                        // log action
                        if ($bot->enableLog) {
                            if (!$bot->testMode) {
                                UzbotLogEditor::create([
                                    'bot' => $bot,
                                    'count' => 1,
                                    'additionalData' => $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.user.affected', [
                                        'total' => 1,
                                        'userIDs' => \implode(', ', $affectedUserIDs),
                                    ]),
                                ]);
                            }
                        } else {
                            $result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.test', [
                                'objects' => 1,
                                'users' => \count($affectedUserIDs),
                                'userIDs' => \implode(', ', $affectedUserIDs),
                            ]);
                            if (\mb_strlen($result) > 64000) {
                                $result = \mb_substr($result, 0, 64000) . ' ...';
                            }
                            UzbotLogEditor::create([
                                'bot' => $bot,
                                'count' => 1,
                                'testMode' => 1,
                                'additionalData' => \serialize(['', '', $result]),
                            ]);
                        }

                        // check for and prepare notification
                        $notify = $bot->checkNotify(true, true);
                        if ($notify === null) {
                            continue;
                        }

                        // send to scheduler
                        $data = [
                            'bot' => $bot,
                            'placeholders' => $placeholders,
                            'affectedUserIDs' => $affectedUserIDs,
                            'countToUserID' => $countToUserID,
                        ];

                        $job = new NotifyScheduleBackgroundJob($data);
                        BackgroundQueueHandler::getInstance()->performJob($job);
                    }
                }
            }
        } else {
            $action = $eventObj->getActionName();
            $allowed = ['disable', 'enable', 'restore', 'trash', 'setAsFeatured', 'unsetAsFeatured', 'update'];
            if (!\in_array($action, $allowed)) {
                return;
            }

            if (empty($eventObj->getObjects())) {
                return;
            }

            // get entry and check for user
            $entry = $eventObj->getObjects()[0]->getDecoratedObject();
            if (!$entry->userID) {
                return;
            }
            if (WCF::getUser()->userID != $entry->userID) {
                return;
            }

            // check for bots
            $bots = UzbotValidBotCacheBuilder::getInstance()->getData(['typeDes' => 'blog_entryChange']);
            if (\count($bots)) {
                //preset data
                $user = WCF::getUser();
                $blog = $entry->getBlog();

                // get reason
                $params = $eventObj->getParameters();
                $reason = '';

                if (isset($params['editReason'])) {
                    $reason = $params['editReason'];
                } elseif (isset($params['data']['editReason'])) {
                    $reason = $params['data']['editReason'];
                }

                foreach ($bots as $bot) {
                    $affectedUserIDs = $countToUserID = $placeholders = [];
                    $count = 1;

                    $conditions = $bot->getUserConditions();
                    if (\count($conditions)) {
                        $userList = new UserList();
                        $userList->getConditionBuilder()->add('user_table.userID = ?', [$user->userID]);
                        foreach ($conditions as $condition) {
                            $condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
                        }
                        $userList->readObjectIDs();
                        if (!\count($userList->getObjectIDs())) {
                            continue;
                        }
                    }

                    // found one
                    $affectedUserIDs[] = $user->userID;
                    $countToUserID[$user->userID] = $user->blogEntries;

                    // get access level string
                    $access = 'wcf.uzbot.blog.access.';
                    $accessLevel = $entry->accessLevel;

                    if ($accessLevel == Blog::ACCESS_EVERYONE) {
                        $access .= 'everyone';
                    } elseif ($accessLevel == Blog::ACCESS_REGISTERED) {
                        $access .= 'registered';
                    } elseif ($accessLevel == Blog::ACCESS_FOLLOWING) {
                        $access .= 'following';
                    } elseif ($accessLevel == Blog::ACCESS_OWNER) {
                        $access .= 'nobody';
                    }

                    // get authors
                    $names = [];
                    $authors = $blog->getAuthors();
                    if (\count($authors)) {
                        foreach ($authors as $author) {
                            $names[] = $author->username;
                        }
                    }

                    // set placeholders
                    $placeholders['action'] = 'wcf.uzbot.blog.action.' . $action;
                    $placeholders['blog-access'] = $access;
                    $placeholders['blog-authors'] = \count($names) ? \implode(', ', $names) : ' ';
                    $placeholders['blog-id'] = $blog->blogID;
                    $placeholders['blog-link'] = $blog->getLink();
                    $placeholders['blog-title'] = $blog->getTitle();
                    $placeholders['count'] = 1;
                    $placeholders['count-user'] = $user->userID ? $user->blogEntries : 0;
                    $placeholders['entry-id'] = $entry->entryID;
                    $placeholders['entry-link'] = $entry->getLink();
                    $placeholders['entry-teaser'] = $entry->getExcerpt();
                    $placeholders['entry-text'] = $entry->getMessage();
                    $placeholders['entry-title'] = $entry->getTitle();
                    $placeholders['reason'] = $reason;
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
                                    'userIDs' => \implode(', ', $affectedUserIDs),
                                ]),
                            ]);
                        } else {
                            $result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.test', [
                                'objects' => 1,
                                'users' => \count($affectedUserIDs),
                                'userIDs' => \implode(', ', $affectedUserIDs),
                            ]);
                            if (\mb_strlen($result) > 64000) {
                                $result = \mb_substr($result, 0, 64000) . ' ...';
                            }
                            UzbotLogEditor::create([
                                'bot' => $bot,
                                'count' => 1,
                                'testMode' => 1,
                                'additionalData' => \serialize(['', '', $result]),
                            ]);
                        }
                    }

                    // check for and prepare notification
                    $notify = $bot->checkNotify(true, true);
                    if ($notify === null) {
                        continue;
                    }

                    // send to scheduler
                    $data = [
                        'bot' => $bot,
                        'placeholders' => $placeholders,
                        'affectedUserIDs' => $affectedUserIDs,
                        'countToUserID' => $countToUserID,
                    ];

                    $job = new NotifyScheduleBackgroundJob($data);
                    BackgroundQueueHandler::getInstance()->performJob($job);
                }
            }
        }
    }
}
