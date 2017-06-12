# 应用说明
>版本1.1
增加简单的后台用户管理.
>版本1.0
>该应用为php的ftp服务器端，能够取代vsftpd使用。
用户在conf下面的users里面，以json格式保存。
目前只实现简单的逻辑和功能，后续开发后台管理。
webserver的代码来自互联网，bug有，能用就行。
本程序依赖php的swoole扩展
>swoole安装方法：
1、pecl脚本自动安装
`pecl install swoole`

2、源码安装
`sudo apt-get install php5-dev
git clone https://github.com/swoole/swoole-src.git
cd swoole-src
phpize
./configure
make && make install`

>使用说明
>在命令行，输入 php index.php 
可以生成用户和密码,默认
用户名：scjtqs
密码：123456 
在命令行输入sudo php MyFtpServer.php
即可开启ftp服务，可以配合supervisor使用。
