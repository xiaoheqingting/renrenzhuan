<?php

global $_GPC;
$spec = array(
  "id" => random(32),
  "title" => $_GPC['title']
);
include $this->template('spec');
