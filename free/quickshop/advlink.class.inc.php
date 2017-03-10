<?php

class AdvLink {

  private static $t_link = 'quickshop_link';

  public static function create($data) {
    $id = -1;
    $ret = pdo_insert(self::$t_link, $data);
    if (false !== $ret) {
      $id = pdo_insertid();
    }
    return $id;
  }


  public static function update($weid, $id, $data) {
    $ret = pdo_update(self::$t_link, $data, array('weid'=>$weid, 'id'=>$id));
    return $ret;
  }


  public static function get($id) {
    $link = pdo_fetch('SELECT * FROM ' . tablename(self::$t_link) . ' WHERE id=:id LIMIT 1', array(':id'=>$id));
    return $link;
  }


  public static function batchGet($weid, $conds = array(), $key = null) {
    if (isset($conds['display']) && $conds['display'] == 'all') {
      // nop
    } else {
      $condition = ' AND enabled=1';
    }
    $links = pdo_fetchall("SELECT * FROM " . tablename(self::$t_link) . " WHERE weid = $weid  {$condition} ORDER BY displayorder ASC", array(), $key);
    return $links;
  }

  public static function remove($weid, $id) {
    return pdo_query("DELETE FROM " . tablename(self::$t_link) . " WHERE id=:id AND weid=:weid", array(':weid'=>$weid, ':id' => $id));
  }

}
