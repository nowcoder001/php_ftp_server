<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月3日
 * Time: 上午10:05:56
 */
defined('DEBUG_ON') or define('DEBUG_ON', false);
//主目录
defined('BASE_PATH') or define('BASE_PATH', __DIR__);
include  BASE_PATH.'/conf/config.php';
$usr=new User();
$user='scjtqs';
$pass='123456';
$home=BASE_PATH.'/test';
$expired='2018-5-5';
$usr->addUser($user, $pass, $home, $expired);
$profile=array('group'=>'admin',
    'folder'=>array(
                array('path'=>$home.'/www','access'=>'RWANDLCNDI')
            ),
    'ip'=>array(
                'allow'=>array('0.0.0.0'),
                'deny'=>array()
            )
);
$usr->setUserProfile($user, $profile);
$usr->addGroup('admin', $home);
$usr->addGroup('user',$home);
$usr->save();
