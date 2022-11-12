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
use blog\data\blog\BlogList;
use blog\data\category\BlogCategory;
use blog\data\category\BlogCategoryNodeTree;
use blog\data\entry\Entry;
use wcf\data\uzbot\notification\UzbotNotify;
use wcf\data\uzbot\type\UzbotType;
use wcf\system\category\CategoryHandler;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\exception\UserInputException;
use wcf\system\WCF;
use wcf\util\ArrayUtil;
use wcf\util\StringUtil;

/**
 * Listen to addForm events for Bot
 */

class UzbotAddFormBlogListener implements IParameterizedEventListener
{
    /**
     * instance of UzbotAddForm
     */
    protected $eventObj;

    /**
     * general data
     */
    protected $availableBlogs = [];

    protected $blogCategoryList;

    /**
     * entry data
     */
    protected $blogID = 0;

    protected $blogCategoryIDs = [];

    protected $blogEnableComments = 1;

    protected $blogIsDisabled = 0;

    protected $blogIsDraft = 0;

    protected $blogIsFeatured = 0;

    protected $blogEntryCountAction = 'entryTotal';

    /**
     * further data
     */
    protected $topBloggerCount = 1;

    protected $topBloggerInterval = 1;

    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        $this->eventObj = $eventObj;
        $this->{$eventName}();
    }

    /**
     * Handles the readData event. Only in UzbotEdit!
     */
    protected function readData()
    {
        if (empty($_POST)) {
            if (!empty($this->eventObj->uzbot->blogEntryData)) {
                $blogEntryData = \unserialize($this->eventObj->uzbot->blogEntryData);
                $this->blogID = $blogEntryData['blogID'];
                $this->blogCategoryIDs = \unserialize($blogEntryData['categoryIDs']);
                $this->blogEnableComments = $blogEntryData['enableComments'];
                $this->blogIsDisabled = $blogEntryData['isDisabled'];
                $this->blogIsDraft = $blogEntryData['isDraft'];
                $this->blogIsFeatured = $blogEntryData['isFeatured'];
            }
            $this->blogEntryCountAction = $this->eventObj->uzbot->blogEntryCountAction;

            $this->topBloggerCount = $this->eventObj->uzbot->topBloggerCount;
            $this->topBloggerInterval = $this->eventObj->uzbot->topBloggerInterval;
        }
    }

    /**
     * Handles the assignVariables event.
     */
    protected function assignVariables()
    {
        // get categories
        $excludedCategoryIDs = \array_diff(BlogCategory::getAccessibleCategoryIDs(), BlogCategory::getAccessibleCategoryIDs(['canUseCategory']));
        $categoryTree = new BlogCategoryNodeTree('com.woltlab.blog.category', 0, false, $excludedCategoryIDs);
        $this->blogCategoryList = $categoryTree->getIterator();
        $this->blogCategoryList->setMaxDepth(0);

        $blogList = new BlogList();
        $blogList->readObjects();
        $availableBlogs = $blogList->getObjects();

        WCF::getTPL()->assign([
            'availableBlogs' => $availableBlogs,
            'blogCategoryList' => $this->blogCategoryList,

            'blogID' => $this->blogID,
            'blogCategoryIDs' => $this->blogCategoryIDs,
            'blogEnableComments' => $this->blogEnableComments,
            'blogIsDisabled' => $this->blogIsDisabled,
            'blogIsDraft' => $this->blogIsDraft,
            'blogIsFeatured' => $this->blogIsFeatured,
            'blogEntryCountAction' => $this->blogEntryCountAction,
            'topBloggerCount' => $this->topBloggerCount,
            'topBloggerInterval' => $this->topBloggerInterval,
        ]);
    }

    /**
     * Handles the readFormParameters event.
     */
    protected function readFormParameters()
    {
        if (isset($_REQUEST['blogID'])) {
            $this->blogID = \intval($_REQUEST['blogID']);
        }
        if (isset($_REQUEST['blogCategoryIDs']) && \is_array($_REQUEST['blogCategoryIDs'])) {
            $this->blogCategoryIDs = ArrayUtil::toIntegerArray($_REQUEST['blogCategoryIDs']);
        }

        $this->blogEnableComments = $this->blogIsDisabled = $this->blogIsDraft = $this->blogIsFeatured = 0;
        if (isset($_POST['blogEnableComments'])) {
            $this->blogEnableComments = \intval($_POST['blogEnableComments']);
        }
        if (isset($_POST['blogIsDisabled'])) {
            $this->blogIsDisabled = \intval($_POST['blogIsDisabled']);
        }
        if (isset($_POST['blogIsDraft'])) {
            $this->blogIsDraft = \intval($_POST['blogIsDraft']);
        }
        if (isset($_POST['blogIsFeatured'])) {
            $this->blogIsFeatured = \intval($_POST['blogIsFeatured']);
        }
        if (isset($_POST['blogEntryCountAction'])) {
            $this->blogEntryCountAction = StringUtil::trim($_POST['blogEntryCountAction']);
        }
        if (isset($_POST['topBloggerCount'])) {
            $this->topBloggerCount = \intval($_POST['topBloggerCount']);
        }
        if (isset($_POST['topBloggerInterval'])) {
            $this->topBloggerInterval = \intval($_POST['topBloggerInterval']);
        }
    }

    /**
     * Handles the validate event.
     */
    protected function validate()
    {
        $blogList = new BlogList();
        $blogList->readObjects();
        $availableBlogs = $blogList->getObjects();

        // Get type / notify data
        $type = UzbotType::getTypeByID($this->eventObj->typeID);
        $notify = UzbotNotify::getNotifyByID($this->eventObj->notifyID);

        // blog notify
        if ($notify->notifyTitle == 'blog') {
            // blogID, allow 0 on certain bots
            if (!$this->blogID) {
                $allowed = ['blog_blogNew', 'blog_entryNew', 'blog_entryCount'];
                if (!\in_array($type->typeTitle, $allowed)) {
                    throw new UserInputException('blogID', 'invalid');
                }
            } else {
                if (!isset($availableBlogs[$this->blogID])) {
                    throw new UserInputException('blogID', 'invalid');
                }
            }

            // categoryIDs
            if (empty($this->blogCategoryIDs)) {
                throw new UserInputException('blogCategoryIDs', 'notConfigured');
            }
            $categories = [];
            foreach ($this->blogCategoryIDs as $categoryID) {
                $category = CategoryHandler::getInstance()->getCategory($categoryID);
                if ($category === null) {
                    throw new UserInputException('blogCategoryIDs', 'invalid');
                }
            }
            // count leaf categories
            $leafCategories = $categories;
            foreach ($categories as $category) {
                if ($category->parentCategoryID && isset($leafCategories[$category->parentCategoryID])) {
                    unset($leafCategories[$category->parentCategoryID]);
                }
            }
            if (\count($leafCategories) > BLOG_ENTRY_MAX_CATEGORIES) {
                throw new UserInputException('blogCategoryIDs', 'tooMany');
            }
        }
    }

    /**
     * Handles the save event.
     */
    protected function save()
    {
        $blogEntryData = [
            'blogID' => $this->blogID,
            'categoryIDs' => \serialize($this->blogCategoryIDs),
            'enableComments' => $this->blogEnableComments,
            'isDisabled' => $this->blogIsDisabled,
            'isDraft' => $this->blogIsDraft,
            'isFeatured' => $this->blogIsFeatured,
        ];

        $this->eventObj->additionalFields = \array_merge($this->eventObj->additionalFields, [
            'blogEntryData' => \serialize($blogEntryData),
            'blogEntryCountAction' => $this->blogEntryCountAction,
            'topBloggerCount' => $this->topBloggerCount,
            'topBloggerInterval' => $this->topBloggerInterval,
        ]);
    }

    /**
     * Handles the saved event.
     */
    protected function saved()
    {
        // not yet ...
    }
}
