<?php
defined('IN_IA') or exit('Access Denied');

if (!function_exists("__quickspread_batch_execute_sql")) {
  function __quickspread_batch_execute_sql($sqls) {
    global $_W;
    $sqls = preg_replace('/ims_/', $_W['config']['db']['tablepre'], $sqls);
    $sql_arr = explode(';', $sqls);
    foreach($sql_arr as $sql) {
      if (strlen($sql) > 10) {
        pdo_query($sql);
      }
    }
  }
}

$sql = "
CREATE TABLE IF NOT EXISTS `ims_quickspread_iptable` (
  `weid`  int(10) unsigned NOT NULL ,
  `ip` varchar(64)  NOT NULL,
  `credit`  int(10) unsigned NOT NULL ,
  `track_id` varchar(50) not null default '',
  `track_type` varchar(20)  NOT NULL default '',
  `from_user`  int(10) unsigned NOT NULL ,
  `spreadid`  int(10) unsigned NOT NULL ,
  `title` varchar(128)  NOT NULL,
  `access_time`  int(10) unsigned NOT NULL ,
  PRIMARY KEY(ip, weid, spreadid, access_time)
) ENGINE = MYISAM DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `ims_quickspread_blacklist` (
  `from_user` varchar(50) not null default '',
  `weid`  int(10) unsigned NOT NULL ,
  `access_time`  int(10) unsigned NOT NULL ,
  `hit` int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY(from_user, weid)
) ENGINE = MYISAM DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `ims_quickspread_fans` (
  `weid`  int(10) unsigned NOT NULL ,
  `from_user` varchar(100)  NOT NULL,
  `createtime` int(10)  NOT NULL,
  primary key(`weid`, `from_user`)
) ENGINE = MYISAM DEFAULT CHARSET = utf8;


CREATE TABLE IF NOT EXISTS `ims_quickspread_follow` (
  `weid`  int(10) unsigned NOT NULL ,
  `leader` varchar(100)  NOT NULL,
  `follower` varchar(100)  NOT NULL,
  `channel` int(10)  NOT NULL DEFAULT 0 COMMENT '渠道唯一标示符',
  `credit` int(10) NOT NULL DEFAULT 0,
  `createtime` int(11) NOT NULL DEFAULT 0,
  primary key(`weid`, `leader`, `follower`, `channel`),
  KEY idx_sum_follower (`weid`, `channel`),
  KEY idx_follow_speed_stat (`weid`, `leader`, `createtime`)
) ENGINE = MYISAM DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `ims_quickspread_credit` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `weid`  int(10) unsigned NOT NULL ,
  `from_user` varchar(100)  NOT NULL,
  `type` varchar(20) NOT NULL,
  `credit` int(10) NOT NULL,
  `createtime`  int(10) unsigned NOT NULL ,
  primary key(`id`)
) ENGINE = MYISAM DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `ims_quickspread_active_channel` (
  `weid`  int(10) unsigned NOT NULL,
  `from_user` varchar(100) NOT NULL,
  `channel` int(10)  NOT NULL,
  PRIMARY KEY(`weid`, `from_user`)
) ENGINE = MYISAM DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `ims_quickspread_scene_id` (
  `weid`  int(10) unsigned NOT NULL,
  `scene_id` int(10)  NOT NULL,
  PRIMARY KEY(`weid`)
) ENGINE = MYISAM DEFAULT CHARSET = utf8;



CREATE TABLE IF NOT EXISTS `ims_quickspread_channel` (
  `channel` int(10)  NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `active`  int(10) unsigned NOT NULL DEFAULT 0,
  `title` varchar(1024)  NOT NULL,
  `thumb` varchar(1024)  NOT NULL,
  `bg` varchar(1024)  NOT NULL,
  `desc` varchar(1024)  NOT NULL,
  `url` varchar(1024)  NOT NULL,
  `bgparam` varchar(10240)  NOT NULL,
  `click_credit` int(10)  NOT NULL COMMENT '未关注的用户关注,送分享者积分',
  `sub_click_credit` int(10)  NOT NULL COMMENT '未关注的用户关注,送上线积分',
  `newbie_credit` int(10)  NOT NULL COMMENT '通过本渠道关注微信号，送新用户大礼包积分',
  `weid`  int(10) unsigned NOT NULL,
  `deleted` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `createtime`  int(10) unsigned NOT NULL DEFAULT 0
) ENGINE = MYISAM DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `ims_quickspread_qr` (
  `weid`  int(10) unsigned NOT NULL,
  `scene_id` varchar(50) NOT NULL,
  `qr_url` varchar(1024)  NOT NULL,
  `media_id` varchar(1024)  NOT NULL,
  `createtime` int(11)  NOT NULL,
  `channel` int(10)  NOT NULL DEFAULT 0 COMMENT '渠道唯一标示符',
  `from_user` varchar(100) NOT NULL,
  PRIMARY KEY(`weid`, `scene_id`)
) ENGINE = MYISAM DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `ims_quickspread_reply` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rid` int(10) unsigned NOT NULL,
  `channel` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8   AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS  `ims_quickspread_top_cache` (
  `weid`  int(10) unsigned NOT NULL ,
  `createtime`  int(10) unsigned NOT NULL ,
  `cache` text NOT NULL DEFAULT '',
  PRIMARY KEY(weid)
) ENGINE = MYISAM DEFAULT CHARSET = utf8;

";

__quickspread_batch_execute_sql($sql);

if(pdo_fieldexists('mc_members', 'nickname')) {
  $sql = "ALTER TABLE  " . tablename('mc_members') . " CHANGE  `nickname`  `nickname` VARCHAR( 100 ) NOT NULL DEFAULT  '' COMMENT  '昵称'";
  pdo_query($sql);
}

