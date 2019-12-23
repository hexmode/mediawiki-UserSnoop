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
}
