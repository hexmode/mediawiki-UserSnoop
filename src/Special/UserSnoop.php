<?php

/**
 * our special page class
 *
 * Copyright (C) 2017, 2019  Mark A. Hershberger
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

namespace UserSnoop\Special;

use LogPage;
use Parser;
use ParserOptions;
use PermissionsError;
use SpecialPage;
use Title;
use User;
use UserSnoop\Pager;
use UserSnoop\Pager\Pageviews;
use UserSnoop\Pager\Watchlist;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class UserSnoop extends SpecialPage {
	protected $target = null;

	function __construct() {
		# create new special page with the 'usersnoop' permission required
		parent::__construct( 'UserSnoop', 'usersnoop' );
	}

	# do your stuff
	function execute( $par = null ) {
		$out = $this->getOutput();
		$req = $this->getRequest();

		if ( !$this->getUser()->isAllowed( 'usersnoop' ) ) {
			throw new PermissionsError( 'usersnoop' );
		}

		$out->setPageTitle( 'User snoop' );

		# initialize our victim user and get his/her id
		$user = null;
		if ( isset( $par ) ) {
			$user = User::newFromName( $par );
		}

		if ( !$user && $req->getVal( 'username' ) ) {
			$user = User::newFromName( $req->getVal( 'username' ) );
		}

		$userID = null;
		if ( $user ) {
			$userID = $user->getID();
		}
		if ( $req->getVal( 'userid' ) && $req->getVal( 'useraction' ) ) {
			$userID = $req->getVal( 'userid' );
		}

		if ( $userID ) {
			# and now create a user object for the victim
			$this->target = User::newFromId( $userID );
		}

		# we don't always want to display details, e.g. when we don't a have a user
		$ignore = false;

		# ok, do stuff for when we have a valid user
		if ( $this->target ) {
			$this->action = $req->getVal( 'action' );
			$this->targetAction = $req->getVal( 'useraction' );
			$uid = $this->target->getID();

			# these are the main actions
			switch ( $this->action ) {
			case 'pageviews':
				$up = new Pageviews( $uid );
				break;
			case 'watchlist':
				$up = new Watchlist( $uid );
				break;
			case 'newpages':
				$up = new NewPages( $uid );
				break;
			default:
				$ignore = true;
				$up = new Pager( $uid );
				break;
			}

			# these are the 'bureaucrat' only actions
			# but first, let's make sure nobody's cheating
			if ( in_array( 'bureaucrat', $this->getUser()->getEffectiveGroups() ) ) {
				switch ( $this->targetAction ) {
				case 'logout':
					$this->target->logout();
					break;
				case 'block':
					$this->blockUser( $this->target->getID() );
					break;
				case 'spreadblock':
					if ( $this->target->isBlocked() ) {
						$this->blockUser( $this->target->getID() );
					}
					$this->target->spreadBlock();
					break;
				case 'unblock':
					$this->unblockUser( $this->target->getID() );
					break;
				default:
					break;
				}
			}

		} else {
			$ignore = true;
		}

		# get the master page header
		$s = $this->getPageHeader();

		# if we're ignoring the body or we have an invalid uid, don't
		# try to generate anything
		if ( !$ignore && ( $this->target->getID() > 0 ) ) {

			$usersbody = $up->getBody();

			if ( $usersbody ) {
				$s .= $up->getPageHeader();
				$s .= $up->getNavigationBar();
				$s .= $up->getBodyHeader();
				$s .= $usersbody;
				$s .= $up->getBodyFooter();
				$s .= $up->getNavigationBar();
			}
		}

		# print what we've got!
		$out->addHTML( $s );
	}

	# Creates the page header and if a user is selected already through
	# the 'username' variable, process
	#
	# @return string
	function getPageHeader() {
		global $wgServer, $wgScript, $wgContLang;

		$req = $this->getRequest();
		$out = '<form name="usersnoop" id="usersnoop" method="post">';
		$out .= wfMessage( 'usersnoopusername' )->text() . ': <input type="text" name="username" value="' . $this->target . '">';
		$out .= '&nbsp;&nbsp;&nbsp;&nbsp;' . wfMessage( 'usersnoopaction' )->text() . ': <select name="action">';
		$out .= '<option>' . wfMessage( 'usersnoopnone' )->text() . '</option>';
		$out .= '<option value="pageviews"' . ( $req->getVal( 'action' ) == 'pageviews' ? ' selected' : '' ) .
			 '>' . wfMessage( 'usersnooppageviews' )->text() . '</option>';
		$out .= '<option value="watchlist"' . ( $req->getVal( 'action' ) == 'watchlist' ? ' selected' : '' ) .
			 '>' . wfMessage( 'usersnoopwatchlist' )->text() . '</option>';
		$out .= '<option value="newpages"' . ( $req->getVal( 'action' ) == 'newpages' ? ' selected' : '' ) .
			 '>' . wfMessage( 'usersnoopnewpages' )->text() . '</option>';
		$out .= '</select>';
		$out .= '<input type="submit" value="' . wfMessage( 'usersnoopsubmit' )->text() . '">';
		$out .= "</form>\n<hr>\n";

		if ( $this->target != "" ) {
			$out .= '<table class="wikitable" width="100%" cellpadding="0" cellspacing="0">';
			$out .= '<tr><th>' . wfMessage( 'usersnoopid' )->text() . '</th><th>' . wfMessage( 'usersnoopusername' )->text() .
				 '</th><th>' . wfMessage( 'usersnooprealname' )->text() . '</th><th>' . wfMessage( 'email' )->text() . '</th>';
			$out .= '<th>' . wfMessage( 'usersnoop-confirmationpending')->text() . '</th>';
			$out .= '<th>' . wfMessage( 'usersnoopnewtalk' )->text() . '</th><th>' . wfMessage( 'usersnoopregistered' )->text() .
				 '</th><th>' . wfMessage( 'usersnoopedits' )->text() . '</th></tr>';

			$dbr = wfGetDB( DB_REPLICA );

			$reg = ConvertibleTimestamp::convert( TS_DB, $this->target->getRegistration() );
			$emailConfPending = $this->target->isEmailConfirmationPending() ? "Y" : "N";

			$out .= '<tr><td align="right">' . $this->target->getID() . '</td>';
			$out .= '<td align="center">' . $this->sandboxParse( '[[{{ns:user}}:' . $this->target . '|' . $this->target . ']]' ) . '</td>';
			$out .= '<td align="center">' . $this->target->getRealName() . '</td>';
			$mt = $this->target->getEmail();
			$out .= '<td align="center">';
			$changeLink = Title::newFromText(
							"ChangeUserEmail/" . $this->target, NS_SPECIAL
			)->getFullURL( [ "returnto" => "Special:UserSnoop/" . $this->target ] );
			$out .= "<a href=\"mailto:$mt\" class= \"external text\" title=\"mailto:$mt\" rel=\"nofollow\">";
			$out .= "$mt</a> (<a href=\"$changeLink\">change</a>)</td>";
			$out .= '<td align="center">' . $emailConfPending . "</td>";
			$out .= '<td align="center">';
			if ( $this->target->getNewTalk() ) {
				$out .= '<a href="' . $wgScript . '?title=' . $wgContLang->GetNsText( NS_USER_TALK );
				$out .= ':' . $this->target . '&diff=cur">' . wfMessage( 'usernsoopyes' )->text() . '</a>';
			} else {
				$out .= wfMessage( 'usersnoopno' )->text();
			}
			$out .= '</td><td align="center">' . $reg . '</td><td align="right">';
			$out .= $this->sandboxParse( '[[{{ns:special}}:Contributions/' . $this->target . '|' .
										$wgContLang->formatNum( $this->target->getEditCount() ) . ']]' );
			$out .= "</td></tr>";

			$out .= '<tr><th>' . wfMessage( 'usersnoopblk' )->text() . '</th><th colspan="2">' . wfMessage( 'usersnoopblockreason' )->text() .
				 '</th><th colspan="2">' . wfMessage( 'usersnoopgroups' )->text() . '</th>';
			$out .= '<th>' . wfMessage( 'usersnooplastupdated' )->text() . '</th>';
			$out .= '<th>' . wfMessage( 'usersnoopsignature' )->text() . '</th></tr>';
			$out .= '<tr><td>' . ( $this->target->isBlocked() == 1 ? wfMessage( 'usersnoopyes' )->text() : wfMessage( 'usersnoopno' )->text() ) . '</td>';
			$out .= '<td colspan="2">' . $this->target->blockedFor() . '</td>';
			$out .= '<td colspan="2">';

			$ok = false;
			$grps = $this->target->getEffectiveGroups();
			sort( $grps );
			foreach ( $grps as $grp ) {
				if ( $ok ) { $out .= ', ';
				}
				$out .= $grp;
				$ok = true;
			}
			$out .= '</td>';
			$pc = $dbr->selectField( 'user',
									"concat(substr(user_touched, 1, 4),
                                        '-',substr(user_touched,5,2),
                                        '-',substr(user_touched,7,2),
                                        ' ',substr(user_touched,9,2),
                                        ':',substr(user_touched,11,2),
                                        ':',substr(user_touched,13,2))",
									[ 'user_name' => $this->target ], __METHOD__ );
			$out .= '<td align="center">' . $pc . '</td>';
			$sig = $this->sandboxParse( $this->target->getOption( 'nickname' ) );
			if ( !$sig ) {
				$sig = $this->sandboxParse( '[[{{ns:user}}:' . $this->target . '|' . $this->target . ']]' );
			}
			$out .= '<td>' . $sig . '</td>';
			$out .= '</tr>';

			# these are the "special" areas, restricted to bureaucrats only
			if ( in_array( 'bureaucrat', $this->getUser()->getEffectiveGroups() ) ) {
				$out .= '<tr>';
				$out .= '<th colspan="2">' . wfMessage( 'usersnooplastlogin' )->text() . '</th>';

				$out .= '<th rowspan="2" valign="center" align="center"><form name="userlogout" id="userlogout" method="post">';
				$out .= '<input type="hidden" name="useraction" value="logout">';
				$out .= '<input type="hidden" name="userid" value="' . $this->target->getID() . '">';
				$out .= '<input type="hidden" name="username" value="' . $this->target . '">';
				$out .= '<input type="hidden" name="action" value="' . $this->action . '">';
				$out .= '<input type="submit" value="' . wfMessage( 'usersnoopforcelogout' )->text() . '"></form></th>';

				$out .= '<th rowspan="2" valign="center" align="center"><form name="userblock" id="userblock" method="post">';
				$out .= '<input type="hidden" name="useraction" value="block">';
				$out .= '<input type="hidden" name="userid" value="' . $this->target->getID() . '">';
				$out .= '<input type="hidden" name="username" value="' . $this->target . '">';
				$out .= '<input type="hidden" name="action" value="' . $this->action . '">';
				$out .= '<input type="submit" value="' . wfMessage( 'usersnoopblock' )->text() . '"></form></th>';

				$out .= '<th rowspan="2" valign="center" align="center"><form name="userspreadblock" id="userspreadblock" method="post">';
				$out .= '<input type="hidden" name="useraction" value="spreadblock">';
				$out .= '<input type="hidden" name="userid" value="' . $this->target->getID() . '">';
				$out .= '<input type="hidden" name="username" value="' . $this->target . '">';
				$out .= '<input type="hidden" name="action" value="' . $this->action . '">';
				$out .= '<input type="submit" value="' . wfMessage( 'usersnoopspreadblock' )->text() . '"></form></th>';

				$out .= '<th rowspan="2" valign="center" align="center"><form name="userunblock" id="userunblock" method="post">';
				$out .= '<input type="hidden" name="useraction" value="unblock">';
				$out .= '<input type="hidden" name="userid" value="' . $this->target->getID() . '">';
				$out .= '<input type="hidden" name="username" value="' . $this->target . '">';
				$out .= '<input type="hidden" name="action" value="' . $this->action . '">';
				$out .= '<input type="submit" value="' . wfMessage( 'usersnoopunblock' )->text() . '"></form></th>';

				$out .= '<th rowspan="2">&nbsp;</th>';

				$out .= '</tr>';

				$pc = $dbr->selectField( 'user_page_views',
										"concat(substr(max(last), 1, 4),
                                                '-',substr(max(last),5,2),
                                                '-',substr(max(last),7,2),
                                                ' ',substr(max(last),9,2),
                                                ':',substr(max(last),11,2),
                                                ':',substr(max(last),13,2))",
										[ 'user_id' => $this->target->getID() ], __METHOD__ );
				$out .= '<tr>';

				$out .= '<td align="center" colspan="2">' . $pc . '</td>';
				$out .= '</tr>';
			}
			$out .= "</table>\n<hr>\n";
		}

		return $out;
	}

	function sandboxParse( $wikiText ) {
		global $wgTitle;

		$myParser = new Parser();
		$myParserOptions = new ParserOptions( $this->getUser() );
		$result = $myParser->parse( $wikiText, $wgTitle, $myParserOptions );

		return $result->getText();
	}

	function blockUser( $user_id = 0 ) {
		if ( $user_id == 0 ) {
			$user_id = $this->target->getID();
		}

		$blk = new Block(
			$this->target, $user_id, $this->getUser()->getID(),
			wfMessage( 'usersnoopblockmessage' )->text(), wfTimestamp(), 0,
			Block::infinity(), 0, 1, 0, 0, 1
		);
		if ( $blk->insert() ) {
			$log = new LogPage( 'block' );
			$log->addEntry(
				'block', Title::makeTitle( NS_USER, $this->target ),
				'Blocked through Special:UserSnoop', [ 'infinite', 'nocreate' ]
			);
		}
	}

	function unblockUser( $user_id = 0 ) {
		if ( $user_id == 0 ) {
			$user_id = $this->target->getID();
		}
		$dbr = wfGetDB( DB_REPLICA );
		$ipb_id = $dbr->selectField( 'ipblocks', 'ipb_id', [ 'ipb_user' => $user_id ], __METHOD__ );
		$blk = Block::newFromId( $ipb_id );

		if ( $blk->delete() ) {
			$log = new LogPage( 'block' );
			$log->addEntry( 'unblock', Title::makeTitle( NS_USER, $this->target ), wfMessage( 'usersnoopunblockmessage' )->text() );
		}
	}
}
