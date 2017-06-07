<html>
<h1>应用说明<h1>
<p>
该应用为php的ftp服务器端，能够取代vsftpd使用。<br/>
用户在conf下面的users里面，以json格式保存。<br/>
目前只实现简单的逻辑和功能，后续开发后台管理。<br/>
webserver的代码来自互联网，bug有，能用就行。<br/>
本程序依赖php的swoole扩展！<br/>
<h2 align="center">使用说明</h2>
在命令行，输入 php index.php </br>
可以生成用户和密码</br>
<br/>
在命令行输入 php MyFtpServer.php <br/>
即可开启ftp服务，可以配合supervisor使用。
</p>
</html>