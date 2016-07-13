<?php
# The UserSnoop MediaWiki extension/special page
#
# This extension creates a new special page through which
# a user with the appropriate permissions ('usersnoop' and 'sysop')
# can view and manipulate users.
#
# The main section is display of user information:
#   - internal user id
#   - username
#   - real name
#   - email address
#   - whether the user has new talk or not
#   - date of registration
#   - number of edits
#   - whether the user is blocked or not
#   - block reason (if blocked)
#   - list of *effective* groups
#   - last update of user profile
#   - user's signature
#   
#   If the calling user is a member of the 'bureaucrat' group then they also can:
#   - user's last login
#   - force the user logout (boot user)
#   - block the user
#   - spread the block on the user to all of his/her ip addresses
#   - unblock the user
#
# The actions on information that can be called on the target user:
#   - page views
#     : page name
#     : number of visits
#     : last visit
#   - watchlist
#     : page name
#     : last visit
#     : last edit by user
#     : last edit by any user
#   - new pages
#     : page name
#     : create date
#     : last edit by user
#     : last edit by any user
 
 
# @addtogroup Extensions
# @author Kimon Andreou
# @copyright 2007 by Kimon Andreou
# @licence GNU General Public Licence 2.0 or later

$wgGroupPermissions['usersnoop'   ]['usersnoop'] = true;
$wgGroupPermissions['sysop'       ]['usersnoop'] = true;
$wgGroupPermissions['bureaucrat'  ]['usersnoop'] = true;


 #credit the extension
$wgExtensionCredits['parserhook'][] = array(
        'name'=>'UserSnoop',
        'url'=>'http://www.mediawiki.org/wiki/Extension:UserSnoop',
        'author'=>'Kimon Andreou',
        'description'=>'View all user information',
);
$wgExtensionCredits['specialpage'][] = array(
        'name' => 'User Snoop',
        'description' => 'View all user information',
        'url' => 'http://www.mediawiki.org/wiki/Extension:UserSnoop',
        'author' => 'Kimon Andreou'
);
#$wgMessagesDirs["UserSnoop"] = __DIR__ . "/i19n";
$wgExtensionMessagesFiles['UserSnoop'] = __DIR__ . "/UserSnoop.alias.php";

#$wgHooks['LanguageGetSpecialPageAliases'][] = 'UserSnoop::loadLocalizedName';
 
#register with a hook
#$wgHooks['ParserAfterTidy'][]  = 'SpecialUserSnoop::UserPageViewTracker';
 
#add our special page to the list
$wgSpecialPages['UserSnoop']                  = 'SpecialUserSnoop';
$wgAutoloadClasses['SpecialUserSnoop']        = __DIR__ . '/SpecialUserSnoop.php';
$wgAutoloadClasses['UserSnoopNewPages']       = __DIR__ . '/UserSnoopNewPage.php';
$wgAutoloadClasses['UserSnoopPager']          = __DIR__ . '/UserSnoopPager.php';
$wgAutoloadClasses['UserSnoopPagerPageviews'] = __DIR__ . '/UserSnoopPagerPageviews.php';
$wgAutoloadClasses['UserSnoopPagerWatchlist'] = __DIR__ . '/UserSnoopPagerWatchlist.php';