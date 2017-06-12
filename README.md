# 应用说明
>版本1.1  </br>  

增加简单的后台用户管理.</br>  
>版本1.0</br>  
>该应用为php的ftp服务器端，能够取代vsftpd使用。</br>  
用户在conf下面的users里面，以json格式保存。</br>  
目前只实现简单的逻辑和功能，后续开发后台管理。</br>  
webserver的代码来自互联网，bug有，能用就行。</br>  
本程序依赖php的swoole扩展</br>  
>swoole安装方法：</br>  
1、pecl脚本自动安装</br>  
`pecl install swoole`</br>  
</br>  
2、源码安装</br>  
`sudo apt-get install php5-dev  
git clone https://github.com/swoole/swoole-src.git  
cd swoole-src  
phpize  
./configure  
make && make install`</br>  
</br>  
>使用说明</br>  
>在命令行，输入 php index.php </br>  
可以生成用户和密码,默认</br>  
用户名：scjtqs</br>  
密码：123456 </br>  
在命令行输入sudo php MyFtpServer.php</br>  
即可开启ftp服务，可以配合supervisor使用。</br>
