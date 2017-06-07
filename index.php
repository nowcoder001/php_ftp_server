<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月3日
 * Time: 上午10:05:56
 */
include_once 'CFtpServer.php';
$usr=new User();
$user='scjtqs';
$pass='123456';
$home=BASE_PATH.'/test';
$expired='2018-5-5';
$usr->addUser($user, $pass, $home, $expired);
$usr->save();
