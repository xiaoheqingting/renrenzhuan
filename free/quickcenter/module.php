<?php
/**
 * QQ群：304081212
 * 作者：晓楚, 547753994
 *
 * 网站：www.xuehuar.com
 */

defined('IN_IA') or exit('Access Denied');

include 'define.php';
require_once(IA_ROOT . '/addons/quickcenter/loader.php');

class QuickCenterModule extends WeModule {
	public function settingsDisplay($settings) {

		global $_GPC, $_W;
    yload()->classs('quickcenter', 'kvparser');

		if(checksubmit()) {
      $vip_arr = KVParser::decode($_GPC['vip']);
      if ($vip_arr === NULL) {
        message('VIP名称自定义格式错误，请参考输入框下方的例子');
      }
			$cfg = array(
        'template' => trim($_GPC['template']),
        'title' => trim($_GPC['title']),
        'uplevelname' => trim($_GPC['uplevelname']),
        'vip' => serialize($vip_arr),
			);
			if($this->saveSettings($cfg)) {
				message('保存成功', 'refresh');
			}
		}

    if (empty($settings['uplevelname'])) {
      $settings['uplevelname'] = '上线';
    }
    if (empty($settings['template'])) {
      $settings['template'] = 'pink';
    }
    if (empty($settings['title'])) {
      $settings['title'] = '记账本';
    }
    if (!empty($settings['vip'])) {
      $settings['vip'] = KVParser::encode(unserialize($settings['vip']));
    }

    yload()->classs('quickcenter', 'FormTpl');

    // 扫码模板文件夹
    yload()->classs('quickcenter', 'dirscanner');
    $_scanner = new DirScanner();
    $dirs = $_scanner->scan('quicktemplate/quickcenter');
    $template_items = array();
    foreach( $dirs as $dir ) {
      $template_items[$dir] = $dir; /* key=value */
    }

		include $this->template('setting');
	}
}
