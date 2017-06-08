<?php
namespace App\Controller;
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月1日
 * Time: 上午9:56:08
 */
class IndexController extends HomebaseController{
    public function Index(){
        $this->display('login');
    }
    public function Login(){
        $this->display('login');
    }
    public function Register(){
        $this->display('register');
    }
    public function details(){
        $this->display('blog');
    }
}

