<?php
/**
 * By 晓楚
 */
defined('IN_IA') or exit('Access Denied');

require IA_ROOT . '/addons/quicklink/define.php';
require_once IA_ROOT . '/addons/quickcenter/loader.php';

class QuickLinkModule extends WeModule {


	public function fieldsFormDisplay($rid = 0) {
		global $_W;
    yload()->classs('quicklink', 'channel');
    $_channel = new Channel();
    $allChannel = $_channel->batchGet($_W['uniacid']);
		if ($rid) {
      yload()->classs('quicklink', 'channelreply');
      $_channelreply = new ChannelReply();
      $reply = $_channelreply->get($rid);
			$channel = $_channel->get($_W['uniacid'], $reply['channel']);
		}
    include $this->template('form');
	}

	public function fieldsFormValidate($rid = 0) {
		global $_W, $_GPC;
		if (isset($_GPC['channel_id'])) {
			$channel_id = intval($_GPC['channel_id']);
			if (!empty($channel_id)) {
        yload()->classs('quicklink', 'channel');
        $_channel = new Channel();
        $channel = $_channel->get($_W['uniacid'], $reply['channel']);
        if (!empty($channel)) {
					return;
				}
			}
		}
		return '没有选择任何海报';
	}

	public function fieldsFormSubmit($rid) {
		global $_GPC;
		if (isset($_GPC['channel_id'])) {
      yload()->classs('quicklink', 'channel');
      yload()->classs('quicklink', 'channelreply');
      $_channel = new Channel();
      $_channelreply = new ChannelReply();
			$record = array('channel' => intval($_GPC['channel_id']), 'rid' => $rid);
      $reply = $_channelreply->get($rid);
			if ($reply) {
				$_channelreply->update($record, array('id' => $reply['id']));
			} else {
        $_channelreply->create($record);
			}
		}
	}

  public function ruleDeleted($rid) {
    //yload()->classs('quicklink', 'channelreply');
    //$_channelreply = new ChannelReply();
    //$_channelreply->remove(array('rid' => $rid));
	}



  public function settingsDisplay($settings) {
    global $_GPC, $_W;
    yload()->classs('quickcenter', 'FormTpl');

    if (checksubmit()) {
      if (intval($_GPC['antispam_enable']) == 1) {
        if (empty($_GPC['antispam_admin']) or empty($_GPC['antispam_passwd'])) {
          message('您选择了启用报警，必须填写接受报警的管理员的OpenID和移动端拉黑密码', referer(), 'error');
        }
      }

      $cfg = array(
        'notify_leader_follow_text' => $_GPC['notify_leader_follow_text'], // 关注后通知上线
        'notify_uplevel_follow_text' => $_GPC['notify_uplevel_follow_text'], // 关注后通知上上线
        'notify_leader_scan_text' => $_GPC['notify_leader_scan_text'], // 扫码通知
        'notify_not_leader_scan_text' => $_GPC['notify_not_leader_scan_text'], // 扫码通知, 扫的非上线二维码
        'antispam_enable' => intval($_GPC['antispam_enable']),
        'antispam_time_threshold' => $_GPC['antispam_time_threshold'],
        'antispam_user_threshold' => $_GPC['antispam_user_threshold'],
        'antispam_admin' => $_GPC['antispam_admin'],
        'antispam_passwd' => $_GPC['antispam_passwd'], // 在线拉黑需密码
        'antispam_autoblack' => intval($_GPC['antispam_autoblack']), // 自动拉黑 
        'antispam_nomore_alert' => intval($_GPC['antispam_nomore_alert']), // 停止报警
        'top_cnt'=> intval($_GPC['top_cnt']),
        'autoreply_rid'=>intval($_GPC['autoreply_rid']),
      );
      if ($this->saveSettings($cfg)) {
        message('保存成功', 'refresh');
      }
    }
    if (empty($settings['antispam_time_threshold'])) {
      $settings['antispam_time_threshold'] = 300;/* 5min */
    }
    if (empty($settings['antispam_user_threshold'])) {
      $settings['antispam_user_threshold'] = 20;/* 20 user */
    }
    if (empty($settings['top_cnt'])) {
      $settings['top_cnt'] = 20;/* 20 user */
    }

    yload()->classs('quicklink', 'channelreply');
    $_channelreply = new ChannelReply();
    $key_res  = $_channelreply->getAllKeyword($_W['uniacid']);
    $choose_keyword[0] = '首次购买后不自动推送二维码';
    foreach ($key_res as $data) {
      $choose_keyword[$data['rid']] = $data['content'];
    }

    $autoblack_options = array('0'=>'【手动拉黑模式】更安全, 不会误杀', '1'=>'【自动拉黑模式】更便捷，遇到大量刷分的时候更轻松');
    $alert_options = array('0'=>'【拉黑后还继续发送该用户刷分报警】更安心，真的拉黑啦！', '1'=>'【拉黑后不再发送该用户的报警】更省心，进入小黑屋的人就不要再烦我了');

    include $this->template('setting');
  }
}
