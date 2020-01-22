<?php

/**
 * create a custom pager for our extension
 * all other pagers will inherit this one
 *
 * Copyright (C) 2017  Mark A. Hershberger
 * Copyright (C) 2007  Kimon Andreou
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

use AlphabeticPager;
use User;
use LinkBatch;

class Pager extends AlphabeticPager {
	protected $targetUser = null;

	# constructor - also inilizes "stuff"
	function __construct( $uid = 0 ) {
		parent::__construct();
		if ( $uid > 0 ) {
			$this->uid = $uid;
			$this->targetUser = User::newFromID( $this->uid );
		} else {
			global $wgRequest;

			$user = $wgRequest->getVal( 'username' );
			if ( $user ) {
				$this->targetUser = User::newFromText( $user );
			}
		}
	}

	function getBodyHeader() {
	}

	function getBodyFooter() {
	}

	function getBody() {
		if ( !$this->mQueryDone ) {
			$this->doQuery();
		}
		$batch = new LinkBatch;
		$db = $this->mDb;

		$this->mResult->rewind();

		$batch->execute();
		$this->mResult->rewind();
		return parent::getBody();
	}

	function formatRow( $row ) {
	}

	function getQueryInfo() {
	}

	function getIndexField() {
	}

	function getPageHeader() {
	}

	function getDefaultQuery() {
		global $wgRequest;
		$query = parent::getDefaultQuery();
		if ( $this->targetUser != '' ) {
			$query['username'] = $this->targetUser;
		}
		$query['action'] = $wgRequest->getVal( 'action' );
		return $query;
	}

	function sandboxParse( $wikiText ) {
		global $wgTitle;

		$myParser = new Parser();
		$myParserOptions = new ParserOptions();
		$myParserOptions->initialiseFromUser( $this->getUser() );
		$result = $myParser->parse( $wikiText, $wgTitle, $myParserOptions );

		return $result->getText();
	}

}
