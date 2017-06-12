<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月9日
 * Time: 下午5:13:44
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
    $user=$_GET['user'];
    $userinfo=$usr->getUserProfile($user);
    //unset($usr);
    if(!$userinfo){
        unset($usr);
        error('未查询到此用户', 'index.php?token='.$_GET['token']);
    }else{
        if($flag){
            unset($flag);
            $usr->delUser($_GET['user']);
            $usr->save();
            success('删除完成', 'index.php?token='.$_GET['token']);
        }else{
            unset($flag);
            error('请重新登录', 'login.php');
        }
    }
     
    //     if(checkadmin($token)){
    //         error('请重新登录', 'login.php');
    //     }

}else{
    error('请先登录', 'login.php');
}