CREATE TABLE /*_*/user_page_views (
  `user_id` int(5) unsigned NOT NULL,
  `page_id` int(8) unsigned NOT NULL,
  `hits` int(10) unsigned NOT NULL,
  `last` char(14) default NULL,
  PRIMARY KEY  (`user_id`,`page_id`)
) /*$wgDBTableOptions*/;
