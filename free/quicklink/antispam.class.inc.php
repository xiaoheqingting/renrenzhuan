<?php

class AntiSpam {
  private static $t_follow = 'quickspread_follow';
  private static $t_wechat = 'wechats_modules';
  private static $t_black = 'quickspread_blacklist';

  public function black($weid, $from_user) {
      if (!empty($from_user)) {
        $b = pdo_fetch("SELECT * FROM " . tablename(self::$t_black) . " WHERE from_user=:f AND weid=:w LIMIT 1", array(':f'=>$from_user, ':w'=>$weid));
        if (empty($b)) {
          pdo_insert(self::$t_black, array('from_user'=>$from_user, 'weid'=>$weid, 'access_time'=>time()));
        }
      }
      return;
  }

  /*
   * 返回true或者false
   * 如果返回0，则表示没有作弊嫌疑
   * 如果返回大于0，则有作弊嫌疑，返回值表示垃圾因子，越大越垃圾
   */
  public function filter($weid, $leader, $follower) {
    global $_W;
    /* 算法暂时比较简单: $time_threshold分钟内带来的粉丝数超过了$user_threshold人
      */
    yload()->classs('quickcenter', 'wechatsetting');
    $_settings = new WechatSetting();
    $setting = $_settings->get($weid, 'quicklink');

     if (empty($setting)) {
       $time_threshold = 120;
       $user_threshold = 10;
       $antispam_nomore_alert = 0;
       $antispam_autoblack = 0;
     } else {
       WeUtility::logging("setting2", $setting);
       $time_threshold = empty($setting['antispam_time_threshold']) ? 120 : $setting['antispam_time_threshold'];
       $user_threshold = empty($setting['antispam_user_threshold']) ? 10 : $setting['antispam_user_threshold'];
       $antispam_admin = $setting['antispam_admin'];
       $antispam_nomore_alert = intval($setting['antispam_nomore_alert']);
       $antispam_autoblack = intval($setting['antispam_autoblack']);
     }
     $since = TIMESTAMP - $time_threshold;
     $usercount = $user_threshold + 100;

     if ($setting['antispam_enable'] == 1) {

       $result = pdo_fetch("SELECT count(*) as count, SUM(credit) as credit FROM " . tablename(self::$t_follow)
         . " WHERE leader = :leader AND weid = :weid AND createtime > :since",
         array(':leader'=>$leader, ':weid'=>$weid, ':since'=>$since));

       $count = $result['count'];
       $credit = $result['credit'];
       if ($count < $user_threshold) {
         $count = 0;
       }

       // 报警
       if ($count > 0)  {
         yload()->classs('quickcenter', 'wechatapi');
         yload()->classs('quickcenter', 'fans');
         $_weapi = new WechatAPI();
         $_fans = new Fans();
         $fans = $_fans->get($weid, $leader);

         if (!empty($antispam_admin) and 0 == $antispam_nomore_alert) {
           $black_url = $_W['siteroot'] . murl('entry/module/blacklist', array('weid'=>$weid, 'm'=>'quicklink', 'from_user'=>$leader));
           $warning =  "[报警] 检测到刷分攻击, [{$fans['nickname']}]在{$time_threshold}秒内至少增加{$count}个下线，至少获得{$credit}分.<a href='{$black_url}'>立即移入黑名单</a> (OPENID:{$fans['from_user']})";
           $_weapi->sendText($antispam_admin, $warning);
           WeUtility::logging('WARNING:'. $antispam_admin, $warning);
         }

         if (1 == $antispam_autoblack) {
           $this->black($weid, $leader);
           if (!empty($antispam_admin)) {
             $warning = "[{$fans['nickname']}]由于刷分已经被拉黑。拉人积分增长将不会计入他的积分账户。";
             $_weapi->sendText($antispam_admin, $warning);
           }
         }
       }

     }

     return $count;
  }

}

?>
