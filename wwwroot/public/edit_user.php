<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月9日
 * Time: 下午2:46:53
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
    $groupList=$usr->getGroupList();
   
    if(!$userinfo){
        error('未查询到此用户', 'index.php?token='.$_GET['token']);
    }else{
        if($flag){
            unset($flag);
            include BASE_PATH.'/wwwroot/main/edit_user.html';
        }else{
            unset($flag);
            error('请重新登录', 'login.php');
        }
    }
    unset($usr);
   
    //     if(checkadmin($token)){
    //         error('请重新登录', 'login.php');
    //     }

}else{
    error('请先登录', 'login.php');
}