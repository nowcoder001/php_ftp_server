<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年5月24日
 * Time: 下午11:21:08
 */
class Autoload
{
    private static $loader;
    public function __construct(){
        require_once 'common.php';
        require_once 'config.php';
    }
    public static function autoload($class) {
        $class=ucfirst($class);
        $controller=__ROOT__.'/../App/Controller/';
        $model=__ROOT__.'/../App/Model/';
        if(file_exists($controller.'/'.$class.'Controller.class.php')){
           require_once $controller.'HomebaseController.class.php';
           require_once $controller.'MemberController.class.php';
           require_once $controller. $class.'Controller.class.php';
        }elseif (file_exists($model.'/'.$class.'Model.class.php')){
            require_once $model.'Model.class.php';
            require_once $model. $class.'Model.class.php';
        }
    }
    public static function run(){
        //控制器
        if(isset($_GET['cont'])){
            $a=ucfirst($_GET['cont']);
        }else{
            $a=ucfirst(HOME);
        }
        
        //方法
        if(isset($_GET['action'])){
            $b=ucfirst($_GET['action']);
        }else{
            $b=ucfirst('index');
        }
        self::autoload($a);
        $c=$a.'Controller';
        $controller=new $c;
        $result=$controller->$b();
        return $result;
    }
}


