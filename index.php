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
$pass='iamqiushi!.';
$home=BASE_PATH.'/test';
$expired='2018-5-5';
$usr->addUser($user, $pass, $home, $expired);
$usr->save();
// $serv = new swoole_server("127.0.0.1", 9501);

// $array=array(
//     'default'=>array(
//         //'home'=>'/var/www/html',
//         'home'=>'/Library/WebServer/Documents/ftp_server/test/',
//         'folder'=>array(
//        // array('path'=>'/var/www/html','access'=>'RWANDLCNDI'),
//        array('path'=>'/Library/WebServer/Documents/ftp_server/test/'),
//         ),
//         'ip'=>array(
//             'allow'=>array('0.0.0.0'),
//             'deny'=>array()
//         )
//     )
// );
// echo json_encode($array);