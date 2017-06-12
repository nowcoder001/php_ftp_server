<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月8日
 * Time: 下午5:41:02
 */
function success($message,$jumpUrl,$waitSecond=3){
   //$str=file_get_contents(BASE_PATH.'/wwwroot/public/success.html');
   //echo $str;
   //unset($str);
   include BASE_PATH.'/wwwroot/public/success.html';
}
function error($message,$jumpUrl,$waitSecond=3){
    //$str=file_get_contents(BASE_PATH.'/wwwroot/public/error.html');
    //echo $str;
    //unset($str);
    include BASE_PATH.'/wwwroot/public/error.html';
}
function checkadmin($token){
    $flag=false;
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
    return $flag;
}