<?php
defined('IN_IA') or exit('Access Denied');

require IA_ROOT . '/addons/quickdynamics/define.php';
require_once MODULE_ROOT . '/quickcenter/loader.php';

class QuickDynamicsModuleSite extends WeModuleSite {
  private static $t_queue = 'quickdynamics_queue';

  public function doWebQueryRunningState() {
    global $_W;
    yload()->classs('quickdynamics', 'runningstate');
    yload()->classs('quickdynamics', 'messagequeue');
    $_state = new RunningState();
    $_queue = new MessageQueue();
    $state = $_state->leaseHold();
    $recent = $_queue->getRecentMsg($_W['uniacid']);
    $result = array('state'=>$state, 'recent'=>$recent);
    message($result, '', 'ajax');
  }

  public function doWebStat() {
    global $_W, $_GPC;
    $this->doWebAuth();
    // 获取一些最近的状态

    include $this->template('stat');
  }

  public function doWebAuth() {
    global $_W,$_GPC;
    yload()->classs('quickauth', 'auth');
    $_auth = new Auth();
    $op = trim($_GPC['op']);
    $modulename = MODULE_NAME;
    $version = '0.60';
    // $_auth->checkXAuth($op, $modulename, $version);
  }

  public function doWebManual() {
    global $_GPC, $_W;
    $this->doWebAuth();
    yload()->classs('quickdynamics', 'messagequeue');
    $_queue = new MessageQueue();
    $op = empty($_GPC['op']) ? 'display' : $_GPC['op'];
    if ('start' == $op) {
      $_queue->activate();
    } else if ('stop' == $op) {
      $_queue->stop();
    }
    $leaseHold = $_queue->leaseHold();
    include $this->template('manual');
  }


  public function doWebDynamics() {
    global $_GPC, $_W;
    $this->doWebAuth();
    yload()->classs('quickdynamics', 'messagequeue');
    $_queue = new MessageQueue();
    $op = empty($_GPC['op']) ? 'display' : $_GPC['op'];
    if ('start' == $op) {
      $_queue->activate();
    } else if ('stop' == $op) {
      $_queue->stop();
    }
    // always try activate a new cron job
    // $_queue->activate();
    $queueSize = $_queue->getSize();
    $leaseHold = $_queue->leaseHold();
    include $this->template('dynamics');
  }


  // 用于HTTP异步形式加载Runner
  public function doMobileActivate() {
    ignore_user_abort(true);
    yload()->routing('quickdynamics', 'taskrunner');
  }

  // @test
  public function doMobileAdd() {
    global $_GPC;
    yload()->classs('quickdynamics', 'messagequeue');
    $_queue = new MessageQueue();
    $param = array('text'=>$_GPC['text'], 'from_user'=>$_GPC['from_user']);
    $_queue->push('quickdynamics', 'sendmsg', 'SendMsg', 'notifyBuyer', $param);
  }
}
