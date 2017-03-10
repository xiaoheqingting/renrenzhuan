<?php

class ShopPermission {

  /* 检查是否有商品修改权限 */
  static public function hasGoodsEditPermission($perm_user) {
    global $_W;
    if ($_W['isfounder']) {
      return true;
    }
    if (empty($perm_user)) {
      return true;
    }
    if (false !== strpos($perm_user, $_W['username'])) {
      return true;
    }
    return false;
  }

}
