<?php
/**
 * QQ群：304081212
 * 作者：晓楚, 547753994
 *
 * 网站：www.xuehuar.com
 */

defined('IN_IA') or exit('Access Denied');

require_once(IA_ROOT . '/addons/quickfans/define.php');
require_once(IA_ROOT . '/addons/quickcenter/loader.php');

class QuickFansModule extends WeModule {
	public function settingsDisplay($settings) {
		global $_GPC, $_W;
		if(checksubmit()) {
			$cfg = array(
			);
			if($this->saveSettings($cfg)) {
				message('保存成功', 'refresh');
			}
		}
    yload()->classs('quickcenter', 'FormTpl');
		include $this->template('setting');
	}
}
