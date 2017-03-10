<?php
$t_option = 'quickdynamics_option';
$t_queue = 'quickdynamics_queue';
ignore_user_abort(true);

//ini_set('max_execution_time', '0');
//set_time_limit(0);
$max_execution_time = intval(ini_get('max_execution_time'));
WeUtility::logging('max_execution_time', $max_execution_time);
if (empty($max_execution_time) or $max_execution_time < 20) {
  // min exepected running time: 10sec
  $max_execution_time = 10;
} else {
  $max_execution_time = $max_execution_time - 10;
}


if ( !empty($_POST) || defined('SENDING_MSG') ) {
  WeUtility::logging('die running');
	die();
}

define('SENDING_MSG', true);

yload()->classs('quickdynamics', 'messagequeue');
$_queue = new MessageQueue();


if ($_queue->isLeaseFree()) {

  $begin_time = time();

  WeUtility::logging('Task Runner start a new thread and renewLease the lease');

  $seconds = $max_execution_time;

  while ($seconds > 0) {

    // 更新活跃度
    $_queue->renewLease();


    $m = pdo_fetch('SELECT * FROM ' . tablename($t_queue) . ' ORDER BY id LIMIT 1'); // 性能依赖SQL优化
    if (!empty($m)) {

      WeUtility::logging('running task', $m);

      // TODO: 移动到历史表中
      $af = pdo_delete($t_queue, array('id'=>$m['id']));
      if ($af === 0) {
        // affected row为0表示这一行不存在, 被另外一个线程删除了
        WeUtility::logging('Another is running. exit', posix_getpid());
        exit(0);
      }
      // 执行回调函数
      yload()->classs($m['module'], $m['file']);
      $c = new $m['class']();
      if (method_exists($c, $m['method'])) {
        $param = iunserializer($m['param']);
        $c->$m['method']($param);
        WeUtility::logging('task executed', $m);
      }

      // 释放查询结果内存
      unset($m);

      $seconds = $max_execution_time;

    } else {

      // 没有更多数据了，则睡眠1秒钟
      $seconds--;

      sleep(1);
    }

    $stop = $_queue->isStopped();
    if ($stop) {
      WeUtility::logging('queue stopped, exit msg loop');
      break;
    } else {
      WeUtility::logging('tick' . $seconds, $stop);
      $end_time = time();
      $remain = $max_execution_time - ($end_time - $begin_time);
      if ($remain <= 0) {
        // $_queue->releaseLease();
        break;
      } else {
        WeUtility::logging('terminate in ' . $remain . ' seconds');
      }
    }

  }

}  else {

  WeUtility::logging('Task Runner fail get a new lease. terminate');

}
unset($_queue);
