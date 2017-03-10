<?php

class Scene {

  private static $t_sys_qr = 'qrcode';
  private static $t_qr = 'quickspread_qr';
  private static $t_scene_id = 'quickspread_scene_id';

  // 微信服务器保留图片3天，保险起见减去1个小时的提前量
  private static $WECHAT_MEDIA_EXPIRE_SEC = 255600; //(3 * 24 * 60 * 60 - 1 * 60 * 60) seconds; 3 days

  /**
   * @brief 获取当前weid可用的下一个SceneID
   */
  public function getNextAvaliableSceneID($weid) {
    //
    //
    // TODO: 采用遍历算法，直接根据QR活跃度，从self::$t_qr表中选择空槽
    //
    //

    $scene_id = pdo_fetchcolumn('SELECT scene_id FROM ' . tablename(self::$t_scene_id) . ' WHERE weid=:weid',
      array(':weid'=>$weid));
    if (empty($scene_id)) {
      $scene_id = 200; // 200以前的预留给普通模块
      WeUtility::logging('sc emtpy', $scene_id);
      pdo_insert(self::$t_scene_id, array('weid'=>$weid, 'scene_id'=>$scene_id));
    } else {
      $scene_id++;
      pdo_update(self::$t_scene_id, array('scene_id'=>$scene_id), array('weid'=>$weid));
    } 
    return $scene_id;
  }

  /**
   * @brief 获取uid用户的当前推广QR
   */
  public function getQR($weid, $from_user, $channel) {
    $qr = pdo_fetch("SELECT * FROM " . tablename(self::$t_qr)
      . " WHERE from_user=:uid AND channel=:channel AND from_user=:from_user AND weid=:weid "
      . " ORDER BY createtime DESC LIMIT 1",
      array(
        ":uid"=>$from_user,
        ":channel"=>$channel,
        ":from_user"=>$from_user,
        ":weid"=>$weid));

    // 简单起见，当图片在微信服务器失效后（一般为3天），直接删除这一条规则, 由调用者负责具体后继处理方式
    if (!empty($qr) and $qr['createtime'] + self::$WECHAT_MEDIA_EXPIRE_SEC  < time()) {
      //pdo_delete(self::$t_qr, array("weid"=>$weid, "scene_id"=>$qr['scene_id']));
      //unset($qr);
      //$qr = null;
    }
    return $qr;
  }

  /**
   * @brief 根据scene_id获取二维码信息
   */
  public function getQRByScene($weid, $scene_id) {
    $qr = pdo_fetch("SELECT * FROM " . tablename(self::$t_qr) . " WHERE scene_id=:scene_id AND weid=:weid",
      array(":scene_id"=>$scene_id, ":weid"=>$weid));
    return $qr;
  }

  public function newQR($weid, $from_user, $scene_id, $qr_url, $media_id, $channel, $keyword) {
    $params = array(
      "weid"=>$weid,
      "from_user"=>$from_user,
      "scene_id"=>$scene_id,
      "qr_url"=>$qr_url,
      "media_id"=>$media_id,
      "channel"=>$channel,
      "createtime"=>time());
    $sys_params = array(
      "uniacid"=>$weid,
      "qrcid"=>$scene_id,
      "model"=>2,
      "name"=>$from_user,
      "keyword"=>$keyword,
      "expire"=>0,
      "createtime"=>time(),
      "status"=>1,
      "ticket"=>$media_id);
    $ret = pdo_insert(self::$t_qr, $params);
    $ret = pdo_insert(self::$t_sys_qr, $sys_params);
    if (!empty($ret)) {
      //pdo_insert(self::$t_sys_qr, array('weid'=>$weid, 'qrcid'=>$scene_id, 'name'=>'友乐聚', 'keyword'=>'qr'));
    }
    return $ret;
  }

  public function updateQR($weid, $from_user, $scene_id, $qr_url, $media_id, $channel) {
    $ret = pdo_update(self::$t_qr,
      array(
        "scene_id"=>$scene_id,
        "qr_url"=>$qr_url,
        "media_id"=>$media_id,
        "channel"=>$channel),
      array(
        "from_user"=>$from_user,
        "weid"=>$weid));
    return $ret;
  }

}
