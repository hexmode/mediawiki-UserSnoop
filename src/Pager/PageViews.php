<?php

/**
 * pager class used for user page views
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

namespace UserSnoop;

class PagerPageviews extends Pager {

        function __construct($uid=0) {
                parent::__construct($uid);
        }

        function getIndexField() {
                return "rownum";
        }

        function getBodyHeader() {
                $s = '<table class="wikitable" width="100%" cellspacing="0" cellpadding="0">';
                $s .= '<tr><th>#</th><th>'.wfMsg('usersnooppage').'</th><th>'.wfMsg('usersnoophits').'</th>';
                $s .= '<th>'.wfMsg('usersnooplast').'</th></tr>';
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


                $table = "(select @rownum:=@rownum+1 as rownum,";
                $table .= "user_name, user_real_name, page_namespace, page_title,hits, last ";
                $table .= "from (select @rownum:=0) r, ";
                $table .= "(select user_name, user_real_name, page_namespace, page_title,hits,";
                $table .= "last from ".$wgDBprefix."user_page_hits) p";
                if($this->targetUser) {
                        $table .= " where user_name = ";
                        $table .= wfGetDB(DB_SLAVE)->addQuotes($this->targetUser);
                }
                $table .= ") results";

                return array(
                        'tables' => " $table ",
                        'fields' => array( 'rownum',
                                'user_name',
                                'user_real_name',
                                'page_namespace',
                                'page_title',
                                'hits',
                                "concat(substr(last, 1, 4),'-',substr(last,5,2),'-',substr(last,7,2),' ',substr(last,9,2),':',substr(last,11,2),':',substr(last,13,2)) AS last"),
                        'conds' => $conds
                );

        }

        function formatRow( $row ) {
                $userPage = Title::makeTitle( NS_USER, $row->user_name );
                $name = $this->getSkin()->makeLinkObj( $userPage, htmlspecialchars( $userPage->getText() ) );
                $pageTitle = Title::makeTitle( $row->page_namespace, $row->page_title );
                if($row->page_namespace > 0) {
                        $pageFullName = $pageTitle->getNsText().":".htmlspecialchars($pageTitle->getText() );
                } else {
                        $pageFullName = htmlspecialchars( $pageTitle->getText());
                }
                $page = $this->getSkin()->makeLinkObj( $pageTitle, $pageFullName );

                $res = '<tr>';
                $res .= "<td style=\"text-align:center\">$row->rownum</td>";
                $res .= "<td>$page</td>";
                $res .= "<td style=\"text-align:right\">$row->hits</td>";
                $res .= "<td style=\"text-align:center\">$row->last</td>";
                $res .= "</tr>\n";
                return $res;
        }
}
