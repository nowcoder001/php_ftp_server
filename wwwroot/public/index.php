<?php
namespace Scjtqs;
use Vendor\Autoload;
require __DIR__.'/../vendor/autoload.php';
define('HOME', 'Index');
define('__ROOT__', __DIR__);
function __autoload($class){
    Autoload::autoload($class);
}
return Autoload::run();
?>