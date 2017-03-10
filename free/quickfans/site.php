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

class QuickFansModuleSite extends WeModuleSite {
  function __construct() {
	}

  public function doMobileCenter()
  {
    //手机端用户信息概览
  }

  public function doMobileCRMFrame()
  {
    global $_W, $_GPC;
    include $this->template('crmframe');
  }



  public function doMobileCRM()
  {
    global $_W, $_GPC;

    yload()->classs('quickcenter', 'fans');
    $_fans = new Fans();

    $from_user = $_GPC['from_user'];
    $fans = $_fans->get($_W['uniacid'], $from_user);
    $this->fansBeautify($item);
    // 获得上下级关系
    $leader = $_fans->getUplevelFans($_W['uniacid'], $from_user);


    // 获取积分历史
    yload()->classs('quickcenter', 'creditlog');
    $_creditlog = new CreditLog();
    $creditlog = $_creditlog->get($_W['uniacid'], $from_user);


    // 获取订单历史
    yload()->classs('quickshop', 'order');
    yload()->classs('quickshop', 'dispatch');
    $_order = new Order();
    $allgoods = $_order->getDetailedOrderByOpenId($_W['uniacid'], $from_user);
    $orders = $_order->batchGet($_W['uniacid'], array('from_user'=>$from_user), 'id', 1, 10000);


    if (!empty($allgoods)) {
      // 获取订单历史
      yload()->classs('quickshop', 'address');
      $_address = new Address();
      $address = $_address->getDefault($_W['uniacid'], $from_user);
    }


    // 模板
    include $this->template('crm');
  }


  public function doWebCenter()
  {
    global $_W, $_GPC;
    $this->doWebAuth();

    yload()->classs('quickcenter', 'fans');
    $_fans = new Fans();

    $op = empty($_GPC['op']) ? 'display' : ($_GPC['op']);
    if ($op == 'display') {
      $cond = array();
      if (isset($_GPC['searchtype'])) {
        switch($_GPC['searchtype']) {
        case 'nickname':
          $cond['nickname'] = $_GPC['search'];
          break;
        case 'from_user':
          $cond['from_user'] = $_GPC['search'];
          break;
        case 'mobile':
          $cond['mobile'] = $_GPC['search'];
          break;
        case 'vip':
          $cond['vip'] = $_GPC['search'];
          break;
        case 'credit1':
          $cond['credit1'] = $_GPC['search'];
          $cond['orderby'] = 'credit';
          break;
        case 'credit2':
          $cond['credit2'] = $_GPC['search'];
          break;
        case 'follow':
          $cond['follow'] = $_GPC['search'];
          break;
        default:
          $cond['nickname'] = $_GPC['search'];
          $cond['from_user'] = $_GPC['search'];
          $cond['mobile'] = $_GPC['search'];
          break;
        }
      } else {
        // 默认情况下，不显示已经取消关注的用户
        // 搜索情况下则显示已经取消关注的用户
        $cond['follow'] = 1;
      }
      $pindex = max(1, intval($_GPC['page']));
      $psize = 100;
      list($list, $total) = $_fans->batchGet($_W['uniacid'], $cond, null, $pindex, $psize);
      foreach($list as &$_item) {
        $this->fansBeautify($_item);
      }
      $pager = pagination($total, $pindex, $psize);
      yload()->classs('quickcenter', 'FormTpl');
      include $this->template('list');
      exit(0);
    } else if ($op == 'post') {
      $from_user = $_GPC['from_user'];
      $item = $_fans->get($_W['uniacid'], $from_user);
      $this->fansBeautify($item);
      if (!empty($_GPC['id'])) {
        yload()->routing('quickfans', 'UpdateFans');
        message('执行成功', referer(), 'success');
      }

      // 获取积分历史
      yload()->classs('quickcenter', 'creditlog');
      $_creditlog = new CreditLog();
      $creditlog = $_creditlog->get($_W['uniacid'], $from_user);


      // 获取订单历史
      yload()->classs('quickshop', 'order');
      $_order = new Order();
      $allgoods = $_order->batchGetOrderGoodsByOpenIds($_W['uniacid'], array($from_user));

      // 获得上下级关系
      yload()->classs('quickcenter', 'fans');
      $_fans = new Fans();
      $leader = $_fans->getUplevelFans($_W['uniacid'], $from_user);


      yload()->classs('quickcenter', 'custommsg');
      $_cust = new CustomMsg();
      $msgHistory = $_cust->getCustomMsg($_W['uniacid'], $from_user, 1000);
      //print_r($msgHistory);die(0);

      // 模板
      yload()->classs('quickcenter', 'FormTpl');
      include $this->template('edit');
      exit(0);
    }
    message('未知操作', '', 'error');
  }

  public function doWebAuth() {
    global $_W,$_GPC;
    yload()->classs('quickauth', 'auth');
    $_auth = new Auth();
    $op = trim($_GPC['op']);
    $modulename = MODULE_NAME;
    $version = '0.60';
    $_auth->checkXAuth($op, $modulename, $version);
  }

  private function fansBeautify(&$fans) {
    if (empty($fans['avatar'])) {
      $fans['avatar'] = RES_IMG . 'default_head.png';
    }
    return;
  }

  public function doWebRefresh() {
    global $_W, $_GPC;
    if (empty($_GPC['from_user'])) {
      message('非法OpenID', referer(), 'error');
    }
    yload()->classs('quickcenter', 'fans');
    $_fans = new Fans();
    $fans = $_fans->refresh($_W['uniacid'], $_GPC['from_user'], true);
    //message('头像刷新成功', referer(), 'success');
    message($fans, referer(), 'ajax');
  }

  public function doMobileRefresh() {
    global $_W, $_GPC;
    yload()->classs('quickcenter', 'fans');
    $_fans = new Fans();
    $fans = $_fans->refresh($_W['uniacid'], $_W['fans']['from_user'], true);
    message('头像刷新成功', referer(), 'success');
  }

  public function doWebDisappear() {
    global $_W, $_GPC;
    yload()->classs('quickcenter', 'fans');
    $_fans = new Fans();
    $_fans->disappear($_W['uniacid'], $_GPC['from_user']);

    // 上下级关系删除, 用户基本数据删除
    yload()->classs('quicklink', 'follow');
    $_follow = new Follow();
    $_follow->disappear($_W['uniacid'], $_GPC['from_user']);

    // 订单数据删除
    yload()->classs('quickshop', 'order');
    $_order = new Order();
    $_order->disappear($_W['uniacid'], $_GPC['from_user']);


    message($_GPC['from_user'] . '消失成功', referer(), 'success');
  }


}
