<?php

#used for the watchlist data
class UserSnoopPagerWatchlist extends UserSnoopPager {
 
        function getIndexField() {
                return "rownum";
        }
 
        function getBodyHeader() {
                $s = '<table class="wikitable" width="100%" cellspacing="0" cellpadding="0">';
                $s .= '<tr><th>#</th><th>'.wfMsg('usersnooppage').'</th><th>'.wfMsg('usersnooplastvisit').
                      '</th><th>'.wfMsg('usersnooplasteditedbythisuser').'</th>';
                $s .= '<th>'.wfMsg('usersnooplasteditedbyanyuser').'</th></tr>';
                return $s;
        }
 
        function getBodyFooter() {
                $s = "</table>";
                return $s;
        }
 
        function getQueryInfo() {
		global $wgDBprefix;
                $conds = array();
 
                $table = "(select @rownum:=@rownum+1 as rownum, wl_user userid, wl_namespace namespace, wl_title title, `last` lastview, lastedit, lastuseredit";
                $table .= " from ".$wgDBprefix."page, ".$wgDBprefix."watchlist, ".$wgDBprefix."user_page_views,";
                $table .= " (select @rownum:=0) r,";
                $table .= " (select rev_page, max(rev_timestamp) lastedit from ".$wgDBprefix."revision group by rev_page) rev1,";
                $table .= " (select rev_user, rev_page, max(rev_timestamp) lastuseredit from ".$wgDBprefix."revision group by rev_user, rev_page) rev2";
                $table .= " where ".$wgDBprefix."page.page_namespace = wl_namespace and ".$wgDBprefix."page.page_title = wl_title";
                $table .= " and rev1.rev_page = ".$wgDBprefix."page.page_id";
                $table .= " and rev2.rev_user = wl_user";
                $table .= " and rev2.rev_page = ".$wgDBprefix."page.page_id";
                $table .= " and ".$wgDBprefix."user_page_views.user_id = wl_user";
                $table .= " and ".$wgDBprefix."user_page_views.page_id = ".$wgDBprefix."page.page_id";
                if($this->targetUser) {
                        $table .= " and wl_user = ".$this->uid;
                }
                $table .= ") result";
 
                return array(
                        'tables' => " $table ",
                        'fields' => array( 'rownum',
                                'namespace',
                                'title',
                                "concat(substr(lastview, 1, 4),'-',substr(lastview,5,2),'-',substr(lastview,7,2),' ',substr(lastview,9,2),':',substr(lastview,11,2),':',substr(lastview,13,2)) AS lastview",
                                "concat(substr(lastedit, 1, 4),'-',substr(lastedit,5,2),'-',substr(lastedit,7,2),' ',substr(lastedit,9,2),':',substr(lastedit,11,2),':',substr(lastedit,13,2)) AS lastedit",
                                "concat(substr(lastuseredit, 1, 4),'-',substr(lastuseredit,5,2),'-',substr(lastuseredit,7,2),' ',substr(lastuseredit,9,2),':',substr(lastuseredit,11,2),':',substr(lastuseredit,13,2)) AS lastuseredit"
                                ),
                        'conds' => $conds
                );
 
        }
 
        function formatRow( $row ) {
                $pageTitle = Title::makeTitle( $row->namespace, $row->title );
                if($row->namespace > 0) {
                        $pageFullName = $pageTitle->getNsText().":".htmlspecialchars($pageTitle->getText() );
                } else {
                        $pageFullName = htmlspecialchars( $pageTitle->getText());
                }
                $page = $this->getSkin()->makeLinkObj( $pageTitle, $pageFullName );
 
                $res = '<tr>';
                $res .= "<td style=\"text-align:center\">$row->rownum</td>";
                $res .= "<td>$page</td>";
                $res .= "<td style=\"text-align:center\">$row->lastview</td>";
                $res .= "<td style=\"text-align:center\">$row->lastuseredit</td>";
                $res .= "<td style=\"text-align:center\">$row->lastedit</td>";
                $res .= "</tr>\n";
                return $res;
        }
 
}
 
