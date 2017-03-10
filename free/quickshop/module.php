<?php

/**
 * 微商城模块定义
 *
 * @author WeEngine Team
 * @url
 */
defined('IN_IA') or exit('Access Denied');

include 'define.php';
require_once(IA_ROOT . '/addons/quickcenter/loader.php');
class QuickShopModule extends WeModule {

    public function settingsDisplay($settings) {
        global $_GPC, $_W;
        if (checksubmit()) {
          $cfg = array(
                'force_open_in_wechat' => intval($_GPC['force_open_in_wechat']),
                'vip_buy_guide_link'=> $_GPC['vip_buy_guide_link'],
                'sellername' => $_GPC['sellername'],
                'key' => $_GPC['key'],
                'secret' => $_GPC['secret'],
                'getnick' => $_GPC['getnick'],
                'enable_order_remark' => intval($_GPC['enable_order_remark']),
                'enable_top_user' => $_GPC['enable_top_user'],
                'followurl' => $_GPC['followurl'],
                'noticeemail' => $_GPC['noticeemail'],
                'shopname' => $_GPC['shopname'],
                'address' => $_GPC['address'],
                'phone' => $_GPC['phone'],
                'officialweb' => $_GPC['officialweb'],
                'enable_inshop_mode' => intval($_GPC['enable_inshop_mode']),
                'inshop_banner' => $_GPC['inshop_banner'],
                'inshop_banner_href' => $_GPC['inshop_banner_href'],
                'inshop_logo' => $_GPC['inshop_logo'],
                'inshop_logo_href' => $_GPC['inshop_logo_href'],
                'inshop_color' => $_GPC['inshop_color'],
                'inshop_share_text' => $_GPC['inshop_share_text'],
                'inshop_share_title'=>trim($_GPC['inshop_share_title']),
                'logo' => $_GPC['logo'],
                'description'=>  htmlspecialchars_decode($_GPC['description']),
                'enable_single_goods_id' => intval($_GPC['enable_single_goods_id']),
                'enable_user_remove_order' => intval($_GPC['enable_user_remove_order']),
                'require_follow_first' => intval($_GPC['require_follow_first']),
                'payed_template_id' => $_GPC['payed_template_id'],
                'default_province'=>empty($_GPC['default_province']) ? '北京市' : trim($_GPC['default_province']),
                'default_city'=>empty($_GPC['default_city']) ? '北京辖区' : trim($_GPC['default_city']),
                'default_area'=>empty($_GPC['default_area']) ? '' : trim($_GPC['default_area']),
                'enable_auto_close_window_after_pay'=>intval($_GPC['enable_auto_close_window_after_pay']),
                'enable_more_secondary' => intval($_GPC['enable_more_secondary']),
            );
            // 普通用户不可编辑模板
            if (isset($_GPC['template'])) {
                $cfg['template'] = trim($_GPC['template']);
            } else {
                $cfg['template'] = trim($settings['template']);
            }

            if ($this->saveSettings($cfg)) {
                message('保存成功', 'refresh');
            }
        }

        yload()->classs('quickcenter', 'FormTpl');

        // 扫码模板文件夹
        yload()->classs('quickcenter', 'dirscanner');
        $_scanner = new DirScanner();
        $dirs = $_scanner->scan('quicktemplate/quickshop');
        $template_items = array();
        foreach( $dirs as $dir ) {
          $template_items[$dir] = $dir; /* key=value */
        }

        yload()->classs('quickshop', 'goods');
        $_goods = new Goods();
        list($list, $total) = $_goods->batchGet($_W['uniacid']);
        $shop_items = array('0'=>'不启用');
        foreach( $list  as $item ) {
          $shop_items[$item['id']] = $item['title'];
        }

        $remove_order_option = array('0'=>'【不允许】用户删除未支付订单', '1'=>'【允许】用户删除未支付订单');
        $order_remark_option = array('0'=>'【不允许】用户下单时添加备注', '1'=>'【允许】用户下单时添加备注');
        $inshop_mode_option =  array('0'=>'【不开启】', '1'=>'【开启】');
        $force_open_in_wechat_option = array('0'=>'【不开启】模板可以被扒图(但打开速度最佳)', '1'=>'【开启】强制模板只能在微信中打开（但略影响打开速度）');
        $enable_auto_close_window_after_pay_option = array('0'=>'【不开启】订单支付成功后自动跳转到我的订单页面', '1'=>'【开启】订单支付成功后，自动关闭微信浏览器进入公众号聊天界面');
        $enable_more_secondary_option = array('1'=>'点击首页【更多】，打开二级分类导航', '0'=>'点击首页【更多】，打开分类下所有商品');


        if (empty($settings['sellername'])) {
          $settings['sellername'] = '东家';
        }
        include $this->template('setting');
    }

}
