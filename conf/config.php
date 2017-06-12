<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月3日
 * Time: 上午10:54:30
 */
/** array(
* 'user1' => array(
        * 'pass'=>'',
        * 'group'=>'',
        * 'home'=>'/home/ftp/', //ftp主目录
        * 'active'=>true,
        * 'expired=>'2015-12-12',
* 'description'=>'',
* 'email' => '',
* 'folder'=>array(
* //可以列出主目录下的文件和目录，但不能创建和删除，也不能进入主目录下的目录
* //前1-5位是文件权限，6-9是文件夹权限,10是否继承(inherit)
* array('path'=>'/home/ftp/','access'=>'RWANDLCNDI'),
* //可以列出/home/ftp/a/下的文件和目录,可以创建和删除，可以进入/home/ftp/a/下的子目录，可以创建和删除。
* array('path'=>'/home/ftp/a/','access'=>'RWAND-----'),
* ),
* 'ip'=>array(
* 'allow'=>array(ip1,ip2,...),//支持*通配符: 192.168.0.*
* 'deny'=>array(ip1,ip2,...)
* )
* )
* )
*
* 组文件格式：
* array(
* 'group1'=>array(
* 'home'=>'/home/ftp/dept1/',
* 'folder'=>array(
*
* ),
* 'ip'=>array(
* 'allow'=>array(ip1,ip2,...),
* 'deny'=>array(ip1,ip2,...)
* )
* )
* )
*/

 //http服务器类端口
 define('HTTP_PORT', 8880);
 //FTP服务器端口
 define('FTP_PORT', 21);
 //最大连接数
 define('MAX_CONNECTION', 50);
 //TLS
 define('FTPS_PORT',990);
 //ftp被动端口
 define('PASV_PORT_MIN', '55000');
 define('PASV_PORT_MAX', '60000');
 //监听地址
 define('LOCALHOST', '0.0.0.0');
 require_once BASE_PATH.'/conf/common.php';
 require_once BASE_PATH.'/inc/User.php';
 require_once BASE_PATH.'/inc/ShareMemory.php';
 require_once BASE_PATH.'/web/CWebServer.php';
 require_once BASE_PATH.'/inc/CSmtp.php';
 require_once BASE_PATH.'/CFtpServer.php';