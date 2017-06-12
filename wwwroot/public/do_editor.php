<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月9日
 * Time: 下午5:34:59
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
    
    if($flag){
        unset($flag);
        if($_POST['pass']==null){
            unset($_POST['pass']);
        }
        if($_POST['group']==null){
            unset($_POST['group']);
        }
        foreach($_POST['folder'] as $k=>$v){
            if($k!=0 && $v['path']==null){
                unset($_POST['folder'][$k]);
            }else{
                if(strlen($v['access'])!=10){
                    $_POST['folder'][$k]['access']="RWAND-----";
                }
                if($k==0 && $v['path']==null){
                    $_POST['folder'][$k]['path']=$_POST['home'];
                }
            }
        }
        $usr->setUserProfile($_POST['user'], $_POST);
        $usr->save();
        success('修改完成', 'index.php?token='.$_GET['token']);
    }else{
        unset($flag);
        error('请重新登录', 'login.php');
    }
    unset($usr);
    //     if(checkadmin($token)){
    //         error('请重新登录', 'login.php');
    //     }

}else{
    error('请先登录', 'login.php');
}