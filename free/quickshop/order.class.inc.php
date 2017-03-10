<?php

class Order {

  /*
   * status 1取消状态，2普通状态，3为已付款，4为已发货，5为成功', 6为管理员确认交易完成(无纠纷),7已经返利
   */
  public static $ORDER_CANCEL =  1;  // 订单已取消
  public static $ORDER_NEW =  2; // 新订单
  public static $ORDER_PAYED =  3; // 已经支付，未发货
  public static $ORDER_DELIVERED =  4; // 已发货，未确认收货
  public static $ORDER_RECEIVED =  5; // 已收货，未发放佣金
  public static $ORDER_CONFIRMED =  6; // 已确认无争议，已经放佣金
  public static $ORDER_FAIL =  7; // 前端显示支付成功，后台notify通知支付失败, 情况极少见，以notify为准。 

  public static $PAY_ONLINE = 2; // 在线支付
  public static $PAY_DELIVERY = 3; // 到付
  public static $PAY_CREDIT = 4; // 余额支付

  private static $t_goods = 'quickshop_goods';
  private static $t_order = 'quickshop_order';
  private static $t_order_goods = 'quickshop_order_goods';
  private static $t_sys_fans = 'mc_mapping_fans';
  private static $t_sys_members = 'mc_members';
  private static $t_sys_member = 'mc_members';

  /****************  基础表操作  ******************/

  public function create($data) {
    $id = -1;
    $ret = pdo_insert(self::$t_order, $data);
    if (false !== $ret) {
      $id = pdo_insertid();
    }
    return $id;
  }


  public function update($weid, $id, $data) {
    global $_W;

    if (isset($data['status'])) {
      $data['updatetime'] = TIMESTAMP;
    }
    $ret = pdo_update(self::$t_order, $data, array('weid'=>$weid, 'id'=>$id));

    pdo_query('UPDATE ' . tablename(self::$t_order) . ' SET lastorderedituser = CONCAT(:lastinfo, lastorderedituser) WHERE weid=:weid AND id=:id',
      array(':lastinfo'=>  $_W['username'] . ' ' . date('m-d H:i:s', TIMESTAMP) . '<br>', ':weid'=>$weid, ':id'=>$id));

    return $ret;
  }

  /* 客户端更新状态，做更严格的检查, 必须是用户本人操作 */
  public function clientUpdate($weid, $from_user, $id, $data) {
    if (isset($data['status'])) {
      $data['updatetime'] = TIMESTAMP;
    }
    $ret = pdo_update(self::$t_order, $data, array('weid'=>$weid, 'from_user'=>$from_user, 'id'=>$id));
    return $ret;
  }


  public function get($id) {
    $order = pdo_fetch('SELECT * FROM ' . tablename(self::$t_order) . ' WHERE id=:id LIMIT 1', array(':id'=>$id));
    return $order;
  }

  /* 客户端读取订单 */
  public function clientGet($weid, $from_user, $id) {
    $order = pdo_fetch('SELECT * FROM ' . tablename(self::$t_order) . ' WHERE weid=:weid AND id=:id AND from_user=:f LIMIT 1',
      array(':weid'=>$weid, ':id'=>$id, ':f'=>$from_user));
    return $order;
  }



  public function batchGet($weid, $conds = array(), $key = null, $pindex, $psize) {
    $condition = '';
    if (isset($conds['from_user'])) {
      $condition .= " AND from_user = '" . $conds['from_user'] . "' ";
    }
    if (!empty($conds['status'])) {
      $condition .=" AND status = " . intval($conds['status']);
    }
    if (!empty($conds['goodstype'])) {
      $condition .=" AND goodstype = " . intval($conds['goodstype']);
    }
    if (!empty($conds['sendtype'])) {
      $condition .=" AND sendtype = " . intval($conds['sendtype']);
    }
    $orders = pdo_fetchall("SELECT * FROM " . tablename(self::$t_order) 
      . " WHERE weid = $weid $condition ORDER BY id DESC "
      . " LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array(), $key);
    $total = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename(self::$t_order) 
      . " WHERE weid = $weid $condition");

    return array($orders, $total);
  }

  public function batchGetById($weid, $ids = array(), $key = null, $pindex, $psize) {
    $condition = 'AND id IN (-1';
    if (count($ids) <= 0) {
      return array(null, 0);
    } else {
      foreach ($ids as $id) {
        $condition .= "," . $id;
      }
      $condition .= ')';
    }

    $orders = pdo_fetchall("SELECT * FROM " . tablename(self::$t_order) 
      . " WHERE weid = $weid $condition ORDER BY id DESC "
      . " LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array(), $key);
    $total = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename(self::$t_order) 
      . " WHERE weid = $weid $condition");

    return array($orders, $total);
  }

  public function batchGetCancelled($weid, $conds = array(), $key = null, $pindex, $psize) {
    $conds['status'] = self::$ORDER_CANCEL ;
    return $this->batchGet($weid, $conds, $key, $pindex, $psize);
  }

  public function batchGetNew($weid, $conds = array(), $key = null, $pindex, $psize) {
    $conds['status'] = self::$ORDER_NEW;
    return $this->batchGet($weid, $conds, $key, $pindex, $psize);
  }

  public function batchGetPayed($weid, $conds = array(), $key = null, $pindex, $psize) {
    $conds['status'] = self::$ORDER_PAYED;
    return $this->batchGet($weid, $conds, $key, $pindex, $psize);
  }

  public function batchGetDelivered($weid, $conds = array(), $key = null, $pindex, $psize) {
    $conds['status'] = self::$ORDER_DELIVERED;
    return $this->batchGet($weid, $conds, $key, $pindex, $psize);
  }

  public function batchGetReceived($weid, $conds = array(), $key = null, $pindex, $psize) {
    $conds['status'] = self::$ORDER_RECEIVED;
    return $this->batchGet($weid, $conds, $key, $pindex, $psize);
  }

  public function batchGetSuccess($weid, $conds = array(), $key = null, $pindex, $psize) {
    $conds['status'] = self::$ORDER_CONFIRMED;
    return $this->batchGet($weid, $conds, $key, $pindex, $psize);
  }

  public function remove($weid, $id) {
    pdo_delete(self::$t_order, array('weid'=>$weid, 'id'=>$id));
    $ret = pdo_delete(self::$t_order_goods, array('weid'=>$weid, 'orderid'=>$id));
    return $ret;
  }

  /****************  多表操作  *******************/

  /*
   * 一个订单内添加多件商品
   */
  public function addGoods($data) {
    $id = -1;
    $ret = pdo_insert(self::$t_order_goods, $data);
    if (false !== $ret) {
      $id = pdo_insertid();
    }
    return $id;
  }

  /*
   * 获取订单下的所有商品基本信息
   */
  public function getGoods($orderid, $key = null) {
    $goods = pdo_fetchall("SELECT * FROM " . tablename(self::$t_order_goods) . " WHERE orderid = $orderid", array(), $key);
    return $goods;
  }

  public function getGoodsExt($orderid, $key = null) {
    $goods = pdo_fetchall("SELECT o.*, g.use_abs_commission FROM " . tablename(self::$t_order_goods) . " o "
      . " LEFT JOIN " . tablename(self::$t_goods) . " g "
      . " ON o.goodsid=g.id "
      . " WHERE o.orderid={$orderid}",
      array(), $key);
    return $goods;
  }


  public function getDetailedGoods($orderid, $key = null) {
    $goods = pdo_fetchall("SELECT g.use_abs_commission, g.sendtype, g.support_delivery, g.goodstype, g.maxbuy, g.id, g.totalcnf, g.status, g.sales, g.title, g.thumb, g.unit, o.ordergoodsprice marketprice,g.productprice, g.costprice, g.total goodstotal, o.total,o.optionid,o.total ordertotal, o.optionname, o.ordergoodsprice FROM " . tablename(self::$t_order_goods) . " o "
      . " LEFT JOIN " . tablename(self::$t_goods) . " g "
      . " ON o.goodsid=g.id "
      . " WHERE o.orderid='{$orderid}'");
    return $goods;
  }

  public function batchGetByOpenIds($weid, $openids, $conds = array(), $key = null, $pindex = 1, $psize = 9999999) {
    $condition = '';
    if (empty($openids)) {
      return array(array(), 0);
    }

    $condition .= " AND a.from_user IN ('" . join("','", $openids) . "')"; 
    if (isset($conds['status']) and $conds['allstatus'] = 0) {
      $condition .=" AND a.status = " . intval($conds['status']);
    }
    $orders = pdo_fetchall("SELECT * FROM " . tablename(self::$t_order) . " a "
      . " LEFT JOIN " . tablename(self::$t_sys_fans) . " b ON a.weid=b.uniacid AND a.from_user=b.openid "
      . " LEFT JOIN " . tablename(self::$t_sys_members) . " c ON b.uid = c.uid "
      . " WHERE a.weid = $weid $condition ORDER BY a.id DESC "
      . " LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array(), $key);
    $total = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename(self::$t_order) . " a " 
      . " WHERE weid = $weid $condition");

    return array($orders, $total);
  }

  public function batchGetOrderGoodsByOpenIds($weid, $openids, $conds = array(), $key = null, $pindex = 1, $psize = 9999999) {
    $condition = '';
    if ((!is_array($openids)) or empty($openids) or count($openids) <= 0) {
      return array(array(), 0);
    }

    $condition .= " AND a.from_user IN ('" . join("','", $openids) . "')";
    /*
    if (isset($conds['status']) and empty($conds['allstatus'])) {
      $condition .=" AND a.status = " . intval($conds['status']);
    }
     */
    $sql = "SELECT c.*, b.*, a.*,g.credittype,g.use_abs_commission FROM " . tablename(self::$t_order) . " a "
      . " LEFT JOIN " . tablename(self::$t_order_goods) . " c ON a.weid=c.weid AND a.id =c.orderid "
      . " LEFT JOIN " . tablename(self::$t_sys_fans) . " b ON a.weid=b.uniacid AND a.from_user=b.openid"
      . " LEFT JOIN " . tablename(self::$t_goods) . " g ON g.id=c.goodsid "
      . " WHERE a.weid = $weid $condition ORDER BY a.id DESC "
      . " LIMIT " . ($pindex - 1) * $psize . ',' . $psize;

    $orders = pdo_fetchall($sql, array(), $key);

    return $orders;
  }

  public function getDetailedOrderByOpenId($weid, $openid) {
    $condition = '';
    if (empty($openid) or empty($weid)) {
      return array();
    }

    $condition .= " a.weid =  " . $weid . " AND a.from_user = '". $openid . "' ";
    $sql = "SELECT c.*, b.*, a.*, g.title, g.min_buy_level, g.goodstype, g.sendtype FROM "
      .                 tablename(self::$t_order) . " a "
      . " LEFT JOIN " . tablename(self::$t_order_goods) . " c ON a.weid=c.weid AND a.id =c.orderid "
      . " LEFT JOIN " . tablename(self::$t_goods) . " g ON g.id=c.goodsid "
      . " LEFT JOIN " . tablename(self::$t_sys_fans) . " b ON a.weid=b.uniacid AND a.from_user=b.openid"
      . " WHERE $condition ORDER BY a.id DESC ";


    $orders = pdo_fetchall($sql);

    return $orders;
  }


  public static function getPayTypeLabel($paytype) {
    switch($paytype) {
    case self::$PAY_ONLINE:
      return 'danger';
    case self::$PAY_DELIVERY:
      return 'warning';
    case self::$PAY_CREDIT:
      return 'info';
    default:
      return 'default';
    }
  }

  public static function getPayTypeName($paytype) {
    switch($paytype) {
    case self::$PAY_ONLINE:
      return '在线支付';
    case self::$PAY_DELIVERY:
      return '货到付款';
    case self::$PAY_CREDIT:
      return '余额支付';
    default:
      return '未知';
    }
  }

  public static function getOrderStatusName($status, $goodstype = 1) {
    if ($goodstype == 2)
    {
      switch ($status) {
      case self::$ORDER_CANCEL:
        return '订单取消';
      case self::$ORDER_NEW:
        return '待付款';
      case self::$ORDER_PAYED:
        return '下单成功';
      case self::$ORDER_DELIVERED:
        return '已确认';
      case self::$ORDER_RECEIVED:
        return '已完成';
      case self::$ORDER_CONFIRMED:
        return '已完成';
      case self::$ORDER_FAIL:
        return '支付失败';
      default:
        return '全部';
      }
    }
    else
    {
      switch ($status) {
      case self::$ORDER_CANCEL:
        return '订单取消';
      case self::$ORDER_NEW:
        return '待付款';
      case self::$ORDER_PAYED:
        return '待发货';
      case self::$ORDER_DELIVERED:
        return '待收货';
      case self::$ORDER_RECEIVED:
        return '已收货';
      case self::$ORDER_CONFIRMED:
        return '已完成';
      case self::$ORDER_FAIL:
        return '支付失败';
      default:
        return '全部';
      }
    }
  }

  public function getTotalOrderedGoodsCount($weid, $from_user, $goodsid)  {
    $total = pdo_fetchcolumn("SELECT SUM(b.total) FROM " . tablename('quickshop_order') . " a, " . tablename('quickshop_order_goods') . " b "
      . " WHERE a.weid={$weid} and b.weid={$weid} AND a.from_user='{$from_user}' AND a.status >= :status AND a.id=b.orderid AND b.goodsid=:goodsid",
      array(':status'=>self::$ORDER_NEW, ':goodsid'=>$goodsid));
    return $total;
  }

  public function getTotalBuy($weid, $from_user, $goodsid)  {
    $total = pdo_fetchcolumn("SELECT SUM(b.total) FROM " . tablename('quickshop_order') . " a, " . tablename('quickshop_order_goods') . " b "
      . " WHERE a.weid={$weid} and b.weid={$weid} AND a.from_user='{$from_user}' AND a.status >= :status AND a.id=b.orderid AND b.goodsid=:goodsid",
      array(':status'=>self::$ORDER_PAYED, ':goodsid'=>$goodsid));
    return $total;
  }

  public function getTotalNew($weid, $from_user, $goodsid)  {
    $total = pdo_fetchcolumn("SELECT SUM(b.total) FROM " . tablename('quickshop_order') . " a, " . tablename('quickshop_order_goods') . " b "
      . " WHERE a.weid={$weid} and b.weid={$weid} AND a.from_user='{$from_user}' AND a.status = :status AND a.id=b.orderid AND b.goodsid=:goodsid",
      array(':status'=>self::$ORDER_NEW, ':goodsid'=>$goodsid));
    return $total;
  }


  /**
   * @brief 获取@seconds以内的若干商品的销售额
   * @status 一组商品状态，null表示所有商品
   * @seconds 表示多少秒之内的商品加入到统计中
   */
  public function getAchievementByTime($weid, $status, $seconds) {
    $now = TIMESTAMP;
    $status_cond = (empty($status)) ? "" : " AND status IN (" . join(',', $status) . ")";
    $total = pdo_fetchcolumn("SELECT SUM(a.price) FROM "
      . tablename('quickshop_order') . " a "
      . " WHERE a.weid={$weid} AND createtime > {$now} - {$seconds} {$status_cond}");
    return round($total, 2);
  }

  // 一键消失
  public function disappear($weid, $from_user) {
    list($orders, $total) = $this->batchGet($weid, array('from_user'=>$from_user), null, 1, 1000000);
    // 删除订单内商品
    foreach ($orders as $order) {
      pdo_delete(self::$t_order_goods, array('weid'=>$weid, 'orderid'=>$order['id']));
    }
    // 删除订单
    pdo_delete(self::$t_order, array('weid'=>$weid, 'from_user'=>$from_user));
  }

  /* 客户端更新状态，做更严格的检查, 必须是用户本人操作 */
  public function clientRemove($weid, $from_user, $id) {
    pdo_delete(self::$t_order, array('weid'=>$weid, 'from_user'=>$from_user, 'id'=>$id));
    $ret = pdo_delete(self::$t_order_goods, array('weid'=>$weid, 'orderid'=>$id));
    return $ret;
  }

} // end class
