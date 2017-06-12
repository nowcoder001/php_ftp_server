<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月8日
 * Time: 下午7:12:12
 */
$usr=new User();

$userArr=$usr->getUserList();
$UserList=array();
foreach ($userArr as $k=>$v){
    $UserList[$k]=$usr->getUserProfile($v);
    $UserList[$k]['username']=$userArr[$k];
}
include 'main.html';

