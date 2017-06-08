<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月1日
 * Time: 上午10:02:53
 */
class HomebaseController{
    public function display($file='index'){
        include_once __ROOT__.'/../App/View/'.$file.'.php';
    }
}