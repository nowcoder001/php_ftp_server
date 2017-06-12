<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月8日
 * Time: 下午4:57:53
 */
if(isset($_GET['token']) && $_GET['token']!=null){
    $flag=false;
    $token=$_GET['token'];
    $usr=new User();
    $token1=urldecode($token);
    $token2=base64_decode($token1);
    $arr=unserialize($token2);
    $result=$usr->checkUser($arr['username'], $arr['password']);
    if($result){
        $list=$usr->getUserListOfGroup('admin');
        if(in_array($arr['username'], $list)){
            $flag=true;
        }
    }
    unset($usr);
    if($flag){
        unset($flag);
        include BASE_PATH.'/wwwroot/main/main.php';
    }else{
        unset($flag);
        error('请重新登录', 'login.php');
    }
//     if(checkadmin($token)){
//         error('请重新登录', 'login.php');
//     }
    
}else{
    error('请先登录', 'login.php');
}