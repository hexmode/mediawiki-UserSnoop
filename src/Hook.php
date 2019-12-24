<?php

/**
 * Hooks for MediaWiki
 *
 * Copyright (C) 2017  Mark A. Hershberger
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace UserSnoop;

use Linker;
use DatabaseUpdater;
use RequestContext;

class Hook {
	/**
	 * Set up the db schema
	 */
	public static function onLoadExtensionSchemaUpdates(
		DatabaseUpdater $updater
	) {
		$updater->addExtensionTable( 'user_page_views', __DIR__ .
									 '/../sql/user_page_views.sql' );
		return true;
	}

	/**
	 * Add the private info link
	 */
	public static function onUserToolLinksEdit(
		int $userId, string $userText, array &$items
	) {
		$user = RequestContext::getMain()->getUser();
		if ( $user->isAllowed( "usersnoop" ) ) {
			array_unshift( $items, Linker::link(
				SpecialPage::getTitleFor( "UserSnoop", $userText ),
				wfMessage( "usersnooplabel" )->escaped()
			) );
		}
	}

	/**
	 * View tracker
	 */
	public static function UserPageViewTracker( &$parser, &$text ) {
		global $wgDBprefix, $wgDBname, $wgUser;

		$title = $parser->getTitle();
		$artID = $title->getArticleID();
		$db = &wfGetDB( DB_REPLICA );
		$userId = $wgUser->getID();

		# check to see if the user has visited this page before
		$query = "SELECT hits, last FROM " . $wgDBprefix . "user_page_views WHERE user_id = "
			   . $userId . " AND page_id = $artID";
		if ( $result = $db->doQuery( $query ) ) {
			$row = $db->fetchRow( $result );
			$last = $row["last"];

			# due to multiple calls, don't double count if we've been
			# here within the last 5 seconds
			if ( $last < ( wfTimestampNow() - 5 ) ) {
				$hits = $row["hits"];
				if ( $hits > 0 ) {
					$query = "UPDATE " . $wgDBprefix . "user_page_views ";
					$query .= "SET hits = " . ( $hits + 1 );
					$query .= ", last='" . wfTimestampNow() . "'";
					$query .= " WHERE user_id = " . $userId;
					$query .= " AND page_id = " . $artID;
				} else {
					# looks like this is our first visit, create the record
					$query = "INSERT INTO " . $wgDBprefix . "user_page_views"
						   . "(user_id, page_id, hits, last) "
						   . "VALUES(" . $userId . "," . $artID . ",1,'" . wfTimestampNow() . "')";
				}
				$db->doQuery( $query );
			}
		}
		return true;
	}

}
