<?php

#create a custom pager for our extension
#all other pagers will inherit this one
class UserSnoopPager extends AlphabeticPager 
{
        protected $targetUser = ""; //our victim
        protected $uid = 0;        //our victim's uid
 
        #constructor - also inilizes "stuff"
        function __construct($uid=0) {
                if($uid > 0) {
                        $this->uid = $uid;
                        $dbr = wfGetDB(DB_SLAVE);
                        $this->targetUser = $dbr->selectField('user','user_name',array('user_id'=>$this->uid),__METHOD__);
 
                } else {
                        global $wgRequest;
 
                        $this->targetUser = $wgRequest->getVal( 'username' );
                        if($this->targetUser != "") {
                                $dbr = wfGetDB(DB_SLAVE);
                                $this->uid = $dbr->selectField('user','user_id',array('user_name'=>$this->targetUser),__METHOD__);
                        }
                }
 
                parent::__construct();
        }
 
        function getBodyHeader() {
 
        }
 
        function getBodyFooter() {
 
        }
 
        function getBody() {
                if (!$this->mQueryDone) {
                        $this->doQuery();
                }
                $batch = new LinkBatch;
                $db = $this->mDb;
 
                $this->mResult->rewind();
 
                $batch->execute();
                $this->mResult->rewind();
                return parent::getBody();
        }
 
        function formatRow($row) {
 
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
                if($this->targetUser != '') {
                        $query['username'] = $this->targetUser;
                }
                $query['action'] = $wgRequest->getVal('action');            
                return $query;
        }
 
        function sandboxParse($wikiText) {
                global $wgTitle, $wgUser;
 
                $myParser = new Parser();
                $myParserOptions = new ParserOptions();
                $myParserOptions->initialiseFromUser($wgUser);
                $result = $myParser->parse($wikiText, $wgTitle, $myParserOptions);
 
                return $result->getText();
        }
 
 
}
 
