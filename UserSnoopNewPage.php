#used for the newpage listing
class UserSnoopPagerNewPages extends UserSnoopPager {
 
        function getIndexField() {
                return "rownum";
        }
 
        function getBodyHeader() {
                $s = '<table class="wikitable" width="100%" cellspacing="0" cellpadding="0">';
                $s .= '<tr><th>#</th><th>'.wfMsg('usersnooppage').'</th><th>'.wfMsg('usersnoopcreated').
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
                list ($userpagehits) = wfGetDB(DB_SLAVE)->tableNamesN($wgDBprefix.'user_page_hits');
                $conds = array();
 
                $table = "(select @rownum:=@rownum+1 as rownum, ".$wgDBprefix."revision.rev_user user_id, namespace, title, created, lastuseredit, lastedit";
                $table .= " from ".$wgDBprefix."revision, (select @rownum:=0) r,";
                $table .= " (SELECT page_namespace namespace, page_title title, page_id pageid, min(rev_timestamp) created";
                $table .= " FROM ".$wgDBprefix."revision, ".$wgDBprefix."page";
                $table .= " where ".$wgDBprefix."page.page_id = ".$wgDBprefix."revision.rev_page";
                $table .= " group by namespace, title, pageid) revs,";
                $table .= " (select rev_page, max(rev_timestamp) lastedit from ".$wgDBprefix."revision group by rev_page) lastedit,";
                $table .= " (select rev_page, rev_user, max(rev_timestamp) lastuseredit from ".$wgDBprefix."revision group by rev_page,";
                $table .= " rev_user) lastuseredit";
                $table .= " where ".$wgDBprefix."revision.rev_page = revs.pageid";
                $table .= " and ".$wgDBprefix."revision.rev_timestamp = revs.created";
                $table .= " and lastedit.rev_page = revs.pageid";
                $table .= " and lastuseredit.rev_page = revs.pageid";
                $table .= " and lastuseredit.rev_user = ".$wgDBprefix."revision.rev_user";
                if($this->targetUser) {
                        $table .= " and ".$wgDBprefix."revision.rev_user = ".$this->uid;
                }
                $table .= ") res";
 
                return array(
                        'tables' => " $table ",
                        'fields' => array( 'rownum',
                                                                        'namespace',
                                                                        'title',
                                                                        "concat(substr(created, 1, 4),'-',substr(created,5,2),'-',substr(created,7,2),' ',substr(created,9,2),':',substr(created,11,2),':',substr(created,13,2)) AS created",
                                                                        "concat(substr(lastuseredit, 1, 4),'-',substr(lastuseredit,5,2),'-',substr(lastuseredit,7,2),' ',substr(lastuseredit,9,2),':',substr(lastuseredit,11,2),':',substr(lastuseredit,13,2)) AS lastuseredit",
                                                                        "concat(substr(lastedit, 1, 4),'-',substr(lastedit,5,2),'-',substr(lastedit,7,2),' ',substr(lastedit,9,2),':',substr(lastedit,11,2),':',substr(lastedit,13,2)) AS lastedit",
                                                                        ),
                        'conds' => $conds
                );
 
        }
 
        function formatRow( $row ) {
                $pageTitle = Title::makeTitle( $row->namespace, $row->title );
                if($row->namespace > 0) {
                        $pageFullName = $pageTitle->getNsText().":".htmlspecialchars($pageTiptle->getText() );
                } else {
                        $pageFullName = htmlspecialchars( $pageTitle->getText());
                }
                $page = $this->getSkin()->makeLinkObj( $pageTitle, $pageFullName );
 
                $res = '<tr>';
                $res .= "<td style=\"text-align:center\">$row->rownum</td>";
                $res .= "<td>$page</td>";
                $res .= "<td style=\"text-align:center\">$row->created</td>";
                $res .= "<td style=\"text-align:center\">$row->lastuseredit</td>";
                $res .= "<td style=\"text-align:center\">$row->lastedit</td>";
                $res .= "</tr>\n";
                return $res;
        }
 
}
