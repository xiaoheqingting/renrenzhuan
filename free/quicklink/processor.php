<?php
defined('IN_IA') or exit('Access Denied');


require IA_ROOT . '/addons/quicklink/define.php';
require MODULE_ROOT . '/quickcenter/loader.php';

class QuickLinkModuleProcessor extends WeModuleProcessor {
  private static $t_reply = 'quickspread_reply';

  public function respond() {
    global $_W;

    WeUtility::logging('respond1');
    $fans =  $this->refreshUserInfo($this->message['from']);
    $rule = $this->message['content'];
    $resp = null;
    WeUtility::logging("Processor:SUBSCRIBE", $this->message);
    if ($this->message['msgtype'] == 'text') {
      WeUtility::logging('respond2');
      // 通过输入关键字进入
      $text = $this->respondText($fans, $rule);
      $resp = $this->respText($text);
    } else if ($this->message['msgtype'] == 'event' and $this->message['event'] == 'CLICK') {
      // 通过点击菜单，模拟输入关键字进入
      $text = $this->respondText($fans, $rule);
      $resp = $this->respText($text);
    } else if ($this->message['msgtype'] == 'event') {
      // 其它事件：扫码、关注、取消关注
      if ($this->message['event'] == 'subscribe' && 0 === strpos($this->message['eventkey'], 'qrscene_')) {
        // 关注。这里需要区分两种情况：其它途径关注；扫专属二维码关注
        $resp = $this->respondSubscribe($fans, $rule);
      } elseif ($this->message['event'] == 'SCAN') {
        // 二维码扫码
        $resp = $this->respondScan($fans, $rule);
      } else if ($this->message['event'] == 'unsubscribe') {
        // 取消关注
        return null;
      } else if ($this->message['event'] == 'subscribe') {
        WeUtility::logging('subscribe quicklink');
      }
    }
    return $resp;
  }


  private function respondText($fans, $rule) {
    global $_W;

		$reply = pdo_fetch("SELECT * FROM " . tablename(self::$t_reply) . " WHERE rid = :rid LIMIT 1", array(':rid' => $this->rule));
    WeUtility::logging("Reply", json_encode($reply) . json_encode($this->rule));
    // start a reponser thread using curl
    if (!empty($reply)) {
      WeUtility::logging("Going Running task", $url . "==>" . json_encode($ret));
      yload()->classs('quickcenter', 'wechatutil');
      $url = WechatUtil::createMobileUrl('RunTask', $this->modulename, array('from_user'=>$this->message['from'], 'channel_id'=>$reply['channel'], 'rule'=>$rule));
      $ret = $this->http_request($url, 30);
      WeUtility::logging("Running task", $url . "==>" . json_encode($ret));
    }
    // responseImmMsg and exit
    return $this->responseImmMsg($_W['uniacid'], $fans, $reply['channel']);
  }

  private function http_request($url, $timeout = 30) {
    $parsed = parse_url($url);
    $host = $parsed['host'];
    $path = $parsed['path'] . '?' . $parsed['query'] ;
    $cookie = '';
    $fp = fsockopen($host, 80, $errno, $errstr, $timeout);
    WeUtility::logging('fsockopen', array($url, $errno, $errstr, $fp));
    if (!$fp) {
      return -1;
    }
    $out = "GET ".$path." HT"."TP/1.1\r\n";
    $out .= "Host: ".$host."\r\n";          //需要注意Host不能包括`http://`，仅可以使用`example.com`
    $out .= "Connection: Close\r\n";
    $out .= "Cookie: ".$cookie."\r\n\r\n";
    if (FALSE === fwrite($fp, $out)) {
      WeUtility::logging('write to socket failed', $fp);//将请求写入socket
    }  else {
      fgets($fp, 64);
    }

    /*
    //也可以选择获取server端的响应
    while (!feof($fp)) {
        echo fgets($fp, 128);
    }
    */
    //如果不等待server端响应直接关闭socket即可
    fclose($fp);
    WeUtility::logging('Msg loop thread start success', $fp);
  }

  private function responseImmMsg($weid, $fans, $channel_id) {
    yload()->classs('quicklink', 'channel');
    $_channel = new Channel();
    $ch = $_channel->get($weid, $channel_id);
    $msg = $ch['genqr_info1'];
    if (intval($ch['vip_limit']) > 0 and intval($fans['vip']) < intval($ch['vip_limit'])) {
      //如果VIP等级不够，则无法获取改二维码，提示用户.
      $msg = $ch['genqr_vip_limit_info'];
    }
    return $msg;
  }

  private function refreshUserInfo($from_user) {
    global $_W;
    yload()->classs('quickcenter', 'fans');
    $_fans = new Fans();
    $force = true;
    $userInfo = $_fans->refresh($_W['uniacid'], $from_user, $force);
    WeUtility::logging('refresh', $userInfo);
    return $userInfo;
  }

  private function respondScan($fans, $rule) {
    global $_W;
    yload()->classs('quicklink', 'scene');
    yload()->classs('quicklink', 'channel');
    $_scene = new Scene();
    $_channel = new Channel();
    $scene_id = $this->message['eventkey'];
    WeUtility::logging('respondScan', $scene_id);
    if (empty($scene_id)) {
      WeUtility::logging('subscribe', 'no scene id');
      return; // 交给其他模块处理 // $this->respText('欢迎关注微信号!');
    }
    // 2. 读取qr表，找到分享者uid，channel
    $qr = $_scene->getQRByScene($_W['uniacid'], $scene_id);
    if (empty($qr)) {
      WeUtility::logging('subscribe', 'no qr' . $scene_id);
      return; // 交给其他模块处理 // $this->respText('您好,已经关注');
    }
    WeUtility::logging('subscribe', $qr);
    $channel = $_channel->get($_W['uniacid'], $qr['channel']);
    if (empty($channel)) {
      WeUtility::logging('subscribe', 'no channel');
      return; // 交给其他模块处理 // $this->respText('欢迎回来，您已经关注');
    }
    if (empty($channel['title'])) {
      WeUtility::logging('subscribe', 'no channel title');
      return; // 交给其他模块处理
    }

    // 通知上线，当前用户通过二维码登陆
    yload()->classs('quickcenter', 'wechatapi');
    yload()->classs('quickcenter', 'fans');
    yload()->classs('quickcenter', 'textparser');
    yload()->classs('quicklink', 'follow');
    $_weapi = new WechatAPI();
    $_fans = new Fans();
    $_parser = new TextParser();
    $_follow = new Follow();

    $follower = $fans['from_user'];
    $leaderid = $qr['from_user'];
    $leader = $_fans->get($_W['uniacid'], $leaderid);
    if ($_follow->isNewUser($_W['uniacid'], $follower)) {
      WeUtility::logging('record followship', $qr);
      $_follow->processSubscribe($_W['uniacid'], $leaderid, $follower, $qr['channel']);
      /* 最后，给上线发一个通知 */
      $this->notifyUpLevelFollow($_weapi, $_follow, $_fans, $_W['uniacid'], $leaderid, $channel);
      $this->notifyLeaderFollow($_weapi, $_fans, $_W['uniacid'], $leaderid, $follower, $channel);
    }

    /* 最后，给上线发一个通知 */
    $realLeader = $_follow->getUpLevel($_W['uniacid'], $follower);
    if ($realLeader['leader'] == $leaderid) {
      $this->notifyLeaderScan($_weapi, $_fans, $_W['uniacid'], $leaderid, $fans['from_user'], $channel);
    } else {
      $this->notifyNotLeaderScan($_weapi, $_fans, $_W['uniacid'], $leaderid, $fans['from_user'], $channel);
    }


    $response = array();

    $channel['title'] = $_parser->parseScanQRResponse($fans, $leader, $channel['title']);
    $channel['desc'] = $_parser->parseScanQRResponse($fans, $leader, $channel['desc']);
    if (empty($channel['thumb'])) {
      return $this->respText($channel['desc']);
    }
    $response[] = array(
      'title' => $channel['title'],
      'description' => htmlspecialchars_decode($channel['desc']),
      'picurl' => $_W['attachurl'] . $channel['thumb'],
      'url' => $this->buildSiteUrl($channel['url'])
    );
    return $this->respNews($response);
  }

  private function respondSubscribe($fans, $rule) {
    global $_W;
    /* 有新用户通过二维码订阅本账号, 处理流程如下：
     * 1. 判断是否设置scene id，如果没有设置则直接回复默认消息，如果设置了scene id，则读取scene id
     * 2. 读取qr表，找到分享者uid，channel
     * 3. 将本次引流事件记录到follow表
     * 4. 推送channel指定消息给用户
     */
    $follower = $fans['from_user'];
    $scene_id = $this->message['scene'];
    if (empty($scene_id)) {
      return;
    }

    yload()->classs('quickcenter', 'wechatapi');
    yload()->classs('quicklink', 'scene');
    yload()->classs('quicklink', 'channel');
    yload()->classs('quicklink', 'follow');
    yload()->classs('quickcenter', 'fans');
    $_scene = new Scene();
    $_channel = new Channel();
    $_follow = new Follow();
    $_weapi = new WechatAPI();
    $_fans = new Fans();
    // 2. 读取qr表，找到分享者uid，channel
    $qr = $_scene->getQRByScene($_W['uniacid'], $scene_id);
    if (empty($qr)) {
      WeUtility::logging('subscribe', 'qr not found using scene ' . $scene_id);
      return; // $this->respText('欢迎关注微信号!');
    }

    // 3. 将本次引流事件记录到follow表
    $leaderid = $qr['from_user'];
    $leader = $_fans->get($_W['uniacid'], $leaderid);
    // 4. 推送channel指定消息给用户
    $channel = $_channel->get($_W['uniacid'], $qr['channel']);
    if (empty($channel)) {
      WeUtility::logging('subscribe', 'channel not found using channel ' . $qr['channel']);
      return; // $this->respText('欢迎关注微信号!');
    }
    if ($_follow->isNewUser($_W['uniacid'], $follower)) {
      WeUtility::logging('record followship', $qr);
      $_follow->processSubscribe($_W['uniacid'], $leaderid, $follower, $qr['channel']);
      $this->notifyUpLevelFollow($_weapi, $_follow, $_fans, $_W['uniacid'], $leaderid, $channel);
      $this->notifyLeaderFollow($_weapi, $_fans, $_W['uniacid'], $leaderid, $follower, $channel);
    }

    /* 最后，给上线发一个通知 */
    $realLeader = $_follow->getUpLevel($_W['uniacid'], $follower);
    if ($realLeader['leader'] == $leaderid) {
      $this->notifyLeaderScan($_weapi, $_fans, $_W['uniacid'], $leaderid, $fans['from_user'], $channel);
    } else {
      $this->notifyNotLeaderScan($_weapi, $_fans, $_W['uniacid'], $leaderid, $fans['from_user'], $channel);
    }

    yload()->classs('quickcenter', 'textparser');
    $_parser = new TextParser();
    $channel['title'] = $_parser->parseScanQRResponse($fans, $leader, $channel['title']);
    $channel['desc'] = $_parser->parseScanQRResponse($fans, $leader, $channel['desc']);
    if (empty($channel['thumb'])) {
      return $this->respText($channel['desc']);
    }
    $response = array();
    $response[] = array(
      'title' => $channel['title'],
      'description' => htmlspecialchars_decode($channel['desc']),
      'picurl' => $_W['attachurl'] . $channel['thumb'],
      'url' => $this->buildSiteUrl($channel['url'])
    );
    return $this->respNews($response);
  }

  private function notifyLeaderFollow($_weapi, $_fans, $weid, $leader, $follower, $channel) {
    $t = trim($channel['notify_leader_follow_text']);
    if (strpos($t, '*') === 0) { // 填写*则什么都不回复
      return;
    }
    if (!empty($leader)) {
      $follower_fans = $_fans->fans_search_by_openid($weid, $follower, array('nickname'));
      yload()->classs('quickcenter', 'textparser');
      $_parser = new TextParser();
      $text = $_parser->parse($follower_fans, $channel['notify_leader_follow_text']);
      $_weapi->sendText($leader, $text); // '通过您的努力，您的朋友' . $follower_fans['nickname'] . '成为了您忠诚的支持者，您也获得了相应的积分奖励，请注意查收！');
      WeUtility::logging('notifyLeaderFollow', array($leader, $text));
    }
  }

  private function notifyUpLevelFollow($_weapi, $_follow, $_fans, $weid, $this_level_openid, $channel) {
    global $_W;
    $t = trim($channel['notify_uplevel_follow_text']);
    if (strpos($t, '*') === 0) { // 填写*则什么都不回复
      return;
    }
    $uplevel = $_follow->getUpLevel($weid, $this_level_openid);
    if (!empty($uplevel)) {
      $fans = $_fans->fans_search_by_openid($weid, $this_level_openid, array('nickname'));
      yload()->classs('quickcenter', 'textparser');
      $_parser = new TextParser();
      $text = $_parser->parse($fans, $channel['notify_uplevel_follow_text']);
      $_weapi->sendText($uplevel['leader'], $text); // '您的朋友' . $fans['nickname'] . '又获得了一个新的支持者，您也得到了相应积分奖励，请注意查收!');
      WeUtility::logging('notifyLeaderFollow', array($uplevel['leader'], $text));
    }
  }

  private function notifyLeaderScan($_weapi, $_fans, $weid, $leader, $follower, $channel) {
    $t = trim($channel['notify_leader_scan_text']);
    if (strpos($t, '*') === 0) { // 填写*则什么都不回复
      return;
    }
    if (!empty($leader)) {
      $follower_fans = $_fans->fans_search_by_openid($weid, $follower, array('nickname'));
      yload()->classs('quickcenter', 'textparser');
      $_parser = new TextParser();
      $text = $_parser->parse($follower_fans, $channel['notify_leader_scan_text']);
      $_weapi->sendText($leader, $text); // '您的朋友' . $follower_fans['nickname'] . '通过您的二维码进入了系统');
      WeUtility::logging("notifyLeaderScan", array('leader'=>$leader, 'text'=>$text));
    }
  }


  private function notifyNotLeaderScan($_weapi, $_fans, $weid, $leader, $follower, $channel) {
    $t = trim($channel['notify_not_leader_scan_text']);
    if (strpos($t, '*') === 0) { // 填写*则什么都不回复
      return;
    }
    if (!empty($leader)) {
      $follower_fans = $_fans->fans_search_by_openid($weid, $follower, array('nickname'));
      yload()->classs('quickcenter', 'textparser');
      $_parser = new TextParser();
      $text = $_parser->parse($follower_fans, $channel['notify_not_leader_scan_text']);
      $_weapi->sendText($leader, $text);
      WeUtility::logging("notifyNotLeaderScan", array('leader'=>$leader, 'text'=>$text));
    }
  }

} /* end class */

