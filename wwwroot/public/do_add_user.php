<?php
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
    //unset($usr);
    if($flag){
        unset($flag);
        $user=$_POST['user']?$_POST['user']:'';
        $pass=$_POST['password']?$_POST['password']:'';
        $home=$_POST['home']?$_POST['home']:'';
        $expired=$_POST['expired']?$_POST['expired']:date('Y-m-d',time());
        if($user && $pass && $home && $expired){
            unset($flag);
            $usr->addUser($user, $pass, $home, $expired);
            $profile=array(
                'folder'=>array(
                    array('path'=>$home.'/public','access'=>'RWANDLCNDI')
                ),
                'ip'=>array(
                    'allow'=>array('0.0.0.0'),
                )
            );
            $usr->setUserProfile($user, $profile);
            $usr->save();
            success('添加完成', "/index.php?token={$_GET['token']}");
        }else{
            unset($flag);
            error('请输入完整的用户', "/addUser.php?token={$_GET['token']}");
        }        
        
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