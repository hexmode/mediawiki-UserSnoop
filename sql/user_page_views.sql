CREATE TABLE /*_*/user_page_views (
  `user_id` int(5) unsigned NOT NULL,
  `page_id` int(8) unsigned NOT NULL,
  `hits` int(10) unsigned NOT NULL,
  `last` char(14) default NULL,
  PRIMARY KEY  (`user_id`,`page_id`)
) /*$wgDBTableOptions*/;


CREATE OR REPLACE VIEW /*_*/user_page_hits AS SELECT
	u.user_name AS user_name,
	u.user_real_name AS user_real_name,
	p.page_namespace AS page_namespace,
	p.page_title AS page_title,
	v.hits AS hits,
	v.last AS last
FROM (/*_*/user u JOIN /*_*/page p) JOIN /*_*/user_page_views v 
WHERE u.user_id = v.user_id AND p.page_id = v.page_id
ORDER BY u.user_id, v.hits DESC;
