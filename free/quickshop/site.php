<?php

/**
 * 微商城模块微站定义
 *
 * @author WeEngine Team
 * @url
 */
defined('IN_IA') or exit('Access Denied');

session_start();
include 'define.php';
require_once(IA_ROOT . '/addons/quickcenter/loader.php');

class QuickShopModuleSite extends WeModuleSite {

  function __construct () {
    global $_W;
    //$_W['fans'] = pdo_fetch("select * from " . tablename('fans') . " Where from_user = 'oKknPt-ISf8ldd3OzigH2wrnhhM0'");
    //$_W['fans']['from_user'] = 'oKknPt-ISf8ldd3OzigH2wrnhhM0';
  }

  public function doWebAchive() {
    global $_W, $_GPC;

    $this->doWebAuth();

    /* 统计销售额 */
    yload()->classs('quickshop', 'order');
    $_order = new Order();
    $today_str = date('Y-m-d', TIMESTAMP) . ' 00:00:00';
    $today_ts = strtotime($today_str);
    $eclipse = TIMESTAMP - $today_ts;
    $new_status = array(Order::$ORDER_NEW);
    $deliever_status = array(Order::$ORDER_PAYED);
    $effective_status =  array(Order::$ORDER_PAYED, Order::$ORDER_DELIVERED, Order::$ORDER_RECEIVED, Order::$ORDER_CONFIRMED);

    $ToPay1 = $_order->getAchievementByTime($_W['uniacid'], $new_status,  $eclipse); // 今日待支付
    $ToDeliever1 = $_order->getAchievementByTime($_W['uniacid'], $deliever_status,  $eclipse); // 今日待支付
    $Pay1 = $_order->getAchievementByTime($_W['uniacid'], $effective_status,  $eclipse); // 今日销售额
    $Pay2 = $_order->getAchievementByTime($_W['uniacid'], $effective_status,  60*60*24*1 + $eclipse); // 今日销售额
    $Pay7 = $_order->getAchievementByTime($_W['uniacid'], $effective_status, 60*60*24*6 + $eclipse); // 7日销售额, 含今天
    $Pay30 = $_order->getAchievementByTime($_W['uniacid'], $effective_status, 60*60*24*29 + $eclipse); // 30日销售额，含今天

    /* 统计点击率 */
    yload()->classs('quickshop', 'shopstat');
    $_shopstat = new ShopStat();
    $viewcounts = $_shopstat->getAllGoodsViewCount($_W['uniacid']);

    /* 统计用户增长 */
    yload()->classs('quickcenter', 'fans');
    $_fans = new Fans();
    $UserDay1 = $_fans->getActiveUserByTime($_W['uniacid'], 1, $eclipse);
    $UserDay2 = $_fans->getActiveUserByTime($_W['uniacid'], 1, 60*60*24*1 + $eclipse);
    $UserDay3 = $_fans->getActiveUserByTime($_W['uniacid'], 1, 60*60*24*2 + $eclipse);
    $UserDay7 = $_fans->getActiveUserByTime($_W['uniacid'], 1, 60*60*24*6 + $eclipse);
    $UserDay30 = $_fans->getActiveUserByTime($_W['uniacid'], 1, 60*60*24*29 +$eclipse);

    $UserFallDay1 = $_fans->getActiveUserByTime($_W['uniacid'], 0, $eclipse);
    $UserFallDay2 = $_fans->getActiveUserByTime($_W['uniacid'], 0, 60*60*24*1 + $eclipse);
    $UserFallDay3 = $_fans->getActiveUserByTime($_W['uniacid'], 0, 60*60*24*2 + $eclipse);
    $UserFallDay7 = $_fans->getActiveUserByTime($_W['uniacid'], 0, 60*60*24*6 + $eclipse);
    $UserFallDay30 = $_fans->getActiveUserByTime($_W['uniacid'], 0, 60*60*24*29 +$eclipse);


    include $this->template('achievement');
  }

  /* done */
  public function doWebCategory() {
    global $_GPC, $_W;

    $this->doWebAuth();

    yload()->classs('quickshop', 'category');
    yload()->classs('quickcenter', 'FormTpl');
    $_category = new Category();

    $operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';
    if ($operation == 'display') {
      // 批量更新displayorder
      if (!empty($_GPC['displayorder'])) {
        foreach ($_GPC['displayorder'] as $id => $displayorder) {
          $_category->update($_W['uniacid'], $id, array('displayorder' => $displayorder));
        }
        message('分类排序更新成功！', $this->createWebUrl('category', array('op' => 'display')), 'success');
      }
      $children = array();
      $category = $_category->batchGet($_W['uniacid']);
      foreach ($category as $index => $row) {
        if (!empty($row['parentid'])) {
          $children[$row['parentid']][] = $row;
          unset($category[$index]);
        }
      }
      include $this->template('category');
    } elseif ($operation == 'post') {
      $parentid = intval($_GPC['parentid']);
      $id = intval($_GPC['id']);
      if (!empty($id)) {
        $category = $_category->get($id);
      } else {
        $category = array(
          'displayorder' => 0,
        );
      }
      if (!empty($parentid)) {
        $parent = $_category->get($parentid);
        if (empty($parent)) {
          message('抱歉，上级分类不存在或是已经被删除！', $this->createWebUrl('post'), 'error');
        }
      }
      if (checksubmit('submit')) {
        if (empty($_GPC['catename'])) {
          message('抱歉，请输入分类名称！');
        }
        $data = array(
          'weid' => $_W['uniacid'],
          'name' => $_GPC['catename'],
          'enabled' => intval($_GPC['enabled']),
          'displayorder' => intval($_GPC['displayorder']),
          'isrecommend' => intval($_GPC['isrecommend']),
          'description' => $_GPC['description'],
          'parentid' => intval($parentid),
          'thumb' => $_GPC['thumb'],
        );

        if (!empty($id)) {
          unset($data['parentid']);
          $_category->update($_W['uniacid'], $id, $data);
        } else {
          $id = $_category->create($data);
        }
        message('更新分类成功！', $this->createWebUrl('category', array('op' => 'display')), 'success');
      }
      include $this->template('category');
    } elseif ($operation == 'delete') {
      $id = intval($_GPC['id']);
      $category = $_category->get($id);
      if (empty($category)) {
        message('抱歉，分类不存在或是已经被删除！', $this->createWebUrl('category', array('op' => 'display')), 'error');
      }
      $_category->remove($_W['uniacid'], $id);
      message('分类删除成功！', $this->createWebUrl('category', array('op' => 'display')), 'success');
    }
  }

  /* done */
  public function doWebSetGoodsProperty() {
    global $_GPC, $_W;

    $this->doWebAuth();

    $id = intval($_GPC['id']);
    $type = $_GPC['type'];
    $data = intval($_GPC['data']);
    empty($data) ? ($data = 1) : $data = 0;
    if (!in_array($type, array('new', 'hot', 'recommend', 'discount'))) {
      die(json_encode(array("result" => 0)));
    }

    yload()->classs('quickshop', 'goods');
    $_goods = new Goods();
    $_goods->update($_W['uniacid'], $id, array("is" . $type => $data));

    die(json_encode(array("result" => 1, "data" => $data)));
  }

  /* done */
  public function doWebGoods() {
    global $_GPC, $_W;

    $this->doWebAuth();

    // for template render
    yload()->classs('quickcenter', 'FormTpl');
    load()->func('tpl');
    yload()->classs('quickshop', 'goods');
    yload()->classs('quickshop', 'category');
    yload()->classs('quickshop', 'dispatch');
    yload()->classs('quickcenter', 'wechatsetting');

    $_goods = new Goods();
    $_category = new Category();
    $_dispatch = new Dispatch();
    $_setting = new WechatSetting();
    $setting = $_setting->get($_W['uniacid'], 'quickcenter');
    $vip_kv = unserialize($setting['vip']);
    if (empty($vip_kv)) {
      $vip_kv = array(0,1,2,3,4,5,6,7,8,9);
    }

    $category = $_category->batchGet($_W['uniacid'], array(), 'id');

    $operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';

    if ($operation == 'swap_parent') {
      $id = intval($_GPC['id']);
      $item = $_goods->get($id);
      if (empty($item)) {
        message('抱歉，商品不存在或是已经删除！', '', 'error');
      }
      $parentid = $item['pgoodsid'];
      $_goods->swapParent($_W['uniacid'], $id, $parentid);

      // 跳转到新的主规格页面
      $gotoUrl = $this->createWebUrl('goods', array('op' => 'post', 'id' => $id)) . "#tab_spec";
      message("已经成功将{$id}号商品设置为主商品！", $gotoUrl, 'success');
    } else if ($operation == 'fork') {
      $id = intval($_GPC['id']);
      $cid = $_goods->fork($id);
      if (!empty($cid)) {
        message('添加子规格商品成功，前往编辑', $this->createWebUrl('goods', array('id'=>$cid, 'op'=>'post')), 'success');
      } else {
        message('添加子规格失败。无效的父类商品ID', referer(), 'error');
      }
    } else if ($operation == 'post') {
      $id = intval($_GPC['id']);
      if (!empty($id)) {
        $item = $_goods->get($id);
        if (empty($item)) {
          message('抱歉，商品不存在或是已经删除！', '', 'error');
        }
        $piclist = unserialize($item['thumb_url']);
      }
      if (empty($category)) {
        message('抱歉，请您先添加商品分类！', $this->createWebUrl('category', array('op' => 'post')), 'error');
      }
      $children = array();
      foreach ($category as $index => $row) {
        if (!empty($row['parentid'])) {
          $children[$row['parentid']][] = $row;
          unset($category[$index]);
        }
      }

      if (checksubmit('submit')) {
        if (empty($_GPC['goodsname'])) {
          message('请输入商品名称！');
        }
        if (empty($_GPC['category']['parentid'])) {
          message('请选择商品一级分类！');
        }
        if (empty($_GPC['totalcnf'])) {
          message('请选择减库存的方式。秒杀商品建议使用拍下减库存，一般商品建议使用付款减库存！');
        }
        if (empty($_GPC['marketprice'])) {
          message('商品价格不能为空');
        }
        if (floatval($_GPC['max_coupon_credit']) >= floatval($_GPC['marketprice'])) {
          message("积分抵扣额{$_GPC['max_coupon_credit']}不得高于商品售价{$_GPC['marketprice']}！");
        }

        if (empty($_GPC['use_abs_commission'])) {
          if (floatval($_GPC['rate1']) > 1 or floatval($_GPC['rate2']) > 1 or floatval($_GPC['rate3']) > 1) {
            message('您使用了相对返利，返利比例设置得太大! 大于1的时候会亏钱啦! 如果您一意孤行，可以考虑设置绝对返利，随您设置任意值。', '', 'error');
          }
        }

        if (!empty($_GPC['pgoodsid'])) {
          if ($_GPC['pgoodsid'] == $_GPC['id']) {
            message('自己不能是自己的主规格商品', '', 'error');
          }
          // 检查这个上级是否是顶级主规格商品
          // (1) 如果上级本身就有上线，必然不是
          $g = $_goods->get($_GPC['pgoodsid']);
          if (!empty($g['pgoodsid'])) {
            message($g['title'] . '是子规格商品，不能作为' . $_GPC['title'] . '的主规格商品', '', 'error');
          }
          unset($g);
          // (2) 如果上级本身就有下线，必然不是
          $g = $_goods->batchGetSubSpec($_W['uniacid'], $_GPC['id']);
          if (!empty($g)) {
            message($_GPC['title'] . '是主规格商品, 不能变更为子规格商品', '', 'error');
          }
        }

        yload()->classs('quickshop', 'shoppermission');
        if ($_W['isfounder']
          or (empty($id))
          or (ShopPermission::hasGoodsEditPermission($_GPC['permusers']))) {
            if (empty($id) and empty($_GPC['permusers'])) { $permusers = $_W['username']; } else { $permusers = $_GPC['permusers'];}
        } else {
            message('没有权限，联系管理员为您增加编辑权限', '', 'error');
        }

        $data = array(
          'weid' => intval($_W['uniacid']),
          'displayorder' => intval($_GPC['displayorder']),
          'title' => $_GPC['goodsname'],
          'pcate' => intval($_GPC['category']['parentid']),
          'ccate' => intval($_GPC['category']['childid']),
          'support_delivery' => intval($_GPC['support_delivery']),
          'goodstype' => intval($_GPC['goodstype']),
          'sendtype' => intval($_GPC['sendtype']),
          'credittype' => intval($_GPC['credittype']),
          'isrecommend' => intval($_GPC['isrecommend']),
          'ishot' => intval($_GPC['ishot']),
          'isnew' => intval($_GPC['isnew']),
          'isdiscount' => intval($_GPC['isdiscount']),
          'istime' => intval($_GPC['istime']),
          'timestart' => strtotime($_GPC['timestart']),
          'timeend' => strtotime($_GPC['timeend']),
          'isminimode' => intval($_GPC['isminimode']),
          'description' => $_GPC['description'],
          'content' => htmlspecialchars_decode($_GPC['content']),
          'cover_content' => htmlspecialchars_decode($_GPC['cover_content']),
          'secret_content' => htmlspecialchars_decode($_GPC['secret_content']),
          'spec' => htmlspecialchars_decode($_GPC['spec']),
          'goodssn' => $_GPC['goodssn'],
          'unit' => $_GPC['unit'],
          'createtime' => TIMESTAMP,
          'total' => intval($_GPC['total']),
          'totalcnf' => intval($_GPC['totalcnf']),
          'marketprice' => $_GPC['marketprice'],
          'weight' => $_GPC['weight'],
          'costprice' => $_GPC['costprice'],
          'productprice' => $_GPC['productprice'],
          'productsn' => $_GPC['productsn'],
          'credit' => intval($_GPC['credit']),
          'credit2' => floatval($_GPC['credit2']),
          'maxbuy' => intval($_GPC['maxbuy']),
          'hasoption' => intval($_GPC['hasoption']),
          'sales' => intval($_GPC['sales']),
          'status' => intval($_GPC['status']),
          'timelinetitle' => $_GPC['timelinetitle'],
          'timelinedesc' => $_GPC['timelinedesc'],
          'killdiscount' => $_GPC['killdiscount'],
          'killmindiscount' => $_GPC['killmindiscount'],
          'killtotaldiscount' => $_GPC['killtotaldiscount'],
          'killmaxtime' => intval($_GPC['killmaxtime']),
          'killenable' => intval($_GPC['killenable']),
          'min_visible_level' => intval($_GPC['min_visible_level']),
          'min_buy_level' => intval($_GPC['min_buy_level']),
          'promote_to_level' => intval($_GPC['promote_to_level']),
          'rate1' => floatval($_GPC['rate1']),
          'rate2' => floatval($_GPC['rate2']),
          'rate3' => floatval($_GPC['rate3']),
          'use_abs_commission' => intval($_GPC['use_abs_commission']),
          'max_coupon_credit' => floatval($_GPC['max_coupon_credit']),
          'dealeropenid' => $_GPC['dealeropenid'],
          'thumb' => $_GPC['thumb'],
          'timelinethumb' => $_GPC['timelinethumb'],
          'lastedituser' => $_W['username'],
          'permusers' => $permusers,
          'thumb_url' => serialize($_GPC['thumb_url']),
        );

        if (empty($id)) {
          $id = $_goods->create($data);
        } else {
          unset($data['createtime']);
          $_goods->update($_W['uniacid'], $id, $data);
        }
        message('商品更新成功！', $this->createWebUrl('goods', array('op' => 'post', 'id' => $id)), 'success');
      }

      // 获取所有子规格
      if (!empty($item['id']) and empty($item['pgoodsid'])) {
        list($subspecs, $total) = $_goods->batchGetSubSpec($_W['uniacid'], $item['id']);
      }
      if (!empty($item['pgoodsid'])) {
        $parent = $_goods->get($item['pgoodsid']);
      }

      // 获取运费模板
      $dispatch = $_dispatch->getUnique($_W['uniacid']);


    } elseif ($operation == 'display') {
      // 批量更新displayorder
      $children = array();
      foreach ($category as $index => $row) {
        if (!empty($row['parentid'])) {
          $children[$row['parentid']][$row['id']] = $row;
          unset($category[$index]);
        }
      }

      if (!empty($_GPC['displayorder'])) {
        foreach ($_GPC['displayorder'] as $id => $displayorder) {
          $_goods->update($_W['uniacid'], $id, array('displayorder' => $displayorder));
        }
        message('分类排序更新成功！', $this->createWebUrl('goods', array('op' => 'display')), 'success');
      }
      $pindex = max(1, intval($_GPC['page']));
      $psize = 40;
      list($list, $total) = $_goods->batchGet($_W['uniacid'], $_GPC, $pindex, $psize);
      $pager = pagination($total, $pindex, $psize);
    } elseif ($operation == 'delete') {
      $id = intval($_GPC['id']);
      $row = $_goods->get($id);
      if (empty($row)) {
        message('抱歉，商品不存在或是已经被删除！');
      }
      $_goods->markDelete($_W['uniacid'], $id);
      message('删除成功！', referer(), 'success');
    }
    include $this->template('goods');
  }

  /* done */
  public function doWebOrder() {
    global $_W, $_GPC;

    $this->doWebAuth();

    yload()->classs('quickshop', 'order');
    yload()->classs('quickshop', 'dispatch');
    yload()->classs('quickshop', 'address');
    yload()->classs('quickshop', 'express');
    yload()->classs('quickcenter', 'FormTpl');
    yload()->classs('quickcenter', 'wechatutil');

    $_order = new Order();
    $_dispatch = new Dispatch();
    $_address = new Address();
    $_express = new Express();

    $operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';
    if ($operation == 'display') {
      if (checksubmit('batchconfirmreceived')) {
        yload()->classs('quickcenter', 'fans');
        $_fans = new Fans();
        $skip = 0;
        foreach ($_GPC['orderid'] as $id) {
          $order = $_order->get($id);
          if (empty($order)) {
            continue;
          }
          // 发货1天内的订单不支持批量收货
          if (TIMESTAMP - $order['updatetime'] < 1*60*60*24 && Order::$ORDER_DELIVERED == $order['status']) {
            $skip++;
            continue;
          }
          $_order->update($_W['uniacid'], $id, array('status' => Order::$ORDER_RECEIVED));
          $this->notifyUser($_W['uniacid'], $id, 'notifyAdminConfirmed');
          if (Order::$PAY_DELIVERY == $order['paytype']) {
            /*** 注意，仅仅在线支付才赠送购物积分、提升VIP等级，这也可以成为一个卖点 ***/
            $this->setVIP($_fans, $order['weid'], $order['from_user']);
          }
        }
        message('订单批量确认收货操作成功！' . ($skip==0?'':'其中有'.$skip.'个订单由于未满1天自动跳过'), referer(), 'success');
      } else if (checksubmit('batchremoveorder')) {
        foreach ($_GPC['orderid'] as $id) {
          $order = $_order->get($id);
          if (empty($order)) {
            continue;
          }
          if ($order['status'] == Order::$ORDER_NEW or $order['status'] == Order::$ORDER_CANCEL) {
            $_order->remove($_W['uniacid'], $id);
          }
        }
        message('批量删除订单操作成功！', $this->createWebUrl('Order', array('status' => Order::$ORDER_NEW)), 'success');
      }


      $pindex = max(1, intval($_GPC['page']));
      $psize = 20;
      // status '1取消状态，2普通状态，3为已付款，4为已发货，5为成功', 6为管理员确认交易完成(无纠纷)
      // sendtype '1为快递，2为自提. 2暂不使用,总为快递',
      // paytype '1为余额，2为在线，3为到付',
      // goodstype  '1为实体，2为虚拟'
      $status = !isset($_GPC['status']) ? 3 : $_GPC['status'];
      //$sendtype = !isset($_GPC['sendtype']) ? 1 : $_GPC['sendtype'];
      //$conds = array('status'=>$status, 'sendtype'=>$sendtype);
      $conds = array('status'=>$status);
      if (isset($_GPC['search']) && !empty($_GPC['search'])) {
        yload()->classs('quickshop', 'ordersearch');
        $_search = new OrderSearch();
        $conds[$_GPC['searchtype']] = trim($_GPC['search']);
        $pindex = 1; $psize = 100000; // all show in one page
        list($list, $total) = $_search->search($_W['uniacid'], $conds, null, $pindex, $psize);
        $pager = pagination($total, $pindex, $psize); // don't page it
      } else {
        list($list, $total) = $_order->batchGet($_W['uniacid'], $conds, null, $pindex, $psize);
        $pager = pagination($total, $pindex, $psize);
      }
      if (!empty($list)) {
        foreach ($list as &$row) {
          !empty($row['addressid']) && $addressids[$row['addressid']] = $row['addressid'];
          $row['dispatch'] = $_dispatch->get($row['dispatch']);
        }
        unset($row);
      }
      if (!empty($addressids)) {
        $address = $_address->batchGetByIds($_W['uniacid'], $addressids, 'id');
      }
      $status_text = $_order->getOrderStatusName($status);
    } elseif ($operation == 'detail') {
      $id = intval($_GPC['id']);
      $item = $_order->get($id);
      if (empty($item)) {
        message("抱歉，订单不存在!", referer(), "error");
      }
      if (checksubmit('confirmsend')) {
        if (!empty($_GPC['isexpress']) && empty($_GPC['expresssn'])) {
          message('请输入快递单号！');
        }
        if (!empty($item['transid'])) {
          // TODO: 通过微信接口通知订单取消
         // $this->changeWechatSend($id, 1);
        }
        if ($item['sendtype'] == Dispatch::$EXPRESS) {
          $order_next_status = Order::$ORDER_DELIVERED;
        } else {
          $order_next_status = Order::$ORDER_RECEIVED;
        }
        $data = array(
          'status' => $order_next_status,
          'remark' => $_GPC['remark'],
          'express' => $_GPC['express'],
          'expresscom' => $_GPC['expresscom'],
          'expresssn' => $_GPC['expresssn'],
        );
        $_order->update($_W['uniacid'], $id, $data);
        $this->notifyUser($_W['uniacid'], $id, 'notifyDelivered');
        message('发货操作成功！', referer(), 'success');
      }
      if (checksubmit('addremark')) {
        $data = array(
          'remark' => $_GPC['remark'],
        );
        $_order->update($_W['uniacid'], $id, $data);
        message('更新备注成功！', referer(), 'success');
      }
      if (checksubmit('cancelsend')) {
        if (!empty($item['transid'])) {
          // TODO: 通过微信接口通知订单取消
          // $this->changeWechatSend($id, 0, $_GPC['cancelreson']);
        }
        $data = array(
          'status' => Order::$ORDER_PAYED,
          'remark' => $_GPC['remark'],
        );
        $_order->update($_W['uniacid'], $id, $data);
        message('取消发货操作成功！', referer(), 'success');
      }
      if (checksubmit('finish')) {
        $_order->update($_W['uniacid'], $id, array('status' => Order::$ORDER_RECEIVED, 'remark' => $_GPC['remark']));
        $this->notifyUser($_W['uniacid'], $id, 'notifyAdminConfirmed');

        if (Order::$PAY_DELIVERY == $item['paytype']) {
          /*** 注意，仅仅在线支付才赠送购物积分、提升VIP等级，这也可以成为一个卖点 ***/
          yload()->classs('quickcenter', 'fans');
          $_fans = new Fans();
          $this->setVIP($_fans, $item['weid'], $item['from_user']);
          message('订单操作成功！用户VIP等级提升成功。', referer(), 'success');
        } else {
          message('订单操作成功！', referer(), 'success');
        }
      }
      if (checksubmit('cancelpay')) {
        $_order->update($_W['uniacid'], $id, array('status' => Order::$ORDER_NEW, 'remark' => $_GPC['remark']));

        //设置库存
        $this->setOrderStock($id, false);
        //减少积分
        $this->setOrderCredit($id, false);
        message('取消订单付款操作成功！', referer(), 'success');
      }
      if (checksubmit('remove')) {
        $order = $_order->get($id);
        if (empty($order)) {
          message('订单已经删除，无需重复删除！', referer(), 'error');
        }
        if ($order['status'] == Order::$ORDER_NEW or $order['status'] == Order::$ORDER_CANCEL) {
          $_order->remove($_W['uniacid'], $id);
          message('删除订单操作成功！', $this->createWebUrl('Order', array('status' => Order::$ORDER_NEW)), 'success');
        } else {
          message('该状态下订单不允许删除！', referer(), 'error');
        }
      }
      if (checksubmit('confirmpay')) {
        $order = $_order->get($id);
        if ($order['status'] == Order::$ORDER_NEW) {
          $_order->update($_W['uniacid'], $id, array('status' => Order::$ORDER_PAYED, 'paytype'=> Order::$PAY_ONLINE, 'remark' => $_GPC['remark']));
          $transid = '';
          $this->onOrderPayedSuccess($_order, $order['weid'], $order['from_user'], $id, $transid, Order::$PAY_ONLINE);
          message('确认订单付款操作成功！', referer(), 'success');
        } else {
          message('订单状态不是待支付，无法支付！', referer(), 'error');
        }
      }
      if (checksubmit('close')) {
        $item = $_order->get($id);
        if (!empty($item['transid'])) {
          // TODO: 通过微信接口通知订单取消
          // $this->changeWechatSend($id, 0, $_GPC['reson']);
        }
        $_order->update($_W['uniacid'], $id, array('status' => Order::$ORDER_CANCEL, 'remark' => $_GPC['remark']));
        message('订单关闭操作成功！', referer(), 'success');
      }
      if (checksubmit('open')) {
        $_order->update($_W['uniacid'], $id, array('status' => Order::$ORDER_NEW, 'remark' => $_GPC['remark']));
        message('开启订单操作成功！', referer(), 'success');
      }
      if (checksubmit('changeprice')) {
        $_order->update($_W['uniacid'], $id, array('price' => floatval($_GPC['newprice'])));
        message('改价成功！', referer(), 'success');
      }
      if (checksubmit('talktouser')) {
        $msg = $_GPC['talktouser_msg'];
        $openid = $_GPC['openid'];
        yload()->classs('quickcenter', 'custommsg');
        if (empty($msg)) {
          message('请输入消息内容！', referer(), 'error');
        } else if (empty($openid)) {
          message('请指定接收消息的用户的OpenID！', referer(), 'error');
        }
        $_custommsg = new CustomMsg();
        $ret = $_custommsg->sendText($_W['uniacid'], $openid, $msg);
        message('发送消息成功！', referer(), 'success');
      }
      $dispatch = $_dispatch->get($item['dispatch']);
      if (!empty($dispatch) && !empty($dispatch['express'])) {
        $express = $_express->get($dispatch['express']);
      }
      $item['user'] = $_address->get($item['addressid']);
      yload()->classs('quickcenter', 'fans');
      $_fans = new Fans();
      $fans = $_fans->get($_W['uniacid'], $item['from_user']);
      $goods = $_order->getDetailedGoods($id);
      $item['goods'] = $goods;

      // 计算上线返利
      yload()->classs('quickdist', 'memberorder');
      $_memberorder = new MemberOrder();
      $commission = $_memberorder->calcCommission($_W['uniacid'], $id);
      foreach ($commission as $c) {
        $ids[] = $c['from_user'];
      }
      $com_fans = $_fans->batchGetByOpenids($_W['uniacid'], $ids, 'openid');
    }
    include $this->template('order');
  }

  //设置订单商品的库存 minus  true 减少  false 增加
  private function setOrderStock($id = '', $minus = true) {
    global $_W;

    yload()->classs('quickshop', 'goods');
    yload()->classs('quickshop', 'order');
    $_goods = new Goods();
    $_order = new Order();

    $goods = $_order->getDetailedGoods($id);
    foreach ($goods as $item) {
      // 对于秒杀商品，不走setOrderStock, 只走setGoodsStock
      if ($item['totalcnf'] != 3) {
        $this->setGoodsStock($item['id'], $item['totalcnf'], $item['goodstotal'], $item['total'], $item['sales'], $minus);
      }
    }
  }

  /**
   * @totalcnf : 减库存模式，-2表示永不减库存
   * @stock : 库存
   * @buyamount : 购买数量
   * @sold : 已售数量
   * @minus : 1 = 减库存/ 0 = 加库存
   */
  private function setGoodsStock($id, $totalcnf, $stock, $buyamount, $sold, $minus = true) {
    global $_W;

    yload()->classs('quickshop', 'goods');
    $_goods = new Goods();

    $data = array();
    if ($minus) {
      if ($totalcnf != 2) { // 不是永不减库存
        if (!empty($stock) && $stock != -1) {  // $stock -1 表示永不减库存
          $data['total'] = $stock - $buyamount;
          if ($data['total'] == -1) { $data['total'] == 0; }  // 防止减库存为-1
        }
      }
      $data['sales'] = $sold + $buyamount;
    } else {
      if ($totalcnf != 2) { // 不是永不减库存
        if (!empty($stock) && $stock != -1) {
          $data['total'] = $stock + $buyamount;
          if ($data['total'] == -1) { $data['total'] == 0; } // 防止减库存为-1
        }
      }
      $data['sales'] = $sold - $buyamount;
    }
    $_goods->update($_W['uniacid'], $id, $data);
  }

  public function getCartTotal() {
    global $_W;
    yload()->classs('quickshop', 'cart');
    $_cart = new Cart();
    $cartotal = $_cart->total($_W['uniacid'], $_W['fans']['from_user']);
    return empty($cartotal) ? 0 : $cartotal;
  }

  private function tryLink() {
    global $_GPC, $_W, $_COOKIE;
    yload()->classs('quicklink', 'translink');
    $_link = new TransLink();
    $_link->preLink($_W['uniacid'], $_GPC['shareby']);

    WeUtility::logging("shareby", array('GPC'=>$_GPC['shareby'], 'cookie'=>$_COOKIE['shareby'.$_W['uniacid']], 'fans'=>$_W['fans']['from_user']));

    if ($_GPC['shareby'] != $_W['fans']['from_user']) {
      $_link->link($_W['uniacid'], $_W['fans']);
    }
  }

  public function doMobileList() {
    global $_GPC, $_W, $_SERVER;
    yload()->classs('quickcenter', 'fans');
    $_fans = new Fans();

    $this->tryLink();

    // 强制要求通过微信浏览器打开
    $this->forceOpenInWechat();

    $title = $_W['account']['name'];
    //$mc_fans = mc_oauth_userinfo();
    $mc_fans = mc_fansinfo($_W['fans']['uid']);
    if (!empty($_GPC['shareby']) && !empty($this->module['config']['enable_inshop_mode'])) {
      $shopowner = $_fans->get($_W['uniacid'], $_GPC['shareby']);
      isetcookie('sharebyck', $_GPC['shareby'], 60*60*24*7); // 将店主持久化下来，让消费者有错觉
      $shopowner['shopname'] = $this->getShopname($shopowner['nickname']);
    } else if (!empty($mc_fans) && $mc_fans['follow'] == 1) {
      // 如果关注了公众号，始终显示官方店
      $shopowner['shopname'] = $this->module['config']['shopname'];
      $shopowner['avatar'] = empty($this->module['config']['inshop_logo']) ? $_W['attachurl'] . "headimg_{$_W['acid']}.jpg" : toimage($this->module['config']['inshop_logo']);
      $shopowner['uid'] = '001';
    } else if (!empty($_GPC['sharebyck'])  && !empty($this->module['config']['enable_inshop_mode']) ) {
      $shopowner = $_fans->get($_W['uniacid'], $_GPC['sharebyck']);
      $shopowner['shopname'] = $this->getShopname($shopowner['nickname']);
    } else {
      $shopowner = $_fans->refresh($_W['uniacid'], $_W['fans']['from_user']);
      $shopowner['shopname'] = $this->module['config']['shopname'];
      $shopowner['avatar'] = empty($this->module['config']['inshop_logo']) ? $_W['attachurl'] . "headimg_{$_W['acid']}.jpg" : toimage($this->module['config']['inshop_logo']);
      $shopowner['uid'] = '001';
    }

    $fans = $_fans->refresh($_W['uniacid'], $_W['fans']['from_user']);
    $vip_cond = array('min_visible_level'=>$fans['vip']);

    if (!empty($this->module['config']['enable_single_goods_id'])) {
      yload()->classs('quickshop', 'goods');
      $_goods = new Goods();
      $item = $_goods->get(intval($this->module['config']['enable_single_goods_id']));
      yload()->classs('quickcenter', 'template');
      $_template = new Template($this->module['name']);
      $_W['account']['template'] = $this->getTemplateName();

      //对分享时的数据处理

      $shareby_str = empty($_W['fans']['from_user']) ? '' : '&shareby=' . $_W['fans']['from_user']; //单品模式下，未关注好友，显示关注推荐按钮
      $share = array();
      $share['title'] = empty($item['timelinetitle']) ? $item['title'] : $item['timelinetitle'];
      $share['content'] = empty($item['timelinedesc']) ? null : $item['timelinedesc'];
      $share['img'] = empty($item['timelinethumb']) ? null : $_W['attachurl'] . $item['timelinethumb'];
      yload()->classs('quickcenter', 'wechatutil');
      $share['link'] = WechatUtil::createMobileUrl('List', 'quickshop', array('shareby'=>$_W['fans']['from_user']));

      if (!empty($_GPC['shareby'])) {
        $share_fans = $_fans->fans_search_by_openid($_W['uniacid'], $_GPC['shareby']);
      }

      // 对付费后可见内容的处理
      $showSecret = false;
      if (!empty($item['secret_content']) && !empty($_W['fans']['from_user'])) {
        $showSecret = $_goods->hasBuy($_W['uniacid'], $_W['fans']['from_user'], $item['id']);
      }

      include $_template->template('index');
      exit(0);
    }

    $pindex = max(1, intval($_GPC['page']));
    $psize = 2000;

    yload()->classs('quickshop', 'goods');
    yload()->classs('quickshop', 'category');
    yload()->classs('quickshop', 'advertise');
    yload()->classs('quickshop', 'advlink');
    $_goods = new Goods();
    $_category = new Category();
    $_advertise = new Advertise();

    $children = array();
    $category = $_category->batchGet($_W['uniacid'], array('enabled'=>1), 'id');
    foreach ($category as $index => $row) {
      if (!empty($row['parentid'])) {
        $children[$row['parentid']][$row['id']] = $row;
        unset($category[$index]);
      }
    }

    $recommendcategory = array();
    foreach ($category as &$c) {
      // 所有分类都首页可见。下一期实现支持更多商品
      //if ($c['isrecommend'] == 1) {
      $vip_cond['isrecommend'] = 1;
        list($c['list'], $c['total']) = $_goods->batchGetByPrimaryCategory($_W['uniacid'], $c['id'], $vip_cond, $pindex, $psize);
        $c['pager'] = pagination($c['total'], $pindex, $psize, $url = '', $context = array('before' => 0, 'after' => 0, 'ajaxcallback' => ''));
        $recommendcategory[] = $c;
      //}
    }
    unset($c);
    $carttotal = $this->getCartTotal();
    $goodstotal = $_goods->batchGetCount($_W['uniacid']);
    $newtotal = $_goods->batchGetCount($_W['uniacid'], array('isnew'=>1));

    //幻灯片
    $advs = $_advertise->batchGet($_W['uniacid'], array('type'=>0));
    $banners = $_advertise->batchGet($_W['uniacid'], array('type'=>1));

    // 自定义链接
    $advlinks = AdvLink::batchGet($_W['uniacid']);

    //首页推荐
    $rpindex = max(1, intval($_GPC['rpage']));
    $rpsize = 6;
    list($rlist, $rtotal) = $_goods->batchGetByHot($_W['uniacid'], $vip_cond, $rpindex, $rpsize);


    //对分享时的数据处理
    $shareby_str = empty($_W['fans']['from_user']) ? '' : '&shareby=' . $_W['fans']['from_user']; //单品模式下，未关注好友，显示关注推荐按钮
    $share = array();
    $share['title'] = $shopowner['shopname'];
    $share['content'] = $this->module['config']['inshop_share_text']; //$title;
    $share['img'] = $shopowner['avatar'];
    // 如果开启了店中店模式, 则分享的时候显示用户头像
    if (!empty($this->module['config']['enable_inshop_mode'])) {
      $share['title'] = (empty($fans['nickname'])) ? $shopowner['shopname'] : $this->getShopname($fans['nickname']);
      $share['content'] = $this->module['config']['inshop_share_text']; //$title;
      $share['img'] = empty($fans['avatar']) ? $shopowner['avatar'] : $fans['avatar'];
    }
    yload()->classs('quickcenter', 'wechatutil');
    $share['link'] = WechatUtil::createMobileUrl('List', 'quickshop', array('shareby'=>$_W['fans']['from_user']));

    yload()->classs('quickcenter', 'template');
    $_template = new Template($this->module['name']);
    $_W['account']['template'] = $this->getTemplateName();
    include $_template->template('list');
  }

  // 更多推荐商品
  public function doMobilelistmore_rec() {
    global $_GPC, $_W;

    yload()->classs('quickshop', 'goods');
    $_goods = new Goods();

    $pindex = max(1, intval($_GPC['page']));
    $psize = 6;
    $list = $_goods->batchGetByRecommend($_W['uniacid'], $rpindex, $rpsize);
    include $this->template('list_more');
  }

  // 一级分类下的详细商品
  public function doMobileList2() {
    global $_GPC, $_W;

    // 强制要求通过微信浏览器打开
    $this->forceOpenInWechat();

    yload()->classs('quickshop', 'goods');
    yload()->classs('quickshop', 'category');
    yload()->classs('quickcenter', 'fans');
    $_fans = new Fans();
    $_goods = new Goods();
    $_category = new Category();

    $fans = $_fans->refresh($_W['uniacid'], $_W['fans']['from_user']);

    $pindex = max(1, intval($_GPC['page']));
    $psize = 600000;
    $pcateid = intval($_GPC['pcate']);
    $ccateid = intval($_GPC['ccate']);
    if (empty($ccateid)) {
      $category = $_category->get($pcateid);
    } else {
      $category = $_category->get($ccateid);
    }

    $categories = $_category->batchGet($_W['uniacid'], array('parentid'=>$pcateid));
    $vip_cond = array('min_visible_level'=>$fans['vip']);
    if (empty($ccateid)) {
      list($list, $total) =  $_goods->batchGetByPrimaryCategory($_W['uniacid'], $pcateid, $vip_cond, $pindex, $psize);
    } else {
      //$category = $_category->get($pcateid, $ccateid);
      list($list, $total) =  $_goods->batchGetBySecondaryCategory($_W['uniacid'], $pcateid, $ccateid, $vip_cond, $pindex, $psize);
    }

    //对分享时的数据处理
    $title = $category['name'];
    $shareby_str = empty($_W['fans']['from_user']) ? '' : '&shareby=' . $_W['fans']['from_user']; //单品模式下，未关注好友，显示关注推荐按钮
    $share = array();
    $share['title'] = $category['name'];
    $share['content'] = $category['description'];
    $share['img'] = '';
    yload()->classs('quickcenter', 'wechatutil');
    $share['link'] = WechatUtil::createMobileUrl('List2', 'quickshop', array('shareby'=>$_W['fans']['from_user'], 'pcate'=> $pcateid, 'ccate' => $ccateid));

    $carttotal = $this->getCartTotal();

    yload()->classs('quickcenter', 'template');
    $_template = new Template($this->module['name']);
    $_W['account']['template'] = $this->getTemplateName();
    include $_template->template('list2');

  }

  // 更多某种特征商品
  public function doMobileList3() {
    global $_GPC, $_W;
    yload()->classs('quickshop', 'goods');
    $_goods = new Goods();
    $categories = array('isnew', 'ishot', 'isdiscount', 'isrecommend', 'istime');
    if (in_array($_GPC['category'], $categories)) {
      // nop
    } elseif (empty($_GPC['category'])) {
      message('没有指定显示类别', '', 'error');
    }

    $carttotal = $this->getCartTotal();

    yload()->classs('quickcenter', 'template');
    $_template = new Template($this->module['name']);
    $_W['account']['template'] = $this->getTemplateName();
    include $_template->template('list3');
  }


  // 更多某种特征商品
  public function doMobileListByTag() {
    global $_GPC, $_W;
    yload()->classs('quickshop', 'goods');
    $_goods = new Goods();
    $categories = array('isnew', 'ishot', 'isdiscount', 'isrecommend', 'istime');
    if (in_array($_GPC['category'], $categories)) {
      // nop
    } elseif (empty($_GPC['category'])) {
      message('没有指定显示类别', '', 'error');
    }


    $carttotal = $this->getCartTotal();

    $pindex = max(1, intval($_GPC['page']));
    $psize = 600000;
    $cond = array($_GPC['category']=>1);
    list($list, $total) =  $_goods->batchGet($_W['uniacid'], $cond, $pindex, $psize);

    yload()->classs('quickcenter', 'template');
    $_template = new Template($this->module['name']);
    $_W['account']['template'] = $this->getTemplateName();
    include $_template->template('list_bytag');
  }

  // 搜索结果
  public function doMobileSearch() {
    global $_GPC, $_W;
    yload()->classs('quickshop', 'goods');
    $_goods = new Goods();


    $carttotal = $this->getCartTotal();

    if (empty($_GPC['keyword'])) {
      $title = '商品搜索';
    } else {
      $title = $_GPC['keyword'] . ' - 商品搜索';
    }

    $_GPC['status'] = 1;

    if (!empty($_GPC['keyword'])) {
      $pindex = max(1, intval($_GPC['page']));
      $psize = 200;
      list($list, $total) = $_goods->batchGet($_W['uniacid'], $_GPC, $pindex, $psize);
      $pager = pagination($total, $pindex, $psize);
    }

    yload()->classs('quickcenter', 'template');
    $_template = new Template($this->module['name']);
    $_W['account']['template'] = $this->getTemplateName();
    include $_template->template('search-result');
  }

  // 导航
  public function doMobileNav() {
    global $_GPC, $_W;

    // 强制要求通过微信浏览器打开
    $this->forceOpenInWechat();

    $pindex = max(1, intval($_GPC['page']));
    $psize = 2000;

    yload()->classs('quickshop', 'category');
    $_category = new Category();

    $children = array();
    $category = $_category->batchGet($_W['uniacid'], array('enabled'=>1));
    $level_mode = 1;
    foreach ($category as $index => $row) {
      if (!empty($row['parentid'])) {
        $level_mode = 2;
        $children[$row['parentid']][] = $row;
        unset($category[$index]);
      }
    }

    $title = '导航';
    //对分享时的数据处理
    $shareby_str = empty($_W['fans']['from_user']) ? '' : '&shareby=' . $_W['fans']['from_user']; //单品模式下，未关注好友，显示关注推荐按钮
    $share = array();
    $share['title'] = $title;
    $share['content'] = $title;
    $share['img'] = '';
    yload()->classs('quickcenter', 'wechatutil');
    $share['link'] = WechatUtil::createMobileUrl('Nav', 'quickshop', array('shareby'=>$_W['fans']['from_user']));

    yload()->classs('quickcenter', 'template');
    $_template = new Template($this->module['name']);
    $_W['account']['template'] = $this->getTemplateName();
    if (1 == $level_mode) {
      include $_template->template('nav');
    } else {
      include $_template->template('nav2');
    }
  }


  // 导航, 只显示一级分类
  public function doMobileNavPrimary() {
    global $_GPC, $_W;

    // 强制要求通过微信浏览器打开
    $this->forceOpenInWechat();

    $pindex = max(1, intval($_GPC['page']));
    $psize = 2000;

    yload()->classs('quickshop', 'category');
    $_category = new Category();

    $category = $_category->batchGet($_W['uniacid'], array('enabled'=>1));
    foreach ($category as $index => $row) {
      if (!empty($row['parentid'])) {
        unset($category[$index]);
      }
    }

    $title = '导航';
    //对分享时的数据处理
    $shareby_str = empty($_W['fans']['from_user']) ? '' : '&shareby=' . $_W['fans']['from_user']; //单品模式下，未关注好友，显示关注推荐按钮
    $share = array();
    $share['title'] = $title;
    $share['content'] = $title;
    $share['img'] = '';
    yload()->classs('quickcenter', 'wechatutil');
    $share['link'] = WechatUtil::createMobileUrl('Nav', 'quickshop', array('shareby'=>$_W['fans']['from_user']));

    yload()->classs('quickcenter', 'template');
    $_template = new Template($this->module['name']);
    $_W['account']['template'] = $this->getTemplateName();

    include $_template->template('nav_primary');
  }

  // 导航, 只显示某个一级分类下的二级分类
  public function doMobileNavSecondary() {
    global $_GPC, $_W;
    $cid = intval($_GPC['pcate']);
    if (empty($cid)) {
      message('参数错误，没有提供分类ID');
    }

    // 强制要求通过微信浏览器打开
    $this->forceOpenInWechat();

    $pindex = max(1, intval($_GPC['page']));
    $psize = 2000;

    yload()->classs('quickshop', 'category');
    $_category = new Category();

    $category = $_category->batchGet($_W['uniacid'], array('enabled'=>1, 'parentid'=>$cid));

    $title = '导航';
    //对分享时的数据处理
    $shareby_str = empty($_W['fans']['from_user']) ? '' : '&shareby=' . $_W['fans']['from_user']; //单品模式下，未关注好友，显示关注推荐按钮
    $share = array();
    $share['title'] = $title;
    $share['content'] = $title;
    $share['img'] = '';
    yload()->classs('quickcenter', 'wechatutil');
    $share['link'] = WechatUtil::createMobileUrl('Nav', 'quickshop', array('shareby'=>$_W['fans']['from_user']));

    yload()->classs('quickcenter', 'template');
    $_template = new Template($this->module['name']);
    $_W['account']['template'] = $this->getTemplateName();
    include $_template->template('nav_secondary');
  }


  function time_tran($the_time) {

    $timediff = $the_time - time();
    $days = intval($timediff / 86400);
    if (strlen($days) <= 1) {
      $days = "0" . $days;
    }
    $remain = $timediff % 86400;
    $hours = intval($remain / 3600);
    ;
    if (strlen($hours) <= 1) {
      $hours = "0" . $hours;
    }
    $remain = $remain % 3600;
    $mins = intval($remain / 60);
    if (strlen($mins) <= 1) {
      $mins = "0" . $mins;
    }
    $secs = $remain % 60;
    if (strlen($secs) <= 1) {
      $secs = "0" . $secs;
    }
    $ret = "";
    if ($days > 0) {
      $ret.=$days . " 天 ";
    }
    if ($hours > 0) {
      $ret.=$hours . ":";
    }
    if ($mins > 0) {
      $ret.=$mins . ":";
    }

    $ret.=$secs;

    return array("倒计时 " . $ret, $timediff);
  }

  public function doMobileMyCart() {
    global $_W, $_GPC;

    $title = $_W['account']['name'];

    $share = array();
    $share['disable'] = true;


    yload()->classs('quickshop', 'goods');
    yload()->classs('quickshop', 'cart');
    $_goods = new Goods();
    $_cart = new Cart();


    $this->checkAuth();

    $op = $_GPC['op'];
    if ($op == 'add') {
      $result = 1;
      $goodsid = intval($_GPC['id']);
      $total = intval($_GPC['total']);
      $total = empty($total) ? 1 : $total;
      $goods = $_goods->get($goodsid);
      if (empty($goods)) {
        $result['message'] = '抱歉，该商品不存在或是已经被删除！';
        message($result, '', 'ajax');
      }
      $marketprice = $goods['marketprice'];
      $row = $_cart->getByGoodsId($_W['uniacid'], $_W['fans']['from_user'], $goodsid);
      // 计算是否超过限额
      $t = $total + $row['total'];
      if (!empty($goods['maxbuy'])) {
        if ($t > $goods['maxbuy']) {
          $result = 0;
        }
      }

      if ($result) {
        if ($row == false) {
          //不存在
          $data = array(
            'weid' => $_W['uniacid'],
            'goodsid' => $goodsid,
            'goodstype' => $goods['goodstype'],
            'marketprice' => $marketprice,
            'from_user' => $_W['fans']['from_user'],
            'total' => $total,
            'optionid' => $optionid
          );
          $_cart->create($data);
        } else {
          //累加最多限制购买数量
          $t = $total + $row['total'];
          if (!empty($goods['maxbuy'])) {
            if ($t > $goods['maxbuy']) {
              $t = $goods['maxbuy'];
            }
          }
          $_cart->update($_W['uniacid'], $_W['fans']['from_user'], $row['id'], $t);
          //存在
        }
      }

      //返回数据
      $carttotal = $this->getCartTotal();

      $result = array(
        'result' => $result,
        'total' => $carttotal,
        'maxbuy' => $goods['maxbuy'],
      );
      die(json_encode($result));
    } else if ($op == 'clear') {
      $_cart->clear($_W['uniacid'], $_W['fans']['from_user']);
      header('Location:' . $this->createMobileUrl('MyCart'));
      exit(0);
      // die(json_encode(array("result" => 1)));
    } else if ($op == 'remove') {
      $id = intval($_GPC['id']);
      $_cart->remove($_W['uniacid'], $_W['fans']['from_user'], $id);
      header('Location:' . $this->createMobileUrl('MyCart'));
      exit(0);
      // die(json_encode(array("result" => 1, "cartid" => $id)));
    } else if ($op == 'update') {
      // 从购物车增加待购商品数量
      $id = intval($_GPC['id']);
      $new_amount = intval($_GPC['num']);
      $_cart->update($_W['uniacid'], $_W['fans']['from_user'], $id, $new_amount);
      die(json_encode(array("result" => 1)));
    } else {
      $list = $_cart->batchGet($_W['uniacid'], $_W['fans']['from_user']);
      $totalprice = 0;
      if (!empty($list)) {
        foreach ($list as &$item) {
          $goods = $_goods->get($item['goodsid']);
          $item['goods'] = $goods;
          $item['totalprice'] = (floatval($goods['marketprice']) * intval($item['total']));
          $totalprice += $item['totalprice'];
        }
        unset($item);
      }

      $carttotal = $this->getCartTotal();

      yload()->classs('quickcenter', 'template');
      $_template = new Template($this->module['name']);
      $_W['account']['template'] = $this->getTemplateName();
      include $_template->template('cart');

    }
  }

  public function doMobileConfirm() {
    global $_W, $_GPC;

    $share = array();
    $share['disable'] = true;

    $this->checkAuth();

    $title = $_W['account']['name'];

    yload()->classs('quickshop', 'goods');
    yload()->classs('quickshop', 'dispatch');
    yload()->classs('quickshop', 'express');
    yload()->classs('quickshop', 'address');
    yload()->classs('quickshop', 'cart');
    yload()->classs('quickshop', 'order');
    yload()->classs('quickcenter', 'fans');

    $_goods = new Goods();
    $_dispatch = new Dispatch();
    $_express = new Express();
    $_address = new Address();
    $_cart = new Cart();
    $_order = new Order();
    $_fans = new Fans();

    $totalprice = 0;
    $allgoods = array();
    $profile = $_fans->get($_W['uniacid'], $_W['fans']['from_user'],
      array('resideprovince', 'residecity', 'residedist', 'address', 'realname', 'mobile', 'credit1', 'credit2', 'vip', 'from_user'));

    $id = intval($_GPC['id']);
    $optionid = intval($_GPC['optionid']);
    $total = intval($_GPC['total']);
    if (empty($total)) {
      $total = 1;
    }
    $direct = false; //是否是直接购买
    $returnurl = ""; //当前连接

    $goodstype = Goods::$VIRTUAL_GOODS;
    $sendtype =  Dispatch::$PICKUP;
    if (!empty($id)) {
      // 有id则表示直接购买，不是从购物车购买
      $item = $_goods->get($id);
      // 0. 检查时间
      $this->checkGoodsTime($item);
      // 1. 检查是否超过单人最大购买限额
      $this->checkMaxBuy($_order, $_W['uniacid'], $_W['fans']['from_user'], $item['id'], $item['maxbuy'], $total, $item['title']);
      // 2. 检查库存
      $this->checkSoldOut($item['id'], $item['total'], $total, $item['title']);
      // 3. 检查通过，开卖
      $item['stock'] = $item['total'];
      $item['total'] = $total;
      $item['totalprice'] = $total * $item['marketprice'];
      $allgoods[] = $item;
      $totalprice+= $item['totalprice'];
      if ($item['goodstype'] == Goods::$PHYSICAL_GOODS) {
        $needdispatch = true;
        $goodstype = Goods::$PHYSICAL_GOODS; //实物
      }
      // not implemented
      $sendtype = $item['sendtype'];
      $direct = true;
      $returnurl = $this->createMobileUrl("confirm", array("id" => $id, "optionid" => $optionid, "total" => $total));
    }
    if (!$direct) {
      //如果不是直接购买（从购物车购买）
      $list = $_cart->batchGet($_W['uniacid'], $_W['fans']['from_user']);
      if (!empty($list)) {
        foreach ($list as &$g) {
          $item = $_goods->get($g['goodsid']);
          // 0. 检查售卖时间
          $this->checkGoodsTime($item, 1 /* is batch */);
          // 1. 检查该用户是否已经达到最大购买量。 此时已减库存，但用户无法下单
          $this->checkMaxBuy($_order, $_W['uniacid'], $_W['fans']['from_user'], $item['id'], $item['maxbuy'], $g['total'], $item['title']);
          // 2. 检查库存。如果库存不足，直接提示下单失败.
          $this->checkSoldOut($item['id'], $item['total'], $g['total'], $item['title']);
          // 3. 检查库存通过，开卖。秒杀模式下检查库存情况
          $item['stock'] = $item['total'];
          $item['total'] = $g['total'];
          $item['totalprice'] = $g['total'] * $item['marketprice'];
          $allgoods[] = $item;
          $totalprice+= $item['totalprice'];
          if ($item['goodstype'] == Goods::$PHYSICAL_GOODS) {
            $needdispatch = true;
            $goodstype = Goods::$PHYSICAL_GOODS; //实物
          }
          // not implemented in goods['sendtype']
          if ($item['sendtype'] == Dispatch::$EXPRESS) {
            $sendtype =  Dispatch::$EXPRESS;
          }
        }
        unset($g);
      }
      $returnurl = $this->createMobileUrl("confirm");
    }

    if (count($allgoods) <= 0) {
      header("location: " . $this->createMobileUrl('myorder'));
      exit();
    }

    //配送方式
    // output $dispatchprice
    $weight = 0;
    foreach ($allgoods as $g) {
      $weight += $g['weight'] * $g['total'];
    }
    $dispatchprice = $this->calcDispatchPrice($_dispatch, $_W['uniacid'], $weight);
    $totalprice += $dispatchprice;

    //
    //
    ///// 提交订单 /////////////////////////////////
    //
    //
    if (checksubmit('submit')) {
      // TODO
      // 这里应该根据商品属性，选择是否强制要求输入收货地址
      //
      $addressid = intval($_GPC['addressid']);
      if (empty($_GPC['realname']) || empty($_GPC['mobile'])) {
        message('请输完善您的资料！姓名:' . $_GPC['realname'] . ' 手机:' . $_GPC['mobile']);
      }
      if ($goodstype == Goods::$PHYSICAL_GOODS && empty($_GPC['address'])) {
        message('请输完善您的资料！姓名:' . $_GPC['realname'] . ' 手机:' . $_GPC['mobile']. ' 地址:' . $_GPC['address']);
      }

      if (empty($_GPC['addressid'])) {
        $data = array(
          'isdefault' => 1, /* always default for the inserted address */
          'weid' => $_W['uniacid'],
          'openid' => $_W['fans']['from_user'],
          'realname' => $_GPC['realname'],
          'mobile' => $_GPC['mobile'],
          'province' => $_GPC['province'],
          'city' => $_GPC['city'],
          'area' => $_GPC['area'],
          'address' => $_GPC['address'],
        );
        $addressid = $_address->create($data);
        $address = $_address->get($addressid);
      } else {
        $data = array(
          'isdefault' => 1, /* always default for the inserted address */
          'weid' => $_W['uniacid'],
          'openid' => $_W['fans']['from_user'],
          'realname' => $_GPC['realname'],
          'mobile' => $_GPC['mobile'],
          'province' => $_GPC['province'],
          'city' => $_GPC['city'],
          'area' => $_GPC['area'],
          'address' => $_GPC['address'],
        );
        if (false === ($address = $_address->find($data))) {
          $addressid = $_address->addDefault($_W['uniacid'], $_W['fans']['from_user'], $data);
          $address = $_address->get($addressid);
          // $_address->update($_W['uniacid'], $addressid, $data);
        }
      }
      if (empty($address)) {
        message('抱歉，请您填写收货地址！');
      }

      //
      // 折扣
      // 积分抵扣
      $discount = 0;
      $creditused = 0;
      if (1 == intval($_GPC['usecredit'])) {
        foreach ($allgoods as $row) {
          $discount += $row['max_coupon_credit'] * $row['total'];
        }
        $user_owned_max_discount = floatval($profile['credit1']) / 100;
        $discount = min($user_owned_max_discount, $discount); // 如果用户积分不足, 则尽力抵扣
        $creditused = $discount * 100;
      }

      if (1 == intval($_GPC['usecredit'])) {
        //  检查积分是否足够
        //  能够继续进行的条件是：账户积分 >= 本次购物所用积分($creditused) + 未支付订单已经占用的积分($pendding_credit)
        $pending_conds = array('from_user'=>$profile['from_user']);
        list($pending_list, $pending_total) = $_order->batchGetNew($_W['uniacid'], $pending_conds, null, 1, 10000000);
        $pendding_credit = 0;
        foreach ($pending_list as $p) {
          $pendding_credit += $p['creditused'];
        }
        if ($profile['credit1'] < $pendding_credit + $creditused) {
          if ($pendding_credit > 0) {
            message('对不起，你还有未完成的订单，请先支付这些订单。', $this->createMobileUrl('MyOrder'), 'error');
          } else {
            message('对不起，你的积分不够，无法完成抵扣', '', 'error');
          }
        }
      }

      //商品价格
      $goodsprice = 0;
      foreach ($allgoods as $row) {
        // 拍下减库存模式下，已经检查过库存，并且库存数已减, 无需再次检查
        $this->checkSoldOut($row['id'], $row['stock'], $row['total'], $row['title']);
        // 拍下减库存, 用于秒杀模式
        if ($row['totalcnf'] == 3) {
          $this->checkMaxOrderedGoodsCount($_order, $_W['uniacid'], $_W['fans']['from_user'], $row['id'], $row['maxbuy'], $row['total'], $row['title']);
          $this->setGoodsStock($row['id'], $row['totalcnf'], $row['stock'], $row['total'], $row['sales']);
        }
        $goodsprice+= $row['totalprice'];
      }
      //运费
      // TODO: 客户端还没有设置运费选择，所以现在运费总是0
      //  $dispatchid = intval($_GPC['dispatch']);
      //  $dispatchprice = 0;
      //  foreach ($dispatch as $d) {
      //    if ($d['id'] == $dispatchid) {
      //      $dispatchprice = $d['price'];
      //    }
      //  }
      //
      $data = array(
        'weid' => $_W['uniacid'],
        'from_user' => $_W['fans']['from_user'],
        'ordersn' => date('mdHis') . random(5, 1),
        'price' => $goodsprice + $dispatchprice - $discount,
        'dispatchprice' => $dispatchprice,
        'goodsprice' => $goodsprice,
        'discount' => $discount,
        'usecredit' => intval($_GPC['usecredit']),
        'creditused' => $creditused,
        'status' => Order::$ORDER_NEW,
        'sendtype' => intval($_GPC['sendtype']),
        'dispatch' => $dispatchid,
        'paytype' => Order::$PAY_ONLINE,
        'goodstype' => $goodstype,
        'remark' => $_GPC['remark'],
        'addressid' => $address['id'],
        'createtime' => TIMESTAMP,
        'updatetime' => TIMESTAMP,
      );
      $orderid = $_order->create($data);
      //插入订单商品
      foreach ($allgoods as $row) {
        if (empty($row)) {
          continue;
        }
        $d = array(
          'weid' => $_W['uniacid'],
          'goodsid' => $row['id'],
          'orderid' => $orderid,
          'total' => $row['total'],
          'ordergoodsprice' => $row['marketprice'],
          'createtime' => TIMESTAMP,
          'optionid' => $row['optionid']
        );
        $_order->addGoods($d);
      }
      //清空购物车
      if (!$direct) {
        $_cart->clear($_W['uniacid'], $_W['fans']['from_user']);
      }
      die("<script>location.href='" . $this->createMobileUrl('pay', array('orderid' => $orderid)) . "';</script>");
    }
    //
    //
    //
    ///// 结束提交订单 //////
    //
    //
    //
    $carttotal = $this->getCartTotal();
    $row = $_address->getDefault($_W['uniacid'], $_W['fans']['from_user']);

    $totalcredit = 0;
    $allgoods_id = array();
    foreach($allgoods as $g) {
      $allgoods_id[] = $g['id'];
      $totalcredit += $g['max_coupon_credit'] * $g['total'];
    }
    $totalcredit = min($totalcredit, $profile['credit1'] / 100.0);

    $carttotal = $this->getCartTotal();

    yload()->classs('quickcenter', 'template');
    $_template = new Template($this->module['name']);
    $_W['account']['template'] = $this->getTemplateName();
    // $sendtype = $goodstype; // Dispatch::$EXPRESS;  // 快递
    include $_template->template('confirm');
  }

  //设置订单积分
  public function setOrderCredit($orderid, $add = true) {
    global $_W;

    yload()->classs('quickshop', 'goods');
    yload()->classs('quickshop', 'order');
    yload()->classs('quickcenter', 'fans');
    $_goods = new Goods();
    $_order = new Order();
    $_fans = new Fans();

    $order = $_order->get($orderid);
    if (empty($order)) {
      return;
    }
    // 使用微信支付或余额支付都送积分, 货到付款不送积分
    if (Order::$PAY_ONLINE == $order['paytype'] or Order::$PAY_CREDIT == $order['paytype']) {
      $ordergoods = $_order->getGoods($orderid, 'goodsid');
      if (!empty($ordergoods)) {
        $goods = $_goods->batchGetByIds($_W['uniacid'], array_keys($ordergoods));
      }

      // 提升VIP
      if (!empty($goods)) {
        $max_vip = 0;
        foreach ($goods as $g) {
          $max_vip = max($max_vip, $g['promote_to_level']);
        }
        if (!empty($max_vip)) {
          $_fans->setVIP($_W['uniacid'], $order['from_user'], $max_vip);
        }
      }

      //增加积分
      if (!empty($goods)) {
        $credits = 0;
        foreach ($goods as $g) {
          $credits+=($g['credit'] * $ordergoods[$g['id']]['total']);
        }
        if ($credits > 0) {
          if ($add) {
            $_fans->addCredit($_W['uniacid'], $order['from_user'], $credits, 1, '购物送积分');
          } else {
            $_fans->addCredit($_W['uniacid'], $order['from_user'], 0 - $credits, 1, '取消订单减积分');
          }
        }
      }
      if (!empty($goods)) {
        $credits = 0;
        foreach ($goods as $g) {
          $credits+=($g['credit2'] * $ordergoods[$g['id']]['total']);
        }
        if ($credits > 0) {
          if ($add) {
            $_fans->addCredit($_W['uniacid'], $order['from_user'], $credits, 2, '购物返现金');
          } else {
            $_fans->addCredit($_W['uniacid'], $order['from_user'], 0 - $credits, 2, '取消订单扣积分');
          }
        }
      }
    }

    $discount_credit = $order['creditused'];
    if ($discount_credit > 0) {
      if ($add) {
        $_fans->addCredit($_W['uniacid'], $order['from_user'], 0 - $discount_credit, 1, '积分换购消耗积分');
      } else {
        $_fans->addCredit($_W['uniacid'], $order['from_user'], $discount_credit, 1, '取消订单, 送还换购积分');
      }
    }
  }

  public function doMobilePay() {
    global $_W, $_GPC;

    $share = array();
    $share['disable'] = true;

    $this->checkAuth();

    $title = $_W['account']['name'];

    yload()->classs('quickshop', 'order');
    $_order = new Order();

    $orderid = intval($_GPC['orderid']);
    $order = $_order->get($orderid);
    if (empty($order)) {
      message('非法订单!','', 'error');
    }
    if ($order['status'] != Order::$ORDER_NEW) {
      message('抱歉，您的订单已经付款或是被关闭，请重新进入付款！', $this->createMobileUrl('myorder'), 'error');
    }
    // 检查是否已经买足了限额，防止超卖。这里特别针对已经生成了订单，通过我的订单下单的场景
    $allgoods = $_order->getDetailedGoods($orderid);
    $isSupportDelivery = true;
    foreach ($allgoods as $row) {
      // 拍下减库存, 用于秒杀模式
      if ($row['support_delivery'] == 0) {
        $isSupportDelivery = false;
        $_W['account']['payment']['delivery']['switch'] = 'OFF';
      }
      $this->checkMaxBuy($_order, $_W['uniacid'], $_W['fans']['from_user'], $row['id'], $row['maxbuy'], $row['total'], $row['title']);
    }

    //****** 货到付款:codsubmit ******
    /*
    if (checksubmit('deliverysubmit')) {
      if (false == $isSupportDelivery) {
        message('对不起，您选择的商品不支持货到付款，请使用微信在线支付', referer(), 'error');
      }
      //更新订单状态
      $_order->clientUpdate($_W['uniacid'], $_W['fans']['from_user'], $orderid, array('status' => Order::$ORDER_PAYED, 'paytype' => Order::$PAY_DELIVERY));
      //TODO: 这里可以增加更多提醒方式，如打印、下发微信通知等
      $transid = '';
      $this->onOrderPayedSuccess($_order, $order['weid'], $order['from_user'], $orderid, $transid, Order::$PAY_DELIVERY);
      message('恭喜您，订单提交成功，我们会尽快与您取得联系，请保持电话畅通！', $this->createMobileUrl('myorder'), 'success');
    }
     */


    //******* 第三方支付  ********
    $params['tid'] = $orderid;
    $params['user'] = $order['from_user'];
    $params['fee'] = $order['price'];
    $params['title'] = $_W['account']['name'];
    $params['ordersn'] = $order['ordersn'];

    $carttotal = $this->getCartTotal();

    yload()->classs('quickcenter', 'template');
    $_template = new Template($this->module['name']);
    $_W['account']['template'] = $this->getTemplateName();
    include $_template->template('pay');
  }

  public function doMobileContactUs() {
    global $_W;
    $cfg = $this->module['config'];

    yload()->classs('quickcenter', 'template');
    $_template = new Template($this->module['name']);
    $_W['account']['template'] = $this->getTemplateName();
    include $_template->template('contactus');

  }

  public function doMobileMyOrder() {
    global $_W, $_GPC;

    $share = array();
    $share['disable'] = true;


    $title = $_W['account']['name'];

    $this->checkAuth();

    yload()->classs('quickshop', 'order');
    yload()->classs('quickshop', 'goods');
    yload()->classs('quickshop', 'dispatch');
    yload()->classs('quickshop', 'address');

    $_order = new Order();
    $_dispatch = new Dispatch();
    $_address = new Address();


    $op = $_GPC['op'];
    if ($op == 'remove') {

      if (empty($this->module['config']['enable_user_remove_order'])) {
        message('抱歉，暂不支持删除订单！', $this->createMobileUrl('myorder'), 'error');
      }

      $orderid = intval($_GPC['orderid']);
      $order = $_order->get($orderid);
      if (empty($order)) {
        message('抱歉，您的订单不存或是已经被取消！', $this->createMobileUrl('myorder'), 'error');
      }
      if ($order['status'] != Order::$ORDER_NEW and $order['status'] != Order::$ORDER_CANCEL) {
        message('抱歉，仅支持删除尚未付款或已取消订单', $this->createMobileUrl('myorder'), 'error');
      }
      if ($order['status'] == Order::$ORDER_NEW or $order['status'] == Order::$ORDER_CANCEL) {
        $_order->clientRemove($_W['uniacid'], $_W['fans']['from_user'], $orderid);
      }
      header('Location:' . $this->createMobileUrl('MyOrder'));
      exit(0);

    }  else if ($op == 'confirm') {

      $orderid = intval($_GPC['orderid']);
      $order = $_order->get($orderid);
      if (empty($order)) {
        message('抱歉，您的订单不存或是已经被取消！', $this->createMobileUrl('myorder'), 'error');
      }
      if ($order['status'] != Order::$ORDER_RECEIVED) {
        $_order->clientUpdate($_W['uniacid'], $_W['fans']['from_user'], $orderid, array('status' => Order::$ORDER_RECEIVED));
        $this->notifyUser($_W['uniacid'], $orderid, 'notifyReceived');
        if (Order::$PAY_DELIVERY == $order['paytype']) {
          yload()->classs('quickcenter', 'fans');
          $_fans = new Fans();
          $this->setVIP($_fans, $order['weid'], $order['from_user']);
        }
      }
      message('确认成功！', $this->createMobileUrl('myorder'), 'success');
    } else if ($op == 'detail') {

      $orderid = intval($_GPC['orderid']);
      $item = $_order->clientGet($_W['uniacid'], $_W['fans']['from_user'], $orderid);
      //$item = $_order->get($orderid);
      if (empty($item)) {
        message('抱歉，您的订单不存或是已经被取消！订单号:' . $orderid, $this->createMobileUrl('myorder'), 'error');
      }
      $item['goods'] = $_order->getDetailedGoods($item['id']);
      $item['total'] = 199999;
      $item['address'] = $_address->get($item['addressid']);
      $item['sendtype'] = Dispatch::$PICKUP;
      foreach ($item['goods'] as $_it) {
        if (Dispatch::$PICKUP != $_it['sendtype']) {
          $item['sendtype'] =  $_it['sendtype'];
          break;
        }
      }

      $carttotal = $this->getCartTotal();

      yload()->classs('quickcenter', 'template');
      $_template = new Template($this->module['name']);
      $_W['account']['template'] = $this->getTemplateName();
      include $_template->template('order-detail');

    } else {

      $pindex = max(1, intval($_GPC['page']));
      $psize = 999;
      $status = intval($_GPC['status']);
      list($list, $total) = $_order->batchGet($_W['uniacid'], array('from_user'=>$_W['fans']['from_user'], 'status'=>$status), 'id', $pindex, $psize);
      $pager = pagination($total, $pindex, $psize);

      if (!empty($list)) {
        $sendtype =  Dispatch::$PICKUP;
        foreach ($list as &$row) {
          $goods = $_order->getDetailedGoods($row['id']);;
          $row['goods'] = $goods;
          $row['total'] = 199999;
          $row['dispatch'] = $_dispatch->get($row['dispatch']);
          $row['address'] = $_address->get($row['addressid']);
          $row['sendtype'] = Dispatch::$PICKUP;
          foreach ($goods as $_it) {
            if (Dispatch::$PICKUP != $_it['sendtype']) {
              $row['sendtype'] =  $_it['sendtype'];
              break;
            }
          }
        }
      }


      $carttotal = $this->getCartTotal();

      yload()->classs('quickcenter', 'template');
      $_template = new Template($this->module['name']);
      $_W['account']['template'] = $this->getTemplateName();
      include $_template->template('order');

    }
  }

  public function doMobileDetail() {
    global $_W, $_GPC;

    $this->tryLink();

    // 强制要求通过微信浏览器打开
    // $this->forceOpenInWechat();


    $title = $_W['account']['name'];

    yload()->classs('quickshop', 'goods');
    yload()->classs('quickcenter', 'fans');
    $_goods = new Goods();
    $_fans = new Fans();

    $fans = $_fans->refresh($_W['uniacid'], $_W['fans']['from_user']);

    $goodsid = intval($_GPC['id']);
    $goods = $_goods->get($goodsid);
    if (empty($goods)) {
      message('抱歉，商品不存在或是已经被删除！');
    } else if ($goods['status'] == 0) {
      message('抱歉，本商品已经下架！带你去看看其他的吧。', $this->createMobileUrl('List', array('m'=>'quickshop')), 'success');
    }
    /*
    if ($goods['totalcnf'] != 2 && $goods['total'] <= 0) {
      message('抱歉，商品库存不足！');
    }
     */
    //浏览量
    $_goods->updateViewCount($_W['uniacid'], $goodsid);
    if ($goods['thumb_url'] != 'N;') {
      $piclist = unserialize($goods['thumb_url']);
    }
    if (empty($piclist)) {
      $piclist[] = $goods['thumb'];
    }

    $marketprice = $goods['marketprice'];
    $productprice= $goods['productprice'];
    $stock = $goods['total'];
    $timelast = intval($goods['timeend'] - TIMESTAMP); //$goods['timestart']);
    $timewait = intval($goods['timestart'] - TIMESTAMP);

    $carttotal = $this->getCartTotal();

    //对分享时的数据处理
    $share = array();
    $share['title'] = empty($goods['timelinetitle']) ? $goods['title'] : $goods['timelinetitle'];
    $share['content'] = empty($goods['timelinedesc']) ? null : $goods['timelinedesc'];
    $share['img'] = empty($goods['timelinethumb']) ? null : $_W['attachurl'] . $goods['timelinethumb'];
    yload()->classs('quickcenter', 'wechatutil');
    $share['link'] = WechatUtil::createMobileUrl('Detail', 'quickshop', array('id'=>$goodsid, 'shareby'=>$_W['fans']['from_user']));

    // 对多规格的处理
    if (!empty($goods['spec']) and 1) {
      yload()->classs('quickshop', 'specparser');
      $specs = SpecParser::parse($_W['uniacid'], $goods['spec']);
    }
    // 对付费后可见内容的处理
    $showSecret = false;
    if (!empty($goods['secret_content']) && !empty($_W['fans']['from_user'])) {
      $showSecret = $_goods->hasBuy($_W['uniacid'], $_W['fans']['from_user'], $goodsid);
    }

    yload()->classs('quickcenter', 'template');
    $_template = new Template($this->module['name']);
    $_W['account']['template'] = $this->getTemplateName();
    include $_template->template('detail');

  }

  public function doMobileAddress() {
    global $_W, $_GPC;

    $share = array();
    $share['disable'] = true;


    $title = $_W['account']['name'];

    yload()->classs('quickshop', 'address');
    $_address = new Address();

    $from = $_GPC['from'];
    $returnurl = urldecode($_GPC['returnurl']);
    $this->checkAuth();
    $operation = $_GPC['op'];

    if ($operation == 'post') {
      $id = intval($_GPC['id']);
      $data = array(
        'weid' => $_W['uniacid'],
        'openid' => $_W['fans']['from_user'],
        'realname' => $_GPC['realname'],
        'mobile' => $_GPC['mobile'],
        'province' => $_GPC['province'],
        'city' => $_GPC['city'],
        'area' => $_GPC['area'],
        'address' => $_GPC['address'],
      );
      if (empty($_GPC['realname']) || empty($_GPC['mobile']) || empty($_GPC['address'])) {
        message('请输完善您的资料！');
      }
      if (!empty($id)) {
        unset($data['weid']);
        unset($data['openid']);
        $_address->update($_W['uniacid'], $id, $data);
        message($id, '', 'ajax');
      } else {
        $id = $_address->addDefault($_W['uniacid'], $_W['fans']['from_user'], $data);
        if (!empty($id)) {
          message($id, '', 'ajax');
        } else {
          message(0, '', 'ajax');
        }
      }
    } elseif ($operation == 'default') {
      $id = intval($_GPC['id']);
      $_address->changeDefault($_W['uniacid'], $_W['fans']['from_user'], $id);
      message(1, '', 'ajax');
    } elseif ($operation == 'detail') {
      $id = intval($_GPC['id']);
      $row = $_address->get($id);
      message($row, '', 'ajax');
    } elseif ($operation == 'remove') {
      $id = intval($_GPC['id']);
      $default = 0;
      if (!empty($id)) {
        $address = $_address->clientGet($_W['uniacid'], $_W['fans']['from_user'], $id);
        if (!empty($address)) {
          //修改成不直接删除，而设置deleted=1
          $default = $_address->markDelete($weid, $_W['fans']['from_user'], $id, $address['isdefault']);
        }
      }
      die(json_encode(array("result" => 1, "maxid" => $default)));
    } else {

      yload()->classs('quickcenter', 'fans');
      $_fans = new Fans();
      $profile = $_fans->fans_search_by_openid($_W['uniacid'], $_W['fans']['from_user'], array('resideprovince', 'residecity', 'residedist', 'address', 'realname', 'mobile'));
      $address = $_address->batchGet($_W['uniacid'], array('openid' => $_W['fans']['from_user']));
      $carttotal = $this->getCartTotal();

      yload()->classs('quickcenter', 'template');
      $_template = new Template($this->module['name']);
      $_W['account']['template'] = $this->getTemplateName();
      include $_template->template('address');

    }
  }

  private function checkAuth() {
    global $_W;
    $this->MyCheckauth();
  }

  private function MyCheckauth($redirect = true) {
    global $_W;
    // $_W['fans']['from_user'] = 'oKknPt-ISf8ldd3OzigH2wrnhhM0';
    //$_W['fans'] = pdo_fetch("select * from " . tablename('fans') . " Where from_user = 'oKknPt-ISf8ldd3OzigH2wrnhhM0'");

    if (empty($_W['fans']['from_user'])) {
      if ($redirect) {
        $follow = $this->module['config']['followurl'];
        if (!empty($follow)) {
          header('Location: ' . $follow);
          exit();
        } else {
          checkauth(); // call system auth
        }
      }
    }
  }


  public function payResult2() {
    yload()->classs('quickcenter', 'wechatutil');
    $url = WechatUtil::createMobileUrl('myorder', 'quickshop');
    $this->message('微信支付成功！', $url, 'success');
  }

  public function payResult($params) {
    global $_W;
    yload()->classs('quickshop', 'order');
    yload()->classs('quickcenter', 'wechatsetting');
    $_setting = new WechatSetting();
    $_order = new Order();
    $order = $_order->get($params['tid']);
    $setting = $_setting->get($params['uniacid'], 'quickshop');
    yload()->classs('quickcenter', 'wechatutil');
    $url = WechatUtil::createMobileUrl('myorder', 'quickshop');

    if ($params['type'] ==  'wechat') {
      if ($params['from'] == 'return') {
        if ($order['status'] == Order::$ORDER_PAYED) {
          WeUtility::logging('payResultConfirm Payed already', $params);
          if (1 == $setting['enable_auto_close_window_after_pay']) {
            $this->message('微信支付成功！', null, 'success');
          } else {
            $this->message('微信支付成功！', $url, 'success');
          }
        } else if ($order['status'] == Order::$ORDER_NEW) {
          if ($params['result'] != 'success') {
            $this->message('微信支付失败，请检查订单状态，如显示为未支付，请联系客服！错误码：' . $params['result'], $url, 'success');
          } else {
            $this->onOrderPayedSuccess($_order, $params['uniacid'], $params['user'], $params['tid'], $params['tag']['transaction_id'], Order::$PAY_ONLINE);
            WeUtility::logging('payResult Pay Done', $params);
            $this->mn = 'quickshop';
            if (1 == $setting['enable_auto_close_window_after_pay']) {
              $this->message('微信支付成功！', null, 'success');
            } else {
              $this->message('微信支付成功！', $url, 'success');
            }
          }
        } // end NewOrder
      }// end from return

      // 补单机制
      if ($params['from'] == 'notify') {
        if ($order['status'] == Order::$ORDER_PAYED) {
          return;  // return quietely
        } else if ($order['status'] == Order::$ORDER_NEW) {
          if ($params['result'] == 'success') {
            $this->onOrderPayedSuccess($_order, $params['uniacid'], $params['user'], $params['tid'], $params['tag']['transaction_id'], Order::$PAY_ONLINE);
          } else {
            $this->onOrderPayedFail($_order, $params['uniacid'], $params['user'], $params['tid'], $params['tag']['transaction_id']);
          }
          WeUtility::logging('payResultNotify Pay Done', $params);
        }
      } // end Notify callback from wechat server
    }

    if ($params['type'] ==  'delivery') {
      if ($params['from'] == 'return') {
        if ($order['status'] == Order::$ORDER_PAYED) {
          WeUtility::logging('payResultConfirm Payed(delivery) already', $params);
          $this->message('已经下单成功！', $url, 'success');
        } else if ($order['status'] == Order::$ORDER_NEW) {
          if ($params['result'] == 'success') { // BUG: 这里支付层错误码设置有误
            $this->message('货到付款支付失败，请检查订单状态，如显示为未支付，请联系客服！', $url, 'success');
          } else {
            /*
            if (false == $isSupportDelivery) {
              message('对不起，您选择的商品不支持货到付款，请使用微信在线支付', referer(), 'error');
            }
            */
            //更新订单状态
            $_order->clientUpdate($_W['uniacid'], $order['from_user'], $order['id'], array('status' => Order::$ORDER_PAYED, 'paytype' => Order::$PAY_DELIVERY));
            $transid = '';
            $this->onOrderPayedSuccess($_order, $order['weid'], $order['from_user'], $order['id'], $transid, Order::$PAY_DELIVERY);
            message('恭喜您，订单提交成功，我们会尽快与您取得联系，请保持电话畅通！', $this->createMobileUrl('myorder'), 'success');
          }
        } // end NewOrder
      }// end from return
    }

    if ($params['type'] ==  'credit') {
      if ($params['from'] == 'return') {
        if ($order['status'] == Order::$ORDER_PAYED) {
          WeUtility::logging('payResultConfirm Payed(delivery) already', $params);
          $this->message('已经下单成功！', $url, 'success');
        } else if ($order['status'] == Order::$ORDER_NEW) {
          if ($params['result'] != 'success') {
            $this->message('余额付款支付失败，请检查订单状态，如显示为未支付，请联系客服！', $url, 'success');
          } else {
            /*
            if (false == $isSupportDelivery) {
              message('对不起，您选择的商品不支持货到付款，请使用微信在线支付', referer(), 'error');
            }
            */
            //更新订单状态
            $_order->clientUpdate($order['weid'], $order['from_user'], $order['id'], array('status' => Order::$ORDER_PAYED, 'paytype' => Order::$PAY_CREDIT));
            $transid = '';
            $this->onOrderPayedSuccess($_order, $order['weid'], $order['from_user'], $order['id'], $transid, Order::$PAY_CREDIT);
            if (1 == $setting['enable_auto_close_window_after_pay']) {
              $this->message('余额支付成功！', null, 'success');
            } else {
              $this->message('余额支付成功！', $url, 'success');
            }
          }
        } // end NewOrder
      }// end from return
    }
  }

  private function onOrderPayedSuccess($_order, $weid, $from_user, $orderid, $transid, $paytype) {
    yload()->classs('quickcenter', 'wechatsetting');
    $_setting = new WechatSetting();
    $setting = $_setting->get($weid, 'quickshop');

    $data = array('status' => Order::$ORDER_PAYED, 'transid' => $transid);
    $_order->clientUpdate($weid, $from_user, $orderid, $data);
    //邮件提醒
    if (false and !empty($setting['noticeemail'])) {
      yload()->classs('quickshop', 'orderemail');
      $_mail = new OrderEmail();
      $_mail->send($order);
    }
    //////// 各种奖励处理 ////// TODO：做成消息队列
    if (Order::$PAY_ONLINE == $paytype or Order::$PAY_CREDIT == $paytype) {
      /*** 注意，仅仅在线支付才赠送购物积分、提升VIP等级，这也可以成为一个卖点 ***/
      yload()->classs('quickcenter', 'fans');
      $_fans = new Fans();
      $this->setVIP($_fans, $weid, $from_user);
    }
    $this->setOrderCredit($orderid); //赠送积分
    ///////// END奖励处理 //////


    //变更商品库存
    $this->setOrderStock($orderid, true);

    //// 通知发货
    $param = array('weid'=>$weid, 'orderid'=>$orderid, 'template_id'=>$setting['payed_template_id']);
    if (0) {
      WeUtility::logging('going to push to queue', $param);
      yload()->classs('quickdynamics', 'messagequeue');
      $mq = new MessageQueue();
      $mq->push('quickshop', 'ordernotifier', 'OrderNotifier', 'notifyPayed', $param);
      //// 结束通知发货
    } else {
      /* */
      yload()->classs('quickshop', 'ordernotifier');
      $_notifier = new OrderNotifier();
      $_notifier->notifyPayed($param);
    }
    /// 主动推送一个二维码给用户
    if (1) {
      yload()->classs('quickdynamics', 'messagequeue');
      $mq = new MessageQueue();
      $mq->push('quickshop', 'ordernotifier', 'OrderNotifier', 'notifyQR', $param);
    }

  }


  private function onOrderPayedFail($_order, $weid, $from_user, $orderid, $transid) {
    // 这种情况极少，只做改订单状态处理
    $data = array('status' => Order::$ORDER_FAIL, 'transid' => $transid);
    $_order->clientUpdate($weid, $from_user, $orderid, $data);
  }

  private function notifyUser($weid, $orderid, $method) {
    yload()->classs('quickcenter', 'wechatsetting');
    $_setting = new WechatSetting();
    $setting = $_setting->get($weid, 'quickshop');

    //// 通知用户
    yload()->classs('quickdynamics', 'messagequeue');
    $param = array('weid'=>$weid, 'orderid'=>$orderid, 'template_id'=>$setting['payed_template_id']);
    WeUtility::logging('going to push to queue', $param);
    $mq = new MessageQueue();
    $mq->push('quickshop', 'ordernotifier', 'OrderNotifier', $method, $param);

    //// 结束通知
  }

  //  public function doWebExpress() {
  //    global $_W, $_GPC;
  //
  //    $this->doWebAuth();
  //
  //    yload()->classs('quickshop', 'express');
  //    $_express = new Express();
  //
  //
  //    $operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';
  //    if ($operation == 'display') {
  //      $list = $_express->batchGet($_W['uniacid']);
  //    } elseif ($operation == 'post') {
  //      $id = intval($_GPC['id']);
  //      if (checksubmit('submit')) {
  //        if (empty($_GPC['express_name'])) {
  //          message('抱歉，请输入物流名称！');
  //        }
  //        $data = array(
  //          'weid' => $_W['uniacid'],
  //          'displayorder' => intval($_GPC['express_name']),
  //          'express_name' => $_GPC['express_name'],
  //          'express_url' => $_GPC['express_url'],
  //          'express_area' => $_GPC['express_area'],
  //        );
  //        if (!empty($id)) {
  //          unset($data['parentid']);
  //          $_express->update($_W['uniacid'], $id, $data);
  //        } else {
  //          $id = $_express->create($data);
  //        }
  //        message('更新物流成功！', $this->createWebUrl('express', array('op' => 'display')), 'success');
  //      }
  //      //修改
  //      $express = $_express->get($id);
  //    } elseif ($operation == 'delete') {
  //      $id = intval($_GPC['id']);
  //      $express = $_express->get($id);
  //      if (empty($express)) {
  //        message('抱歉，物流方式不存在或是已经被删除！', $this->createWebUrl('express', array('op' => 'display')), 'error');
  //      }
  //      $_express->remove($_W['uniacid'], $id);
  //      message('物流方式删除成功！', $this->createWebUrl('express', array('op' => 'display')), 'success');
  //    } else {
  //      message('请求方式不存在');
  //    }
  //    include $this->template('express', TEMPLATE_INCLUDEPATH, true);
  //  }

  public function doWebDispatch() {
    global $_W, $_GPC;

    $this->doWebAuth();

    yload()->classs('quickcenter', 'FormTpl');
    yload()->classs('quickshop', 'dispatch');
    $_dispatch = new Dispatch();

    $id = intval($_GPC['id']);
    if (checksubmit('submit')) {
      $data = array(
        'weid' => $_W['uniacid'],
        'displayorder' => intval($_GPC['dispatch_name']),
        'dispatchtype' => intval($_GPC['dispatchtype']),
        'dispatchname' => $_GPC['dispatchname'],
        'express' => $_GPC['express'],
        'firstprice' => $_GPC['firstprice'],
        'firstweight' => $_GPC['firstweight'],
        'secondprice' => $_GPC['secondprice'],
        'secondweight' => $_GPC['secondweight'],
        'description' => $_GPC['description'],
        'province' => $_GPC['sel-provance'],
        'city' => $_GPC['sel-city'],
        'area' => $_GPC['sel-area'],
      );
      if (!empty($id)) {
        $_dispatch->update($_W['uniacid'], $id, $data);
      } else {
        $id = $_dispatch->create($data);
      }
      message('更新快递模板成功！', $this->createWebUrl('dispatch'), 'success');
    }

    // 修改
    if (!empty($id)) {
      $dispatch = $_dispatch->get($id);
    } else {
      $dispatchs = $_dispatch->batchGet($_W['uniacid']);
      $dispatch = $dispatchs[0];
    }

    include $this->template('dispatch', TEMPLATE_INCLUDEPATH, true);
  }

  public function doWebAdv() {
    global $_W, $_GPC;

    $this->doWebAuth();

    load()->func('tpl');
    yload()->classs('quickcenter', 'FormTpl');
    yload()->classs('quickshop', 'advertise');
    $_advertise = new Advertise();

    $operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';
    if ($operation == 'display') {
      $conds = array('display' => 'all', 'type'=>0);
      $list = $_advertise->batchGet($_W['uniacid'], $conds);
    } elseif ($operation == 'post') {

      $id = intval($_GPC['id']);
      if (checksubmit('submit')) {
        $data = array(
          'weid' => $_W['uniacid'],
          'advname' => htmlspecialchars_decode($_GPC['advname']),
          'link' => $_GPC['link'],
          'enabled' => intval($_GPC['enabled']),
          'displayorder' => intval($_GPC['displayorder']),
          'thumb' => $_GPC['thumb'],
          'type' => 0, /* is top slider */
        );
        if (!empty($id)) {
          $_advertise->update($_W['uniacid'], $id, $data);
        } else {
          $id = $_advertise->create($data);
        }
        message('更新幻灯片成功！', $this->createWebUrl('adv', array('op' => 'display')), 'success');
      }
      $adv = $_advertise->get($id);
    } elseif ($operation == 'delete') {
      $id = intval($_GPC['id']);
      $adv = $_advertise->get($id);
      if (empty($adv)) {
        message('抱歉，幻灯片不存在或是已经被删除！', $this->createWebUrl('adv', array('op' => 'display')), 'error');
      }
      $_advertise->remove($_W['uniacid'], $id);
      message('幻灯片删除成功！', $this->createWebUrl('adv', array('op' => 'display')), 'success');
    } else {
      message('请求方式不存在');
    }
    include $this->template('adv', TEMPLATE_INCLUDEPATH, true);
  }


  public function doWebBanner() {
    global $_W, $_GPC;

    $this->doWebAuth();

    load()->func('tpl');
    yload()->classs('quickcenter', 'FormTpl');
    yload()->classs('quickshop', 'advertise');
    $_advertise = new Advertise();

    $operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';
    if ($operation == 'display') {
      $conds = array('display' => 'all', 'type'=>1);
      $list = $_advertise->batchGet($_W['uniacid'], $conds);
    } elseif ($operation == 'post') {

      $id = intval($_GPC['id']);
      if (checksubmit('submit')) {
        $data = array(
          'weid' => $_W['uniacid'],
          'advname' => htmlspecialchars_decode($_GPC['advname']),
          'link' => $_GPC['link'],
          'enabled' => intval($_GPC['enabled']),
          'displayorder' => intval($_GPC['displayorder']),
          'thumb' => $_GPC['thumb'],
          'type' => 1, /* is banner */
        );
        if (!empty($id)) {
          $_advertise->update($_W['uniacid'], $id, $data);
        } else {
          $id = $_advertise->create($data);
        }
        message('更新推荐栏目成功！', $this->createWebUrl('banner', array('op' => 'display')), 'success');
      }
      $adv = $_advertise->get($id);
    } elseif ($operation == 'delete') {
      $id = intval($_GPC['id']);
      $adv = $_advertise->get($id);
      if (empty($adv)) {
        message('抱歉，推荐栏目不存在或是已经被删除！', $this->createWebUrl('banner', array('op' => 'display')), 'error');
      }
      $_advertise->remove($_W['uniacid'], $id);
      message('推荐栏目删除成功！', $this->createWebUrl('banner', array('op' => 'display')), 'success');
    } else {
      message('请求方式不存在');
    }
    include $this->template('banner', TEMPLATE_INCLUDEPATH, true);
  }

  public function doWebAdvLink() {
    global $_W, $_GPC;

    $this->doWebAuth();

    load()->func('tpl');
    yload()->classs('quickcenter', 'FormTpl');
    yload()->classs('quickshop', 'advlink');

    $operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';
    if ($operation == 'display') {
      $conds = array('display' => 'all');
      $list = AdvLink::batchGet($_W['uniacid'], $conds);
    } elseif ($operation == 'post') {

      $id = intval($_GPC['id']);
      if (checksubmit('submit')) {
        $data = array(
          'weid' => $_W['uniacid'],
          'title' => $_GPC['title'],
          'url' => $_GPC['url'],
          'enabled' => intval($_GPC['enabled']),
          'displayorder' => intval($_GPC['displayorder']),
          'thumb' => $_GPC['thumb']
        );

        if (!empty($id)) {
          AdvLink::update($_W['uniacid'], $id, $data);
        } else {
          $id = AdvLink::create($data);
        }
        message('更新幻灯片成功！', $this->createWebUrl('advlink', array('op' => 'display')), 'success');
      }
      $advlink = AdvLink::get($id);
    } elseif ($operation == 'delete') {
      $id = intval($_GPC['id']);
      $advlink = AdvLink::get($id);
      if (empty($advlink)) {
        message('抱歉，幻灯片不存在或是已经被删除！', $this->createWebUrl('advlink', array('op' => 'display')), 'error');
      }
      AdvLink::remove($_W['uniacid'], $id);
      message('幻灯片删除成功！', $this->createWebUrl('advlink', array('op' => 'display')), 'success');
    } else {
      message('请求方式不存在');
    }
    include $this->template('advlink', TEMPLATE_INCLUDEPATH, true);
  }

  public function doMobileAjaxdelete() {
    global $_GPC;
    $delurl = $_GPC['pic'];
    ob_clean();
    if (file_delete($delurl)) {
      echo 1;
    } else {
      echo 0;
    }
  }

  private function getTemplateName() {
    if (empty($this->module['config']['template'])) {
      return 'jumei';
    }
    return $this->module['config']['template'];
  }

  private function message($msg, $redirect, $label) {
    global $_W;
    yload()->classs('quickcenter', 'template');
    $_template = new Template($this->module['name']);
    $_W['account']['template'] = $this->getTemplateName();
    include $_template->template('message');
    //message($msg, $redirect, $type);
  }

  public function doWebAuth() {
    global $_W,$_GPC;
    yload()->classs('quickauth', 'auth');
    $_auth = new Auth();
    $op = trim($_GPC['op']);
    $modulename = MODULE_NAME;
    $version = '0.60';
    $_auth->checkXAuth($op, $modulename, $version);

    // extra check
    yload()->classs('quickcenter', 'dependencychecker');
    $_checker = new DependencyChecker();
    $_checker->requireModules($_W['account']['modules'], array('quickdynamics'));
  }

  public function doWebDownloadOrder() {
    yload()->routing('quickshop', 'download');
  }

  // 检查同一个用户最多购买数量
  private function checkSoldOut($goodsid, $total, $amount, $title) {
    if ($total != -1) { // -1的情况下表示不限制库存
      if ($total < $amount) {
        $url = $this->createMobileUrl('Detail', array('id'=>$goodsid));
        if ($total <= 0) {
          message("抱歉，您购买本商品【{$title}】已经售罄，无法购买啦！", $url, "error");
        } else {
          message("抱歉，您购买的商品【{$title}】库存不足，无法购买多件，请重新下单！", $url, "error");
        }
      }
    }
  }

  // 检查同一个用户最多购买数量
  private function checkMaxOrderedGoodsCount($_order, $weid, $from_user, $goodsid, $maxbuy, $buyamount, $title) {
    if (!empty($maxbuy)) { // 为0表示不限制
      $maxBuyed = $_order->getTotalBuy($weid, $from_user, $goodsid);
      $newOrderCount = $_order->getTotalNew($weid, $from_user, $goodsid);
      $url = $this->createMobileUrl('Detail', array('id'=>$goodsid));
      $order_url = $this->createMobileUrl('MyOrder');
      if ($maxbuy <= $maxBuyed) {
        message("抱歉，您购买本商品【{$title}】的数量已经达到了最大限额，无法购买啦！", $url, "error");
      } else if ($maxbuy <= $maxBuyed + $newOrderCount) {
        message("抱歉，您购买的【{$title}】订单尚未支付，请先进入订单页面支付！", $order_url, "error");
      } else if ($maxbuy < $buyamount + $maxBuyed + $newOrderCount) {
        message("抱歉，您购买本商品【{$title}】的数量超过了最大限额，请减少数量重新下单。", $url, "error");
      }
    }
    return true;
  }



  // 检查同一个用户最多购买数量
  private function checkMaxBuy($_order, $weid, $from_user, $goodsid, $maxbuy, $buyamount, $title) {
    if (!empty($maxbuy)) { // 为0表示不限制
      $maxBuyed = $_order->getTotalBuy($weid, $from_user, $goodsid);
      $url = $this->createMobileUrl('Detail', array('id'=>$goodsid));
      if ($maxbuy <= $maxBuyed) {
        message("抱歉，您购买本商品【{$title}】的数量已经达到了最大限额，无法购买啦！", $url, "error");
      } else if ($maxbuy < $buyamount + $maxBuyed) {
        message("抱歉，您购买本商品【{$title}】的数量超过了最大限额，请减少数量重新下单。", $url, "error");
      }
    }
    return true;
  }

  // 检查商品是否已经开放购买
  private function checkGoodsTime($item, $isBatch = false) {
    if ($item['istime'] == 1) {
      $url = $this->createMobileUrl('Detail', array('id'=>$item['id']));
      if (TIMESTAMP < $item['timestart']) {
        message('抱歉，' . $item['title'] . '还未到购买时间, 暂时无法购物哦~', $url, "error");
      }
      if (TIMESTAMP > $item['timeend']) {
        message('抱歉，' . $item['title'] . '限购时间已到，不能购买了哦~', $url, "error");
      }
    }
  }


  private function call_debug_backtrace() {
    $traces = debug_backtrace();
    $ts = '';
    foreach($traces as $trace) {
      $trace['file'] = str_replace('\\', '/', $trace['file']);
      $trace['file'] = str_replace(IA_ROOT, '', $trace['file']);
      $ts .= "file: {$trace['file']}; line: {$trace['line']}; <br />";
    }
    return $ts;
  }

  private function confirmSend($id) {
    global $_GPC, $_W;
    yload()->classs('quickshop', 'order');
    $_order = new Order();
    $item = $_order->get($id);
    $result = array();
    if (empty($item)) {
      $result['message'] = '抱歉，商品不存在或是已经被删除！';
      $result['errno'] = 1;
      return $result;
    }
    if ( empty($_GPC['expresssn']) ) {
      $result['message'] = '请输入快递单号';
      $result['errno'] = 2;
      return $result;
    }
    if ( empty($_GPC['express']) or empty($_GPC['expresscom'])) {
      $result['message'] = '请选择快递公司';
      $result['errno'] = 3;
      return $result;
    }

    $item = $_order->get($id);
    if (Order::$ORDER_PAYED != $item['status']) {
      $result['message'] = '改订单不是已支付状态，无法直接发货。';
      $result['errno'] = 4;
      return $result;
    }
    $data = array(
      'status' => Order::$ORDER_DELIVERED,
      'remark' => $item['remark'] . $_GPC['remark'],
      'express' => $_GPC['express'],
      'expresscom' => $_GPC['expresscom'],
      'expresssn' => $_GPC['expresssn'],
    );

    $_order->update($_W['uniacid'], $id, $data);
    $this->notifyUser($_W['uniacid'], $id, 'notifyDelivered');

    $result['message'] = '发货操作成功！';
    $result['errno'] = 0;
    return $result;
  }

  public function doWebAjaxConfirmSend() {
    global $_GPC, $_W;
    ob_clean();
    yload()->classs('quickshop', 'order');
    if (false && is_array($_GPC['id'])) {
      // not implemented
      foreach ($_GPC['id'] as $id) {
        $result = $this->confirmSend($id);
        if ($result['errno'] != 0) {
          message($result, '', 'ajax');
        }
      }
      message($result, '', 'ajax');
    } else {
      $id = intval($_GPC['id']);
      $result = $this->confirmSend($id);
      message($result, '', 'ajax');
    }
  }

  public function doWebBatchOrder() {
    global $_W, $_GPC;

    $this->doWebAuth();

    yload()->classs('quickshop', 'order');
    yload()->classs('quickshop', 'dispatch');
    yload()->classs('quickshop', 'address');
    yload()->classs('quickshop', 'express');
    yload()->classs('quickcenter', 'FormTpl');
    $_order = new Order();
    $_dispatch = new Dispatch();
    $_address = new Address();
    $_express = new Express();

    $operation = !empty($_GPC['op']) ? $_GPC['op'] : 'display';

    if ($operation == 'display') {
      $pindex = max(1, intval($_GPC['page']));
      $psize = 20000;
      // status '1取消状态，2普通状态，3为已付款，4为已发货，5为成功', 6为管理员确认交易完成(无纠纷)
      // sendtype '1为快递，2为自提. 2暂不使用,总为快递',
      // paytype '1为余额，2为在线，3为到付',
      // goodstype  '1为实体，2为虚拟'
      $status = Order::$ORDER_PAYED;
      $sendtype = Dispatch::$EXPRESS;
      $conds = array('status'=>$status, 'sendtype'=>$sendtype);
      list($list, $total) = $_order->batchGet($_W['uniacid'], $conds, null, $pindex, $psize);
      $pager = pagination($total, $pindex, $psize);
      if (!empty($list)) {
        foreach ($list as &$row) {
          !empty($row['addressid']) && $addressids[$row['addressid']] = $row['addressid'];
          $row['dispatch'] = $_dispatch->get($row['dispatch']);
        }
        unset($row);
      }
      if (!empty($addressids)) {
        $address = $_address->batchGetByIds($_W['uniacid'], $addressids, 'id');
      }
      $status_text = $_order->getOrderStatusName($status);
    }

    include $this->template('batchorder');
  }

  private function forceOpenInWechat() {
    $force = $this->module['config']['force_open_in_wechat'];
    if(0 == $force) {
      return;
    }
    yload()->classs('quickcenter', 'wechatservice');
    $_weservice = new WechatService('quickshop');
    $fakeopenid = $_weservice->forceOpenInWechat('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
  }

  private function calcDispatchPrice($_dispatch, $weid, $weight) {
    $d = $_dispatch->getUnique($weid);
    if (empty($d)) {
      $d['firstweight'] = $d['firstprice'] = $d['secondweight'] = $d['secondprice'] = 0;
    }
    $dispatchprice = 0;
    if ($weight <= 0) {
      $dispatchprice = 0;
    } else if ($weight <= $d['firstweight']) {
      $dispatchprice = $d['firstprice'];
    } else {
      $dispatchprice = $d['firstprice'];
      if ($d['secondweight'] > 0) {
        $secondweight = $weight - $d['firstweight'];
        if ($secondweight % $d['secondweight'] == 0) {
          $dispatchprice+= (int) ( $secondweight / $d['secondweight'] ) * $d['secondprice'];
        } else {
          $dispatchprice+= (int) ( $secondweight / $d['secondweight'] + 1 ) * $d['secondprice'];
        }
      }
    }
    unset($d);
    return $dispatchprice;
  }


  protected function pay($params = array(), $mine = array()) {
    global $_W;
    if(!$this->inMobile) {
      message('支付功能只能在手机上使用');
    }
    if (empty($_W['member']['uid'])) {
      // checkauth();
    }

    $params['module'] = $this->module['name'];
    $pars = array();
    $pars[':uniacid'] = $_W['uniacid'];
    $pars[':module'] = $params['module'];
    $pars[':tid'] = $params['tid'];

    if($params['fee'] <= 0) {
      $pars['from'] = 'return';
      $pars['result'] = 'success';
      $pars['type'] = 'alipay';
      $pars['tid'] = $params['tid'];
      $site = WeUtility::createModuleSite($pars[':module']);
      $method = 'payResult';
      if (method_exists($site, $method)) {
        exit($site->$method($pars));
      }
    }

    $sql = 'SELECT * FROM ' . tablename('core_paylog') . ' WHERE `uniacid`=:uniacid AND `module`=:module AND `tid`=:tid';
    $log = pdo_fetch($sql, $pars);
		if (empty($log)) {
			$log = array(
				'uniacid' => $_W['uniacid'],
				'acid' => $_W['acid'],
				'openid' => $_W['member']['uid'],
				'module' => $this->module['name'],
				'tid' => $params['tid'],
				'fee' => $params['fee'],
				'card_fee' => $params['fee'],
				'status' => '0',
				'is_usecard' => '0',
			);
			pdo_insert('core_paylog', $log);
		}
		if($log['status'] == '1') {
			message('这个订单已经支付成功, 不需要重复支付.');
		}
    $setting = uni_setting($_W['uniacid'], array('payment', 'creditbehaviors'));
    if(!is_array($setting['payment'])) {
      message('没有有效的支付方式, 请联系网站管理员.');
    }
    $pay = $setting['payment'];
    $pay['delivery']['switch'] = ($_W['account']['payment']['delivery']['switch'] == 'OFF') ? 0 : $pay['delivery']['switch'];
    if (empty($_W['member']['uid'])) {
      $pay['credit']['switch'] = 0;
    }
    if (!empty($pay['credit']['switch'])) {
        $credtis = mc_credit_fetch($_W['member']['uid']);
    }
    include $this->template('common/paycenter');
  }

  private function getShopname($nick) {
    $conf = empty($this->module['config']['inshop_share_title']) ? 'nickname微店铺' : $this->module['config']['inshop_share_title'];
    yload()->classs('quickcenter', 'textparser');
    $_parser = new TextParser();
    return preg_replace('/nickname/', $nick, $conf);
  }

  private function setVIP($_fans, $weid, $from_user, $vip = 1) {
    $profile = $_fans->fans_search_by_openid($weid, $from_user, array('vip'));
    if ($profile['vip'] < $vip) {
      $_fans->setVIP($weid, $from_user, $vip);
    }
  }

  public function doMobileGoodsList()
  {
    global $_GPC, $_W;
    header('Access-Control-Allow-Origin: *');
    yload()->classs('quickshop', 'goods');
    $_goods = new Goods();
    $pindex = max(1, intval($_GPC['page']));
    $psize = 40;
    list($list, $total) = $_goods->batchGet($_W['uniacid'], $_GPC, $pindex, $psize);
    die($_GPC['callback'] . '(' . json_encode($list) . ')');
  }
}
