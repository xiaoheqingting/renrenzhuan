<?php

/* 负责处理关键词回复 */

class Responser {

  private static $WECHAT_MEDIA_EXPIRE_SEC = 300; // 100min //(3 * 24 * 60 * 60 - 1 * 60 * 60) seconds; 3 days

  function __construct() {
  }

  public function respondText($weid, $from_user, $channel_id, $rule) {
    /* 用户请求传单算法
     * 1. 获得用户uid
     * 2. 立即通知用户正在生成二维码
     * 3. 查询qr表，如果
     *   3.1 uid在qr表中不存在，则立即创建二维码，并插入qr表，然后返回信息
     *   3.2 uid在qr表中存在，则直接返回信息(第二期需要判断二维码有效时间，如果超过3天，则需要重新上传，更新media_id到qr表
     * 4. 将qr信息推送给用户
     * 5. 结束本次请求
     */
    WeUtility::logging('step1', '');
    yload()->classs('quickcenter', 'wechatapi');
    yload()->classs('quickcenter', 'fans');
    yload()->classs('quicklink', 'channel');
    yload()->classs('quicklink', 'scene');
    $weapi = new WechatAPI();
    $_channel = new Channel();
    $_scene = new Scene();
    $_fans = new Fans();
    //$content = $this->message['content'];
    // 1. 获取uid
    // $from_user = $this->message['from'];
    // 3. 查询qr表
    // $qr_mgr = new UserManager($from_user);
    /* 如果海报已经删除，则只记录上下级关系，不送积分 */
    $fans = $_fans->get($weid, $from_user);
    $ch = $_channel->get($weid, $channel_id);
    $qr = $_scene->getQR($weid, $from_user, $channel_id);

    // 没有缓存， 或者缓存过期
    // TODO: 根据二维码回收策略，定期回收不活跃二维码。二维码下面的ID分配给新主人。
    // 重新分配后，新用户通过老主人导入，送积分给新主人.
    if (intval($ch['vip_limit']) > 0 and intval($fans['vip']) < intval($ch['vip_limit'])) {
      //如果VIP等级不够，则无法获取改二维码，提示用户.
      //本功能仅仅针对点击菜单或关键词，不针对购物后自动推送二维码
      //合理
      //$ret = $weapi->sendText($from_user, $ch['genqr_vip_limit_info']);
      exit(0);
    } else if
      (
        empty($qr)
        or ($qr['createtime'] < $ch['createtime'])
        or ($qr['createtime'] + self::$WECHAT_MEDIA_EXPIRE_SEC  < time())
        // todo: 3天后又来生成，应该重用老scene_id，再生再传 */
      )
    {
      // 2. 立即通知用户
      if (!empty($ch['genqr_info1'])) {
        //$ret = $weapi->sendText($from_user, $ch['genqr_info1']);
      }
      // 3.1 uid在qr表中不存在，则立即创建二维码，并插入qr表，然后返回信息
      //$scene_id =  $_scene->getNextAvaliableSceneID($weid) . 'china';
      $scene_id =  $_scene->getNextAvaliableSceneID($weid);
      $media_id = $this->genImage($weid, $from_user, $weapi, $scene_id, $channel_id);
      if (!empty($media_id) and !empty($ch['genqr_info2'])) {
        //$ret = $weapi->sendText($from_user, $ch['genqr_info2']);
      }
      if (empty($media_id)) {
        $ret = $weapi->sendText($from_user, '生成二维码传单失败, 请联系我们解决. ScID:' . $scene_id);
      } else if (!empty($scene_id)) {
        WeUtility::logging('begin setQR', array($scene_id));
        // 老的QR不删除，因为二维码已经生成并且发布流传，删除后其他人关注后无法发放积分
        $_scene->newQR($weid, $from_user, $scene_id, '', $media_id, $channel_id, $rule);
        WeUtility::logging('end setQR', '');
      }
    } else {
      // 3.2 uid在qr表中存在，则直接返回信息
      $media_id = $qr['media_id'];
      if (!empty($media_id) and !empty($ch['genqr_info3'])) {
        // $ret = $weapi->sendText($from_user, $ch['genqr_info3']);
      }
    }
    // 4. 将qr信息推送给用户
    if (!empty($media_id)) {
      $ret = $weapi->sendImage($from_user, $media_id);
    } else {
      $ret = $weapi->sendText($from_user, "您的专属二维码已经生成过啦, 相信您已经保存起来了吧？你之前保存过的专属二维码依然有效，直接转发就可以啦。");
    }
    WeUtility::logging('step4', array($media_id, $ret));
    // 5. 结束本次请求
    exit(0);
  }

  private function genImage($weid , $from_user, $weapi, $scene_id, $channel) {
    global $_W;
    $rand_file = $from_user . rand() . '.jpg';
    $att_target_file = 'qr-image-' .$rand_file;
    $att_qr_cache_file = 'raw-qr-image-' .$rand_file;
    $att_head_cache_file = 'head-image-' . $rand_file;
    $target_file = ATTACH_DIR . $att_target_file;
    $target_file_url = $_W['attachurl'] . $att_target_file;
    $head_cache_file = ATTACH_DIR . $att_head_cache_file;
    $qr_cache_file = ATTACH_DIR . $att_qr_cache_file;
    $qr_file = $weapi->getLimitQR($scene_id);
    //$qr_file = $weapi->getLimitStrQR($scene_id);

    yload()->classs('quicklink', 'channel');
    $_channel = new Channel();
    $ch = $_channel->get($weid, $channel);

    $enableHead = $ch['avatarenable'];
    $enableName = $ch['nameenable'];
    if (empty($ch)) {
      $ret = $weapi->sendText($from_user, "您所请求的二维码已经失效, 请联系客服人员");
      exit(0);
    } else if (empty($ch['bgimages'])) {
      $bg_file  = MODULE_ROOT . 'quicklink/images/bg.jpg';
    }else if (is_array($ch['bgimages'])) {
      $cnt = count($ch['bgimages']);
      if ($cnt > 0) {
        srand(TIMESTAMP);
        $ridx = rand(0, $cnt - 1);
        $rand_bg = $ch['bgimages'][$ridx];
        $bg_file = $_W['attachurl'] . $rand_bg; //$ch['bg'];
      } else {
        $bg_file  = MODULE_ROOT . 'quicklink/images/bg.jpg';
      }
    } else {
      $bg_file  = MODULE_ROOT . 'quicklink/images/bg.jpg';
    }
    // 基础模式
    WeUtility::logging('step merge 1', '');

    //$url = WechatUtil::curl_file_get_contents($qr_file);
    //$fp = fopen($qr_cache_file, 'wb');
    //fwrite($fp, $url);
    //fclose($fp);
    $this->mergeImage($bg_file, $qr_file, $target_file, array('left'=>$ch['qrleft'], 'top'=>$ch['qrtop'], 'width'=>$ch['qrwidth'], 'height'=>$ch['qrheight'], 'quality'=>$ch['qrquality']));

    WeUtility::logging('step merge 1 done', '');
    // 扩展功能：昵称、图像
    if (1) {
      $fans = fans_search($from_user, array('nickname', 'avatar'));
      if (!empty($fans)) {
        // 昵称
        if ($enableName) {
          if (strlen($fans['nickname']) > 0) {
            WeUtility::logging('step wirte text 1', '');
            $this->writeText($target_file, $target_file, $fans['nickname'], array('size'=>$ch['namesize'], 'left'=>$ch['nameleft'], 'top'=>$ch['nametop'], 'color'=>$ch['namecolor']));
            WeUtility::logging('step wirte text 1 done', '');
          }
        }
        // 头像
        if ($enableHead) {
          if (strlen($fans['avatar']) > 10) {
            $head_file = $fans['avatar'];
            $head_file = preg_replace('/\/0$/i', '/96', $head_file);

            $url = WechatUtil::curl_file_get_contents($head_file);
            $fp = fopen($head_cache_file, 'wb');
            fwrite($fp, $url);
            fclose($fp);

            $this->mergeImage($target_file, $head_cache_file, $target_file,
              array('left'=>$ch['avatarleft'], 'top'=>$ch['avatartop'], 'width'=>$ch['avatarwidth'], 'height'=>$ch['avatarheight'], 'quality'=>100 /* fixed */));
            WeUtility::logging('IamInMergeFile', $target_file . $head_file);
          }
        }
      }
    }
    $media_id = $weapi->uploadImage($target_file);
    if (!empty($media_id)) {
      global $_W;
      $nowtime = time();
      //pdo_query("INSERT INTO " . tablename('core_attachment') . " (uniacid, uid, filename,attachment,type,createtime) VALUES "
      //  ."({$weid}, {$weid}, 'head_cache', '{$att_head_cache_file}', 1, {$nowtime}),"
      //  ."({$weid}, {$weid}, 'qr_cache', '{$att_qr_cache_file}', 1, {$nowtime}),"
      //  ."({$weid}, {$weid}, 'post_cache', '{$att_target_file}', 1, {$nowtime})");
    } else { // in case 45009, api freq out of limit ;
      $ret = $weapi->sendText($from_user, "哎哟，没有成功地把图片通过微信推送给你，不过没关系哦，点击这里:<a href='$target_file_url'>打开您的专属二维码</a>,保存到手机后转发.");
    }
    return $media_id;
  }

  private function imagecreate($bg) {
   $bgImg = @imagecreatefromjpeg($bg);
    if (FALSE == $bgImg) {
      $bgImg = @imagecreatefrompng($bg);
    }
    if (FALSE == $bgImg) {
      $bgImg = @imagecreatefromgif($bg);
    }
    return $bgImg;
  }


  private function mergeImage($bg, $qr, $out, $param) {
    extract($param);
    $bgImg = $this->imagecreate($bg);
    $qrImg = $this->imagecreate($qr);
    list($bgWidth, $bgHeight) = array(imagesx($bgImg), imagesy($bgImg));
    list($qrWidth, $qrHeight) = array(imagesx($qrImg), imagesy($qrImg));
    imagecopyresized($bgImg, $qrImg, $left, $top, 0, 0, $width, $height, $qrWidth, $qrHeight);
    ob_start();
    // output jpeg (or any other chosen) format & quality
    imagejpeg($bgImg, NULL, $quality);
    $contents = ob_get_contents();
    ob_end_clean();
    imagedestroy($bgImg);
    imagedestroy($qrImg);
    $fh = fopen($out, "w+" );
    fwrite( $fh, $contents );
    fclose( $fh );
  }

  private function writeText($bg, $out, $text, $param = array()) {
    list($bgWidth, $bgHeight) = getimagesize($bg);
    extract($param);
    $im = imagecreatefromjpeg($bg);
    $black = imagecolorallocate($im, 0, 0, 0);
    $font = APP_FONT . 'msyhbd.ttf';
    // $white = imagecolorallocate($im, 255, 255, 255);
    list($red, $green, $blue) = $this->hex2rgb($color);
    //$text = 'hello';
    $rgbcolor = imagecolorallocate($im, $red, $green, $blue);
    imagettftext($im, $size, 0, $left, $top+$size/2, $rgbcolor, $font, $text);
    ob_start();
    // output jpeg (or any other chosen) format & quality
    imagejpeg($im, NULL, 80);
    $contents = ob_get_contents();
    ob_end_clean();
    imagedestroy($im);
    $fh = fopen($out, "w+" );
    fwrite( $fh, $contents );
    fclose( $fh );
  }

  private function hex2rgb( $colour ) {
    if ( $colour[0] == '#' ) {
      $colour = substr( $colour, 1 );
    }
    if ( strlen( $colour ) == 6 ) {
      list( $r, $g, $b ) = array( $colour[0] . $colour[1], $colour[2] . $colour[3], $colour[4] . $colour[5] );
    } elseif ( strlen( $colour ) == 3 ) {
      list( $r, $g, $b ) = array( $colour[0] . $colour[0], $colour[1] . $colour[1], $colour[2] . $colour[2] );
    } else {
      list( $r, $g, $b ) = array('00', '00', '00');
    }
    $r = hexdec( $r );
    $g = hexdec( $g );
    $b = hexdec( $b );
    return array($r, $g, $b );
  }
}
