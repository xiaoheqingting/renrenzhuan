<?php
defined('IN_IA') or exit('Access Denied');
/*
@author newjueqi( http://blog.csdn.net/newjueqi )
this libray can conver Emoji(encoding by utf8) to a special str(default is "#") 
all emoji utf8 string from two parts:
1. take from https://github.com/iamcal/php-emoji
2. i write code to get from http://punchdrunker.github.io/iOSEmoji/table_html/ios6/index.html
how to use,please see function test()
*/
class EmojiUtil {
  public static function removeEmoji($text) {
    $tmpStr = json_encode($text); //暴露出unicode
    //$tmpStr = preg_replace("#(\\\ue[0-9a-f]{3})#ie","addslashes('\\1')",$tmpStr); //将emoji的unicode留下，其他不动
    $tmpStr = @preg_replace("#(\\\ue[0-9a-f]{3})#ie", '', $tmpStr); //将emoji全部删掉
    $text = json_decode($tmpStr);
    return $text;
  }
}

