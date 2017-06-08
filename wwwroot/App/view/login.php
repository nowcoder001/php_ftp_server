<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
		<title>博客登录</title>
		 <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

	<link href="css/font-awesome.min.css" rel="stylesheet">
    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <link href="css/ie10-viewport-bug-workaround.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="navbar-fixed-top.css" rel="stylesheet">

    <!-- Just for debugging purposes. Don't actually copy these 2 lines! -->
    <!--[if lt IE 9]><script src="../../assets/js/ie8-responsive-file-warning.js"></script><![endif]-->
    <script src="js/ie-emulation-modes-warning.js"></script>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://cdn.bootcss.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://cdn.bootcss.com/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
		<!--<link rel="stylesheet" href="css/reset.css" />-->
		<link rel="stylesheet" href="css/login.css" />
	</head>
	<body>
		<div class="head navbar navbar-default navbar-fixed-top">
			<div class="container">
			<div class="left navbar-collapse collapse">
<!-- 			<img src="img/184-40.png"/> -->
			<ul id="navbar" class="nav navbar-nav">
				<li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">功能 <span class="caret"></span></a>
              <ul class="dropdown-menu">
                <li><a href="#">功能1</a></li>
                <li><a href="#">功能2</a></li>
                <li><a href="#">功能3</a></li>
              </ul>
            </li>
				<li><a href="#">企业版</a></li>
				<li><a href="#">冒泡</a></li>
				<li><a href="?b=index">博客</a></li>
				<li><a href="#">帮助</a></li>
			</ul>
			</div>
			<h1>用户登录</h1>
			<div class="right">
<!-- 				<img src="img/码市.png"/> -->
				<a href="?b=register"><span class="span1">注册</span></a>
				<a href="?b=login"><span class="span2">登录</span></a>
			</div>
			</div>
		</div>
		<!--登录框-->
		<div class="login container">
			<h2>用户登录</h2>
			<div class="main_input">
				<input class="input" type="text" name="username" placeholder="用户名/手机/邮箱"/>
			</div>
			<div class="main_input">
				<input class="input" type="password" name="password" placeholder="密码"/>
			</div>
			<div class="check">
				<span class="left"><input type="checkbox" />记住我</span>
				<span class="right">找回密码</span>
			</div>
			<button class="submit btn">登录</button>
			<div class="register">还没有账户，马上<a href="register.html">注册</a></div>
		</div>
		 <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="js/jquery-1.11.1.min.js"></script>
    <script>window.jQuery || document.write('<script src="js/jquery.min.js"><\/script>')</script>
    <script src="js/bootstrap.min.js"></script>
    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <script src="js/ie10-viewport-bug-workaround.js"></script>
	</body>
</html>
