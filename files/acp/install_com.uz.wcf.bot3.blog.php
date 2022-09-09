<?php
use wcf\data\uzbot\top\UzbotTopAction;
use wcf\system\WCF;

/**
 * Initializes blog top data for Bot 
 * 
 * @author		2014-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.wcf.bot3.blog
 */

// posts
$topBloggerID = null;
$sql = "SELECT		userID, blogEntries
		FROM		wcf".WCF_N."_user
		ORDER BY 	blogEntries DESC";
$statement = WCF::getDB()->prepareStatement($sql, 1);
$statement->execute();
$row = $statement->fetchArray();
if (!empty($row)) $topBloggerID = $row['userID'];

$action = new UzbotTopAction([1], 'update', [
		'data' => [
				'blogEntry' => $topBloggerID
		]
]);
$action->executeAction();
