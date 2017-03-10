<?php
/**
 * QQ群：304081212
 * 作者：晓楚, 547753994
 *
 * 网站：www.xuehuar.com
 */

defined('IN_IA') or exit('Access Denied');

class CenterManager {
	protected static $t_bind = "quickcenter_module_bindings";
  /*
   * @groupid 分组名称
   * @identifier 菜单标示符
   * @pidentifier 上级菜单标示符，顶级菜单为空
   */
  public function AddItem($item)
  {
    global $_W;
    $groupid = 'default';
    $identifier = 'default';
    $pidentifier = '';
    $title = '默认内容';
    $url = 'http://www.baidu.com';
    $thumb = '';
    $module = '';
    $do = '';
    $callback = '';
    extract($item);
    pdo_insert(self::$t_bind,
      array(
        'weid'=>$_W['uniacid'],
        'groupid'=>$groupid,
        'displayorder'=>$displayorder,
        'identifier'=>$identifier,
        'pidentifier'=>$pidentifier,
        'title'=>$title,
        'url'=>$url,
        'thumb'=>$thumb,
        'module'=>$module,
        'do'=>$do,
        'callback'=>$callback,
        'enable'=>$enable,
        'rich_callback_enable'=>$rich_callback_enable,
      )
    );
  } 

  public function GetItem($id)
  {
    $item = pdo_fetch("SELECT * FROM " . tablename(self::$t_bind) . " WHERE id=:id", array(':id'=>$id));
    return $item;
  }

  public function UpdateItem($id, $item)
  {
    global $_W;
    $org_item = $this->GetItem($id);
    if (!empty($org_item)) {
      $item = array_merge($org_item, $item);
      extract($item);
      pdo_update(self::$t_bind,
        array(
          'groupid'=>$groupid,
          'displayorder'=>$displayorder,
          'identifier'=>$identifier,
          'pidentifier'=>$pidentifier,
          'title'=>$title,
          'url'=>$url,
          'thumb'=>$thumb,
          'module'=>$module,
          'do'=>$do,
          'callback'=>$callback,
          'enable'=>$enable,
          'rich_callback_enable'=>$rich_callback_enable,
        ),
        array(
          'id'=>$id
        )
      );
    }
  } 

  public function EnableItem($id)
  {
    pdo_update(self::$t_bind, array('enable'=>1), array('id'=>$id));
  } 

  public function DisableItem($id)
  {
    pdo_update(self::$t_bind, array('enable'=>0), array('id'=>$id));
  } 

  public function DeleteItem($id)
  {
    pdo_delete(self::$t_bind, array('id'=>$id));
  } 

}

