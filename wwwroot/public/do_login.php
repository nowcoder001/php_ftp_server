<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月8日
 * Time: 下午5:08:02
 */

if(isset($_POST)){
    $username=$_POST['username'];
    $password=$_POST['password'];
    $user=new User();
    $result=$user->checkUser($username, $password);
    if($result){
        $list=$user->getUserListOfGroup('admin');
        if(in_array($username, $list)){
            $arr=serialize(array('username'=>$username,'password'=>$password));
            $token=urlencode(base64_encode($arr));
           success('登录成功', 'index.php?token='.$token, 3);
           $_COOKIE['admin']=$_POST;
        }else{
            error('登录失败', 'login.php', 3);
        }
    }else{
        error('登录失败', 'login.php', 3);
    }
   
}