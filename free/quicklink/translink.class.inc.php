<?php

/*
 * 重点：什么时机做link()操作？
 * 可选1. 进入商城的时候
 * 可选2. 关注公众号的时候, receive函数
 */
class TransLink {
    private static $t_textlink = 'quickspread_textlink';
  /*
   * A转发文章，带上from_user
   * B是未注册用户，
   * if (B.cookie中没有from_user域) {
   *   B点击A的文章，将A.from_user写入B.cookie
   * } else {
   *  // ignore
   * }
   */
  public function preLink($weid, $shareby) {
    if (!empty($shareby)) {
      $key = 'shareby'.$weid;
      setcookie($key, $shareby, TIMESTAMP + 3600 * 24); // 点击文章后，一天内关注有效
    }
  }

 /*
  * B进入系统时，B如果是注册用户
  * if (Follow has no (A,B)) {
  *   Record A,B to Follow;
  *   送分给A
  * } else {
  *   // ignore
  * }
  * 清理cookie
  */
  public function link($weid, $fansInfo) {
    $key = 'shareby'.$weid;
    // if (isset($_COOKIE[$key]) && !empty($_COOKIE[$key]) && !empty($fansInfo) && !empty($fansInfo['follow'])) {
    if (isset($_COOKIE[$key]) && !empty($_COOKIE[$key]) && !empty($fansInfo)) {
      yload()->classs('quicklink', 'follow');
      $_follow = new Follow();
      // 是否已经是他人下线 / 是否已经是他人上线 (这里意味着：用户曾经已经加入了系统，否则不会成为别人上线)
      if ($_follow->isNewUser($weid, $fansInfo['from_user'])) {
        // setup link
        // 通过链接加入的，channel都是-1
        $ch = $this->getTextLinkChannel($weid);
        $ret = $_follow->recordFollow($weid, $_COOKIE[$key], $fansInfo['from_user'], -1, $ch['click_credit'], $ch['sub_click_credit'], $ch['newbie_credit']);
        $this->afterLink($weid, $ch, $fansInfo);
      }
      $this->cleanLink($weid);
    }
    return;
  }


  public function instantLink($weid, $fansInfo, $uplevel, $notify = false) {
    if (!empty($fansInfo)) {
      yload()->classs('quicklink', 'follow');
      $_follow = new Follow();
      // 是否已经是他人下线 / 是否已经是他人上线 (这里意味着：用户曾经已经加入了系统，否则不会成为别人上线)
      if ($_follow->isNewUser($weid, $fansInfo['from_user'])) {
        // setup link
        // 通过链接加入的，channel都是-1
        $ch = $this->getTextLinkChannel($weid);
        $ret = $_follow->recordFollow($weid, $uplevel, $fansInfo['from_user'], -1, $ch['click_credit'], $ch['sub_click_credit'], $ch['newbie_credit']);
        if ($notify) {
          $this->afterLink($weid, $ch, $fansInfo);
        }
      }
    }
    return;
  }

  private function afterLink($weid, $ch, $fans) {
      yload()->classs('quicklink', 'follownotify');
      $_notifier = new FollowNotify();
      $_notifier->notifyFollower($weid, $fans['from_user'], $fans['nickname']);
      $_notifier->notifyLeader($weid, $fans['from_user'], $fans['nickname']);
      $_notifier->notifyLeader2($weid, $fans['from_user'], $fans['nickname']);
      WeUtility::logging('All Notified', $weid);
  }

  public function cleanLink($weid) {
    setcookie("shareby".$weid, "system", time() - 3600); // set to a previouse time to clean
  }

  public function getTextLinkChannel($weid) {
    yload()->classs('quicklink', 'channel');
    $_channel = new Channel();
    $ret = $_channel->getActive($weid);
    return $ret;
  }

}

?>
