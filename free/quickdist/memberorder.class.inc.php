<?php
class MemberOrder {

  /**
   * 获得自己$level级下线的订单数据
   */
  private function getOrderByLevel($weid, $openid, $level) {
    yload()->classs('quickdist', 'member');
    yload()->classs('quickshop', 'order');
    $_member = new Member();
    $_order = new Order();

    $member = $_member->getMemberInfoByLevel($weid, $openid, $level);

    return $_order->batchGetByOpenIds($weid, array_keys($member), array('allstatus' => 1));
  }

  public function getOrderCountByLevel($weid, $openid, $level) {
    list($order, $count) = $this->getOrderByLevel($weid, $openid, $level);
    return $count;
  }

  public function getOrderInfoByLevel($weid, $openid, $level) {
    list($order, $count) = $this->getOrderByLevel($weid, $openid, $level);
    return $order;
  }


  private function getCommissionInfoByLevel($weid, $openid, $level) {
    yload()->classs('quickdist', 'member');
    yload()->classs('quickshop', 'order');
    yload()->classs('quickdist', 'commission');
    $_member = new Member();
    $_order = new Order();
    $_commission = new Commission();

    // 1. 获取下线OpenID,昵称列表
    // @return key为openid
    $member = $_member->getMemberInfoByLevel($weid, $openid, $level);

    // 2. 获取OpenID对应的订单列表
    // 3. 获取订单对应的商品列表
    // @return key为goodsid
    if (!empty($member)) {
      $order_goods = $_order->batchGetOrderGoodsByOpenIds($weid, array_keys($member), array('allstatus' => 1));
    }

    if (!empty($order_goods)) {
      // 4. 根据商品列表获得他们的返利比例表
      // @return key为goodsid
      foreach ($order_goods as $item) {
        $goods_list[$item['goodsid']] = 1;
      }
      $commission_rate = $_commission->batchGetCommissionByGoodsIds($weid, array_keys($goods_list), 'id');
    }

    // 5. 计算全部返利信息
    $result = array();
    if (!empty($order_goods)) {
      foreach ($order_goods as $item) {
        if ($item['use_abs_commission']) {
          $com_val = $commission_rate[$item['goodsid']]['rate'.$level] * $item['total'];
        } else {
          $com_val = $commission_rate[$item['goodsid']]['rate'.$level] * $item['ordergoodsprice'] * $item['total'];
        }
        $line = array('nickname' => $item['nickname'],
          'from_user' => $item['from_user'],
          'price' => $item['ordergoodsprice'],
          'total' => $item['total'],
          'level' => $level,
          'credittype' => $item['credittype'],
          'status' => $item['status'],
          'order' => $item['orderid'],
          'goods' => $item['goodsid'],
          'commission_rate' => $commission_rate[$item['goodsid']]['rate'.$level],
          'commission' => $com_val);
        $result[] = $line;
      }
    }
    /* result中包含: 用户昵称, from_user, 商品价格, 返利 */
    return $result;
  }

  public function getCommissionInfo($weid, $openid) {
    /* goods1中包含: 用户昵称, from_user, 商品价格, 返利 */
    $cinfo1 = $this->getCommissionInfoByLevel($weid, $openid, 1);
    $cinfo2 = $this->getCommissionInfoByLevel($weid, $openid, 2);
    $cinfo3 = $this->getCommissionInfoByLevel($weid, $openid, 3);
    // TODO: 计算返利
    // 结果直接输出多行html
    $commision = array_merge($cinfo1, $cinfo2, $cinfo3);
    return $commision;
  }


  /**
   * 预估佣金
   * @param weid 当前微信账号
   * @param orderId 发放佣金的账单编号
   */
  public function calcCommission($weid, $orderId) {
    yload()->classs('quickshop', 'order');
    yload()->classs('quickdist', 'commission');
    yload()->classs('quickdist', 'member');
    yload()->classs('quickshop', 'goods');
    $_goods = new Goods();
    $_order = new Order();
    $_member = new Member();
    $_commission = new Commission();
    $order = $_order->get($orderId);
    $order_goods = $_order->getGoodsExt($orderId, 'goodsid');
    if (!empty($order_goods)) {
      // 根据商品列表获得每一件商品的返利比例表
      $commission_rate = $_commission->batchGetCommissionByGoodsIds($weid, array_keys($order_goods), 'id');
      $goods = $_goods->batchGetByIds($weid, array_keys($order_goods), 'id');
    }
    $cur_level = 1;
    $members = $_member->getAllLevelMemberInfo($weid, $order['from_user'], Member::MAX_LEVEL);

    $commission = array();
    while ($cur_level <= Member::MAX_LEVEL) {
      if (empty($members[$cur_level]) or empty($members[$cur_level]['leader'])) {
        break; // 没有更多上级
      }
      $commission[$cur_level] = array();
      $commission[$cur_level]['from_user'] = $members[$cur_level]['leader'];
      $commission[$cur_level]['com_val'] = 0;
      foreach ($order_goods as $item) {
        // 对于每一件商品，给上线分佣
        if ($item['use_abs_commission']) {
          $commission[$cur_level]['com_val'] += $commission_rate[$item['goodsid']]['rate'.$cur_level] * $item['total'];
        } else {
          $commission[$cur_level]['com_val'] += $commission_rate[$item['goodsid']]['rate'.$cur_level] * $item['ordergoodsprice'] * $item['total'];
        }
      }
      $cur_level++;
    }
    return $commission;
  }

  /**
   * 发放佣金
   * @param weid 当前微信账号
   * @param orderId 发放佣金的账单编号
   */
  public function giveCommission($weid, $orderId) {
    yload()->classs('quickshop', 'order');
    yload()->classs('quickdist', 'commission');
    yload()->classs('quickdist', 'member');
    yload()->classs('quickshop', 'goods');
    $_goods = new Goods();
    $_order = new Order();
    $_member = new Member();
    $_commission = new Commission();
    $order = $_order->get($orderId);
    if ($order['status'] != Order::$ORDER_RECEIVED) {
      message('当前订单状态不能结算佣金', referer(), 'error');
    }
    $order_goods = $_order->getGoodsExt($orderId, 'goodsid');
    if (!empty($order_goods)) {
      // 根据商品列表获得每一件商品的返利比例表
      $commission_rate = $_commission->batchGetCommissionByGoodsIds($weid, array_keys($order_goods), 'id');
      $goods = $_goods->batchGetByIds($weid, array_keys($order_goods), 'id');
    }
    $cur_level = 1;
    $members = $_member->getAllLevelMemberInfo($weid, $order['from_user'], Member::MAX_LEVEL);

    while ($cur_level <= Member::MAX_LEVEL) {
      if (empty($members[$cur_level]) or empty($members[$cur_level]['leader'])) {
        break; // 没有更多上级
      }

      foreach ($order_goods as $item) {
        // 对于每一件商品，给上线分佣
        if ($item['use_abs_commission']) {
          $com_val = $commission_rate[$item['goodsid']]['rate'.$cur_level] * $item['total'];
        } else {
          $com_val = $commission_rate[$item['goodsid']]['rate'.$cur_level] * $item['ordergoodsprice'] * $item['total'];
        }
        $_commission->giveCommission(
          $weid,
          $orderId,
          $item['goodsid'],
          $members[$cur_level]['leader'],
          $order['from_user'],
          $order['createtime'],
          $item['ordergoodsprice'],
          $commission_rate[$item['goodsid']]['rate'.$cur_level],
          $item['total'],
          $cur_level,
          $com_val,
          $goods[$item['goodsid']]['credittype']);
      }
      $cur_level++;
    }
    if (!empty($order)) {
      $data = array(
        'status' => Order::$ORDER_CONFIRMED
      );
      $_order->update($weid, $orderId, $data);
    }
  }

  public function notifyCommission($order) {
    yload()->classs('quickshop', 'order');
    yload()->classs('quickdist', 'commission');
    yload()->classs('quickdist', 'distnotifier');
    yload()->classs('quickdist', 'member');
    yload()->classs('quickshop', 'goods');
    $_goods = new Goods();
    $_order = new Order();
    $_member = new Member();
    $_commission = new Commission();
    $_distnotifier = new DistNotifier();
    $weid = $order['weid'];
    $order_goods = $_order->getGoodsExt($order['id'], 'goodsid');
    if (!empty($order_goods)) {
      // 根据商品列表获得每一件商品的返利比例表
      $commission_rate = $_commission->batchGetCommissionByGoodsIds($weid, array_keys($order_goods), 'id');
      $goods = $_goods->batchGetByIds($weid, array_keys($order_goods), 'id');
    }
    $cur_level = 1;
    $members = $_member->getAllLevelMemberInfo($weid, $order['from_user'], Member::MAX_LEVEL);

    $msg = $_distnotifier->get($weid);
    while ($cur_level <= Member::MAX_LEVEL) {
      if (empty($members[$cur_level]) or empty($members[$cur_level]['leader'])) {
        break; // 没有更多上级
      }

      $template = $msg[$cur_level];
      if (empty($template)) {
        $cur_level++;
        continue;
      }

      $com_val_acc = 0;
      $totalprice = 0;
      foreach ($order_goods as $item) {
        // 对于每一件商品，给上线分佣
        if ($item['use_abs_commission']) {
          $com_val = $commission_rate[$item['goodsid']]['rate'.$cur_level] * $item['total'];
        } else {
          $com_val = $commission_rate[$item['goodsid']]['rate'.$cur_level] * $item['ordergoodsprice'] * $item['total'];
        }
        $com_val_acc += $com_val;
        $totalprice += $item['ordergoodsprice'] * $item['total'];
      }
      $_distnotifier->notify(
        $weid,
        $members[$cur_level]['leader'],
        $cur_level,
        $order['from_user'],
        $com_val_acc,
        $totalprice,
        $template);

      $cur_level++;
    }
  }

  // 通知经销商发货
  public function notifyDealer($order) {
    global $_W;
    yload()->classs('quickshop', 'order');
    yload()->classs('quickdist', 'dealernotifier');
    yload()->classs('quickshop', 'goods');
    $_goods = new Goods();
    $_order = new Order();
    $_dealernotifier = new DealerNotifier();
    $weid = $order['weid'];
    $order_goods = $_order->getGoods($order['id'], 'goodsid');
    if (!empty($order_goods)) {
      $goods = $_goods->batchGetByIds($weid, array_keys($order_goods), 'id');
      foreach ($goods as $item) {
        $order_url = $_W['siteroot'] . 'web/' . wurl('site/entry/order', array('m'=>'quickshop', 'op'=>'detail', 'id'=>$order['id'], 'uniacid'=>$_W['uniacid']));
        $goods_url =  $_W['siteroot'] . 'app/' . wurl('entry/module/detail', array('m'=>'quickshop', 'id'=>$item['id'], 'i'=>$_W['uniacid']));

        $template = "[buyer]购买了新商品，请注意发货. 订单编号:<a href='{$order_url}'>" . $order['ordersn'] ."</a>\r\n商品名:<a href='{$goods_url}'>" . $item['title'] . "</a>";
        $_dealernotifier->notify(
          $weid,
          $item['dealeropenid'],
          $order['from_user'],
          $template);
      }
    }
  }
}
