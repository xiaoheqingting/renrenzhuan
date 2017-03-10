<?php

class CreditRequest {

  private static $t_request = 'quickcredit_request';
  private static $t_goods = 'quickcredit_goods';

  public function batchGetN($weid, $conds = array(), $key = null, $pindex, $psize) {
    $condition = '';
    if (isset($conds['from_user'])) {
      $condition .= " AND from_user = '" . $conds['from_user'] . "' ";
    }
    if (!empty($conds['status'])) {
      $condition .=" AND status = '" . $conds['status'] . "' ";
    }
    $requests = pdo_fetchall("SELECT * FROM " . tablename(self::$t_request)
      . " WHERE weid = $weid $condition ORDER BY id DESC "
      . " LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array(), $key);
    $total = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename(self::$t_request)
      . " WHERE weid = $weid $condition");
    return array($requests, $total);
  }

  public function batchGet($weid, $conds = array(), $key = null, $pindex, $psize) {
    $condition = '';
    if (isset($conds['from_user'])) {
      $condition .= " AND from_user = '" . $conds['from_user'] . "' ";
    }
    if (!empty($conds['status'])) {
      $condition .=" AND status = '" . $conds['status'] . "' ";
    }
    $requests = pdo_fetchall("SELECT * FROM " . tablename(self::$t_request)
      . " WHERE weid = $weid $condition ORDER BY id DESC "
      . " LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array(), $key);
    return $requests;
  }


  public function batchGetExport($weid, $conds = array(), $key = null, $pindex, $psize) {
    $conditions = '';
    if (isset($conds['status'])) {
      if ($conds['status'] == 'done') {
        $conditions = " AND status = 'done' ";
      } else if ($conds['status'] == 'new') {
        $conditions = " AND status != 'done' ";
      }
    }


    if (isset($conds['goods_id'])) {
      if (!empty($conds['goods_id'])) {
        $goodsid = intval($conds['goods_id']);
        $conditions .= " AND a.goods_id={$goodsid} ";
      }
    }

    if (isset($conds['keyword'])) {
      if (!empty($conds['keyword'])) {
        $kw = $conds['keyword'];
        $conditions .= "  AND (a.from_user like '%" .$kw ."%' OR  a.mobile like '%" .$kw ."%' OR a.realname like '%".$kw."%' OR a.residedist like '%".$kw."%') ";
      }
    }


    $requests = pdo_fetchall("SELECT a.*, b.title FROM " . tablename(self::$t_request) . " a LEFT JOIN " . tablename(self::$t_goods)
      . " b ON a.goods_id=b.goods_id AND a.weid=b.weid "
      . " WHERE a.weid = $weid $conditions ORDER BY a.createtime DESC "
      . " LIMIT " . ($pindex - 1) * $psize . ',' . $psize, array(), $key);
    return $requests;
  }

  public function getTotalExchanaged($weid, $conds = array()) {
    $condition = '';
    if (isset($conds['from_user'])) {
      $condition .= " AND from_user = '" . $conds['from_user'] . "' ";
    }
    if (!empty($conds['status'])) {
      $condition .=" AND status = '" . $conds['status'] . "' ";
    }
    $totalExchanged = pdo_fetchcolumn("SELECT sum(cost) FROM " . tablename(self::$t_request)
      . " WHERE weid = $weid $condition ");
    if (empty($totalExchanged)) {
      $totalExchanged = 0;
    }
    return $totalExchanged;
  }

  public function getStatusName($status) {
    if ($status == 'done') {
      return '已完成';
    } else if ($status == 'new' or empty($status)) {
      return '处理中';
    }
    return '未知';
  }
}
