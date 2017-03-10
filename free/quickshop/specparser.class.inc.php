<?php
/* 解析规格字符串 */
/* 规格：
 * [
 *    {
 *      'title':'红色',
 *      'ref': 1
 *    },
 *    {
 *      'title':'蓝色',
 *      'ref': 'http://www.baidu.com'
 *    },

 *    {
 *      'title':'紫罗兰色',
 *      'ref': 3
 *    }
 * ]
 *
 */
class SpecParser {
  public static function parse($weid, $text) {
    $lines = explode("\r", $text);
    $specs = array();
    foreach ($lines as $line) {
      $tline = trim($line);
      if (!empty($tline)) {
        $parts = explode('|', $tline, 3);
        if (is_array($parts)) {
          $item = array(
            'title'=>trim($parts[0]),
            'ref'=> trim($parts[1]),
            'selected'=>trim($parts[2])
          );
          $specs[] = $item;
        } /* is_array */
      }
    } /* foreach */

    $result = self::parse_data($weid, $specs);
    return $result;
  }

  public static function parse_data($weid, $specs) {
    // 如果是id，则转成商品url
    // 如果不是id，则看做自定义url
    foreach ($specs as &$s) {
      if (is_numeric($s['ref'])) {
        $s['id'] = $s['ref'];
        $s['ref'] = murl('entry/module/detail', array('m'=>'quickshop', 'weid'=>$weid, 'id'=>$s['ref']));
      } else {
        // $s['ref'] = $s['ref'];
      }
    }
    return $specs;
  }
}
