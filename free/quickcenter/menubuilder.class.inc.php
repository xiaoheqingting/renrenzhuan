<?php

class MenuBuilder
{
  static public function build($items) {
    $menus = array();
    /*
     * $menu format:
     *   menu-name, parent-menu-name, items
     * the first element in menus is the top menu, which has no parents
     */
    /* build top menu */
    $menu = array();
    $menu['identifier'] = '__topmenu__';
    $menu['pidentifier'] = '';
    $menu['items'] = array();
    foreach($items as &$item) {
      if (empty($item['pidentifier'])) {
        $item['pidentifier'] = $menu['identifier'];
        if (!isset($menu['items'][$item['groupid']])) {
          $menu['items'][$item['groupid']] = array();
        }
        $menu['items'][$item['groupid']][] = $item;
      }
    }
    $menus[] = $menu;
    foreach($items as &$item) {
      $item['children'] = array();
    }

    /* 1. build children list */
    foreach($items as &$item) {
      // 搜索item的所有孩子节点
      foreach($items as $child) {
        if ($item['identifier'] == $child['pidentifier']) {
          if (!isset($item['children'][$child['groupid']])) {
            $item['children'][$child['groupid']] = array();
          }
          $item['children'][$child['groupid']][] = $child;
        }
      }
    }
    /* 2.build panels */
    foreach($items as $item) {
      if (count($item['children']) > 0) {
        $menu = array();
        $menu['identifier'] = $item['identifier'];
        $menu['pidentifier'] = $item['pidentifier'];
        $menu['items'] = $item['children'];
        $menus[] = $menu;
      }
    }
/*
    foreach($menus as $menu) {
        print_r($menu['items']);
        echo "<br/>";
        echo "<br/>";
    }
 */

    //echo json_encode($menus);
    //die(0);
    return $menus;
  }
}

