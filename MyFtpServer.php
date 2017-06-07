<?php
/**FTP启动程序
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月3日
 * Time: 上午10:55:40
 */
//请使用sudo php MyFtpServer.php 启动，swoole仅支持php_cli
include_once 'CFtpServer.php';
$host='0.0.0.0';
$port='21';
$ftp=new CFtpServer($host, $port);
$crt=BASE_PATH.'/ssl/ftp.scjtqs.top.crt';
$key=BASE_PATH.'/ssl/ftp.scjtqs.top.key';
$ftp->set_ssl($crt, $key);
$ftp->run();