<?php

#our special page class
class SpecialUserSnoop extends SpecialPage
{
	#constructor
	function __construct__() {
		#add message to message cache
		global $wgMessageCache;
		self::loadAllMessages(); 
 
		#create new special page with the 'usersnoop' permission required
		SpecialPage::SpecialPage('UserSnoop', 'usersnoop');
	}
 
	#do your stuff
	function execute( $par = null ) {
		global $wgRequest, $wgOut, $wgUser;

		if( !$wgUser->isAllowed( 'usersnoop' ) ) {
			$wgOut->permissionRequired( 'usersnoop' );
			return;
		}

		$wgOut->setPageTitle('User snoop');
 
		list( $limit, $offset ) = wfCheckLimits();
 
		#initialize our victim user and get his/her id
		$this->targetUser = isset($par) ? $par : $wgRequest->getVal( 'username' );
		if($this->targetUser) {
			if($wgRequest->getVal('userid') && $wgRequest->getVal('useraction')) {
				$this->uid = $wgRequest->getVal('userid');
			} else {
				$dbr = wfGetDB(DB_SLAVE);
				$this->uid = $dbr->selectField('user','user_id',array('user_name'=>$this->targetUser),__METHOD__);
			}
 
			#and now create a user object for the victim
			$this->newUser = User::newFromId($this->uid);
			$this->newUser->load();
			$this->targetUser = $this->newUser->getName();
		}
 
		#we don't always want to display details, e.g. when we don't a have a user
		$ignore=false;
 
		#ok, do stuff for when we have a valid user
		if($this->uid >0 ) {
			$this->action = $wgRequest->getVal('action');
			$this->userAction = $wgRequest->getVal('useraction');
 
			#these are the main actions
			switch($this->action) {
			case 'pageviews':
				$up = new UserSnoopPagerPageviews($uid);
				break;
			case 'watchlist':
				$up = new UserSnoopPagerWatchlist($uid);
				break;
			case 'newpages':
				$up = new UserSnoopPagerNewpages($uid);
				break;
			default:
				$ignore = true;
				$up = new UserSnoopPager($uid);
				break;
			}
 
			#these are the 'bureaucrat' only actions
			#but first, let's make sure nobody's cheating
			global $wgUser;
			if(in_array('bureaucrat', $wgUser->getEffectiveGroups())) {
				switch($this->userAction) {
				case 'logout':
					$this->newUser->logout();
					break;
				case 'block':
					$this->blockUser($this->uid);
					break  ;
				case 'spreadblock':
					if($this->newUser->isBlocked()) {
						$this->blockUser($this->uid);
					}
					$this->newUser->spreadBlock();
					break;
				case 'unblock':
					$this->unblockUser($this->uid);
					break;
				default:
					break;
				}
			}
 
		} else {
			$ignore = true;
		}
 
 
		#get the master page header
		$s = $this->getPageHeader();
 
		#if we're ignoring the body or we have an invalid uid, don't try to generate anything
		if (!$ignore && ($this->uid > 0)) {
 
			$usersbody = $up->getBody();
 
			if( $usersbody) {
				$s .= $up->getPageHeader();
				$s .= $up->getNavigationBar();
				$s .= $up->getBodyHeader();
				$s .= $usersbody;
				$s .= $up->getBodyFooter();
				$s .= $up->getNavigationBar() ;
			}
		}
 
		#print what we've got!
		$wgOut->addHTML( $s );
	}
 
	#Creates the page header and if a user is selected already through the 'username' variable, process
	#
	#@return string       
	function getPageHeader() {
		global $wgServer, $wgScript, $wgContLang, $wgRequest, $wgUser;
 
		$out = '<form name="usersnoop" id="usersnoop" method="post">';
		$out .= wfMsg('usersnoopusername').': <input type="text" name="username" value="'.$this->targetUser.'">';
		$out .= '&nbsp;&nbsp;&nbsp;&nbsp;'.wfMsg('usersnoopaction').': <select name="action">';
		$out .= '<option>'.wfMsg('usersnoopnone').'</option>';
		$out .= '<option value="pageviews"'.($wgRequest->getVal('action')=='pageviews'?' selected':'').
			 '>'.wfMsg('usersnooppageviews').'</option>';
		$out .= '<option value="watchlist"'.($wgRequest->getVal('action')=='watchlist'?' selected':'').
			 '>'.wfMsg('usersnoopwatchlist').'</option>';
		$out .= '<option value="newpages"'.($wgRequest->getVal('action')=='newpages'?' selected':'').
			 '>'.wfMsg('usersnoopnewpages').'</option>';
		$out .= '</select>';
		$out .= '<input type="submit" value="'.wfMsg('usersnoopsubmit').'">';
		$out .= "</form>\n<hr>\n";
 
 
		if($this->targetUser != "") {
			$out .= '<table class="wikitable" width="100%" cellpadding="0" cellspacing="0">';
			$out .= '<tr><th>'.wfMsg('usersnoopid').'</th><th>'.wfMsg('usersnoopusername').
				 '</th><th>'.wfMsg('usersnooprealname').'</th><th>'.wfMsg('email').'</th>';
			$out .= '<th>'.wfMsg('usersnoopnewtalk').'</th><th>'.wfMsg('usersnoopregistered').
				 '</th><th>'.wfMsg('usersnoopedits').'</th></tr>';
 
			$dbr = wfGetDB(DB_SLAVE);
 
			#in 1.11 the User object has a getRegistration() method - need to upgrade but, this works too
			$reg = $dbr->selectField('user',
									 "concat(substr(user_registration, 1, 4),
                                        '-',substr(user_registration,5,2),
                                        '-',substr(user_registration,7,2),
                                        ' ',substr(user_registration,9,2),
                                        ':',substr(user_registration,11,2),
                                        ':',substr(user_registration,13,2))",
									 array('user_name' => $this->targetUser),__METHOD__);
 
			$out .= '<tr><td align="right">'.$this->uid.'</td>';
			$out .= '<td align="center">'.$this->sandboxParse('[[{{ns:user}}:'.$this->targetUser.'|'.$this->targetUser.']]').'</td>';
			$out .= '<td align="center">'.$this->newUser->getRealName().'</td>';
			$mt = $this->newUser->getEmail();
			$out .= '<td align="center">';
			$out .= "<a href=\"mailto:$mt\" class= \"external text\" title=\"mailto:$mt\" rel=\"nofollow\">";
			$out .= "$mt</a></td>";
			$out .= '<td align="center">';
			if($this->newUser->getNewTalk()) {
				$out .= '<a href="'.$wgScript.'?title='.$wgContLang->GetNsText(NS_USER_TALK);
				$out .= ':'.$this->targetUser.'&diff=cur">'.wfMsg('usernsoopyes').'</a>';
			} else {
				$out .= wfMsg('usersnoopno');
			}
			$out .= '</td><td align="center">'.$reg.'</td><td align="right">';
			$out .= $this->sandboxParse('[[{{ns:special}}:Contributions/'.$this->targetUser.'|'.
										$wgContLang->formatNum($this->newUser->getEditCount()).']]');
			$out .= "</td></tr>";
 
			$out .= '<tr><th>'.wfMsg('usersnoopblk').'</th><th colspan="2">'.wfMsg('usersnoopblockreason').
				 '</th><th colspan="2">'.wfMsg('usersnoopgroups').'</th>';
			$out .= '<th>'.wfMsg('usersnooplastupdated').'</th>';
			$out .= '<th>'.wfMsg('usersnoopsignature').'</th></tr>';
			$out .= '<tr><td>'.($this->newUser->isBlocked()==1?wfMsg('usersnoopyes'):wfMsg('usersnoopno')).'</td>';
			$out .= '<td colspan="2">'.$this->newUser->blockedFor().'</td>';
			$out .= '<td colspan="2">';
 
			$ok = false;
			$grps = $this->newUser->getEffectiveGroups();
			sort($grps);
			foreach($grps as $grp) {
				if($ok) $out .= ', ';
				$out .= $grp;
				$ok = true;
			}
			$out .= '</td>';
			$pc = $dbr->selectField('user',
									"concat(substr(user_touched, 1, 4),
                                        '-',substr(user_touched,5,2),
                                        '-',substr(user_touched,7,2),
                                        ' ',substr(user_touched,9,2),
                                        ':',substr(user_touched,11,2),
                                        ':',substr(user_touched,13,2))",
									array('user_name' => $this->targetUser),__METHOD__);
			$out .= '<td align="center">'.$pc.'</td>';
			$sig = $this->sandboxParse($this->newUser->getOption('nickname'));
			if(!$sig) {
				$sig = $this->sandboxParse('[[{{ns:user}}:'.$this->targetUser.'|'.$this->targetUser.']]');
			}
			$out .= '<td>'.$sig.'</td>';
			$out .= '</tr>';
 
			#these are the "special" areas, restricted to bureaucrats only
			if(in_array('bureaucrat', $wgUser->getEffectiveGroups())) {
				$out .= '<tr>';
				$out .= '<th colspan="2">'.wfMsg('usersnooplastlogin').'</th>';
 
				$out .= '<th rowspan="2" valign="center" align="center"><form name="userlogout" id="userlogout" method="post">';
				$out .= '<input type="hidden" name="useraction" value="logout">';
				$out .= '<input type="hidden" name="userid" value="'.$this->uid.'">';
				$out .= '<input type="hidden" name="username" value="'.$this->targetUser.'">';
				$out .= '<input type="hidden" name="action" value="'.$this->action.'">';
				$out .= '<input type="submit" value="'.wfMsg('usersnoopforcelogout').'"></form></th>';
 
				$out .= '<th rowspan="2" valign="center" align="center"><form name="userblock" id="userblock" method="post">';
				$out .= '<input type="hidden" name="useraction" value="block">';
				$out .= '<input type="hidden" name="userid" value="'.$this->uid.'">';
				$out .= '<input type="hidden" name="username" value="'.$this->targetUser.'">';
				$out .= '<input type="hidden" name="action" value="'.$this->action.'">';
				$out .= '<input type="submit" value="'.wfMsg('usersnoopblock').'"></form></th>';
 
				$out .= '<th rowspan="2" valign="center" align="center"><form name="userspreadblock" id="userspreadblock" method="post">';
				$out .= '<input type="hidden" name="useraction" value="spreadblock">';
				$out .= '<input type="hidden" name="userid" value="'.$this->uid.'">';
				$out .= '<input type="hidden" name="username" value="'.$this->targetUser.'">';
				$out .= '<input type="hidden" name="action" value="'.$this->action.'">';
				$out .= '<input type="submit" value="'.wfMsg('usersnoopspreadblock').'"></form></th>';
 
				$out .= '<th rowspan="2" valign="center" align="center"><form name="userunblock" id="userunblock" method="post">';
				$out .= '<input type="hidden" name="useraction" value="unblock">';
				$out .= '<input type="hidden" name="userid" value="'.$this->uid.'">';
				$out .= '<input type="hidden" name="username" value="'.$this->targetUser.'">';
				$out .= '<input type="hidden" name="action" value="'.$this->action.'">';
				$out .= '<input type="submit" value="'.wfMsg('usersnoopunblock').'"></form></th>';
 
				$out .= '<th rowspan="2">&nbsp;</th>';
 
				$out .= '</tr>';
 
				$pc = $dbr->selectField('user_page_views',
                                        "concat(substr(max(last), 1, 4),
                                                '-',substr(max(last),5,2),
                                                '-',substr(max(last),7,2),
                                                ' ',substr(max(last),9,2),
                                                ':',substr(max(last),11,2),
                                                ':',substr(max(last),13,2))",
                                        array('user_id' => $this->uid),__METHOD__);
				$out .= '<tr>';
 
				$out .= '<td align="center" colspan="2">'.$pc.'</td>';
				$out .= '</tr>';
			}
			$out .= "</table>\n<hr>\n";
		}
 
		return $out;  
	}
 
	function sandboxParse($wikiText) {
		global $wgTitle, $wgUser;
 
		$myParser = new Parser();
		$myParserOptions = new ParserOptions();
		$myParserOptions->initialiseFromUser($wgUser);
		$result = $myParser->parse($wikiText, $wgTitle, $myParserOptions);
 
		return $result->getText();
	}
 
	function blockUser($user_id = 0) {
		global $wgUser;
 
		if($user_id == 0) {
			$user_id = $this->uid;
		}
 
		$blk = new Block($this->targetUser, $user_id, $wgUser->getID(), wfMsg('usersnoopblockmessage'),
						 wfTimestamp(), 0, Block::infinity(), 0, 1, 0, 0, 1);
		if($blk->insert()) {
			$log = new LogPage('block');
			$log->addEntry('block', Title::makeTitle( NS_USER, $this->targetUser ),
						   'Blocked through Special:UserSnoop', array('infinite', 'nocreate'));
		}
	}
 
	function unblockUser($user_id = 0) {
		global $wgUser;
 
		if($user_id == 0) {
			$user_id = $this->uid;
		}
        $dbr = wfGetDB(DB_SLAVE);
		$ipb_id = $dbr->selectField('ipblocks', 'ipb_id', array('ipb_user'=>$user_id), __METHOD__);
		$blk = Block::newFromId($ipb_id);
 
		if($blk->delete()) {
			$log = new LogPage('block');
			$log->addEntry('unblock', Title::makeTitle(NS_USER, $this->targetUser), wfMsg('usersnoopunblockmessage'));
		} 
	}
 
	function loadAllMessages() {
		static $messagesLoaded = false;
		if(!$messagesLoaded) {
			global $wgMessageCache;
 
			#todo: add more languages
			$wgMessageCache->addMessages(
				array('usersnoop' => 'User snoop',
					  'usersnoopusername' => 'Username',
					  'usersnoopaction' => 'Action',
					  'usersnoopnone' => '(none)',
					  'usersnooppageviews' => 'Page views',
					  'usersnoopwatchlist' => 'Watchlist',
					  'usersnoopnewpages' => 'New pages',
					  'usersnoopsubmit' => 'Submit',
					  'usersnoopid' => 'ID',
					  'usersnooprealname' => 'Real name',
					  'usersnoopnewtalk' => 'New talk',
					  'usersnoopregistered' => 'Registered',
					  'usersnoopedits' => 'Edits',
					  'usersnoopyes' => 'Yes',
					  'usersnoopno' => 'No',
					  'usersnoopblk' => 'Blk',
					  'usersnoopblockreason' => 'Block reason',
					  'usersnoopgroups' => 'Groups',
					  'usersnooplastupdated' => 'Last updated',
					  'usersnoopsignature' => 'Signature',
					  'usersnooplastlogin' => 'Last login',
					  'usersnoopforcelogout' => 'Force logout',
					  'usersnoopblock' => 'Block',
					  'usersnoopspreadblock' => 'Spread block',
					  'usersnoopunblock' => 'Unblock',
					  'usersnoopblockmessage' => 'Blocked through Special:UserSnoop',
					  'usersnoopunblockmessage' => 'Unblocked through Special:UserSnoop',
					  'usersnooppage' => 'Page',
					  'usersnoophits' => 'Hits',
					  'usersnooplast' => 'Last',
					  'usersnooplastvisit' => 'Last visit',
					  'usersnooplasteditedbythisuser' => 'Last edited<br />by this user',
					  'usersnooplasteditedbyanyuser' => 'Last edited<br />by any user',
					  'usersnoopcreated' => 'Created')
				,'en');
 
			$messagesLoaded = true;
		}
		return true;
	}
 
	function loadLocalizedName(&$specialPageArray, $code) {
		self::loadAllMessages();
 
		$text = wfMsg('usersnoop');
		$title = Title::newFromText($text);
		$specialPageArray['UserSnoop'][] = $title->getDBKey();
 
		return true;
	}

    #function that updates the table containing the hits
    static public function UserPageViewTracker(&$parser, &$text) {
        global $wgDBprefix, $wgDBname, $wgUser, $wgOut;
 
		$title = $parser->getTitle();
		$artID = $title->getArticleID();
		$db = &wfGetDB(DB_SLAVE);
 
		#check to see if the user has visited this page before
		$query = "SELECT hits, last FROM ".$wgDBprefix."user_page_views WHERE user_id = ".$wgUser->getID();
		$query .= " AND page_id = $artID";
		if($result = $db->doQuery($query)) {
			$row = $db->fetchRow($result);
			$last = $row["last"];
 
			#due to multiple calls, don't double count if we've been here within the last 5 seconds
			if($last < (wfTimestampNow() - 5)) {
				$hits = $row["hits"];
				if($hits > 0) {
					$query = "UPDATE ".$wgDBprefix."user_page_views ";
					$query .= "SET hits = ".($hits + 1);
					$query .= ", last='".wfTimestampNow()."'";
					$query .= " WHERE user_id = ".$wgUser->getID();
					$query .= " AND page_id = ".$artID;
				}
				else
				{
					#looks like this is our first visit, create the record
					$query = "INSERT INTO ".$wgDBprefix."user_page_views(user_id, page_id, hits, last)";
					$query .= " VALUES(".$wgUser->getID().",".$artID.",1,'".wfTimestampNow()."')";
				}
				$db->doQuery($query);
			}
		}
		return true;
	}
}