<?php
/* encapsulate Wechat APIs */

class WechatAPI
{
  public function getAccessToken() {
    global $_W;
    // 如何获取指定公众号的 access_token
    // 如果需要获得acid的token，则需要调用$accObj= WeixinAccount::create($acid);
    load()->classs('weixin.account');
    $accObj= new WeixinAccount();
    $access_token = $accObj->fetch_available_token();
    return $access_token;
  }

  public function getUserInfo($OPENID) {
    load()->func('communication');
    $ACCESS_TOKEN = $this->getAccessToken();
    $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$ACCESS_TOKEN}&openid={$OPENID}&lang=zh_CN";
    $json = ihttp_get($url);
    $userInfo = @json_decode($json['content'], true);
    // 讲wechat API得到的数据适配为fans表需要的格式
    $userInfo['from_user'] = $userInfo['openid'];
    $userInfo['nickname'] = $userInfo['nickname'];
    $userInfo['gender'] = $userInfo['sex'];
    $userInfo['nationality'] = $userInfo['country'];
    $userInfo['resideprovince'] = $userInfo['province'];
    $userInfo['residecity'] = $userInfo['city'];
    $userInfo['avatar'] = $userInfo['headimgurl'];
    return $userInfo;
  }

  public function uploadImage($img) {
    return $this->uploadRes($this->getAccessToken(), $img, 'image');
  }

  public function uploadVoice($voice) {
    return $this->uploadRes($this->getAccessToken(), $img, 'voice');
  }

  private function uploadRes($access_token, $img, $type) {
    $url = "http://file.api.weixin.qq.com/cgi-bin/media/upload?access_token={$access_token}&type={$type}";
    WeUtility::logging('uploadurl', $url);
    //$img = substr(trim($img), 1);
    $post = array(
      'media' => '@' . $img
    );
    WeUtility::logging('postparam', $post);
    yload()->classs('quickcenter', 'wechatutil');
    $ret = WechatUtil::http_request($url, $post);
    $content = @json_decode($ret['content'], true);
    WeUtility::logging('content', $content);
    return $content['media_id'];
  }

  public function sendText($openid, $text) {
    $data = array(
      "touser"=>$openid,
      "msgtype"=>"text",
      "text"=>array("content"=>htmlspecialchars_decode($text, ENT_QUOTES)));
    WeUtility::logging('begin send text','');
    yload()->classs('quickcenter', 'wechatutil');
    $json = WechatUtil::json_encode($data);
    WeUtility::logging('end send text','');
    $ret = $this->sendRes($this->getAccessToken(), $json);
    WeUtility::logging('end send res','');
    return $ret;
  }


  public function sendTextByParam($param) {
    return $this->sendText($param['from_user'], $param['msg']);
  }



  public function sendImage($openid, $media_id) {
    $data = array(
      "touser"=>$openid,
      "msgtype"=>"image",
      "image"=>array("media_id"=>$media_id));
    $ret = $this->sendRes($this->getAccessToken(), json_encode($data));

    $data['ret'] = $ret;
    WeUtility::logging('sendImage end', json_encode($data));
    return $ret;
  }


  /////////// 模板消息 ///////////
  public function sendTemplateMsg($data) {
    WeUtility::logging('begin send template msg', $data);
    yload()->classs('quickcenter', 'wechatutil');
    $json = WechatUtil::json_encode($data);
    WeUtility::logging('end send template msg', $data);
    $ret = $this->sendTpl($this->getAccessToken(), $json);
    return $ret;
  }


  // 
  // @return 0 ok, other code err
  //
  private function sendRes($access_token, $data) {
    $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$access_token}";
    yload()->classs('quickcenter', 'wechatutil');
    $ret = WechatUtil::http_request($url, $data);
    $content = @json_decode($ret['content'], true);
    WeUtility::logging('wechat server response', $content);
    return $content['errcode'];
  }

  private function sendTpl($access_token, $data) {
    $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$access_token}";
    yload()->classs('quickcenter', 'wechatutil');
    $ret = WechatUtil::http_request($url, $data);
    $content = @json_decode($ret['content'], true);
    WeUtility::logging('sendTpl return:', $content);
    return $content['errcode'];
  }

  public function getLimitQR($scene_id) {
    $qr_url = null;
    if ($scene_id < 1 or $scene_id > 100000) {
      WeUtility::logging('invalid scene id', $scene_id);
    } else {
      WeUtility::logging('begin get limit scene scene id', $scene_id);
      $data = array("action_name" => "QR_LIMIT_SCENE", "action_info" => array("scene" => array("scene_id" => $scene_id)));
      $content = $this->getQRTicket($this->getAccessToken(), $data);
      if ($content['errcode'] == 0) {
        WeUtility::logging('succ get limit scene scene id', $scene_id);
        $qr_url = $this->getQRImage($content['ticket']);
        WeUtility::logging('img get limit scene scene id', $scene_id);
      }
    }
    return $qr_url;
  }


  public function getLimitStrQR($scene_id) {
    $qr_url = null;
    if (empty($scene_id)) {
      WeUtility::logging('invalid scene id', $scene_id);
    } else {
      WeUtility::logging('begin get limit scene scene id', $scene_id);
      $data = array("action_name" => "QR_LIMIT_STR_SCENE", "action_info" => array("scene" => array("scene_str" => $scene_id)));
      $content = $this->getQRTicket($this->getAccessToken(), $data);
      if ($content['errcode'] == 0) {
        WeUtility::logging('succ get limit scene scene id', $scene_id);
        $qr_url = $this->getQRImage($content['ticket']);
        WeUtility::logging('img get limit scene scene id', array($scene_id, $qr_url));
      }
    }
    return $qr_url;
  }

  public function getQR($scene_id, $expire_seconds = 1800) {
    $qr_url = null;
    $data = array("expire_seconds"=>$expire_seconds, "action_name" => "QR_SCENE", "action_info" => array("scene" => array("scene_id" => $scene_id)));
    $content = $this->getQRTicket($this->getAccessToken(), $data);
    if ($content['errcode'] == 0) {
      $qr_url = $this->getQRImage($content['ticket']);
    }
    return $qr_url;
  }

  private function getQRTicket($token, $data) {
    $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token={$token}";
    WeUtility::logging('QR data', json_encode($data));
    yload()->classs('quickcenter', 'wechatutil');
    $ret = WechatUtil::http_request($url, json_encode($data));
    $content = @json_decode($ret['content'], true);
    WeUtility::logging('QR content', $content);
    return $content;
  }

  public function getQRImage($ticket) {
    $url = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket="
      . urlencode($ticket);
    return $url;
  }

  /// third party functions
}
