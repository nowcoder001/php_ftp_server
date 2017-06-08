<?php
/**这个主要是嵌入在ftp的http服务器类，功能不是很完善，
 * 进行ftp的管理还是可行的。不过需要注意的是，
 * 这个实现与apache等其他http服务器运行的方式可能有所不同。
 * 代码是驻留内存的。
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月3日
 * Time: 上午10:55:07
 *
 */
//内置的web服务器类
class CWebServer{
    protected $buffer_header = array();
    protected $buffer_maxlen = 65535; //最大POST尺寸
    const DATE_FORMAT_HTTP = 'D, d-M-Y H:i:s T';
    const HTTP_EOF = "\r\n\r\n";
    const HTTP_HEAD_MAXLEN = 8192; //http头最大长度不得超过2k
    const HTTP_POST_MAXLEN = 1048576;//1m
    const ST_FINISH = 1; //完成，进入处理流程
    const ST_WAIT = 2; //等待数据
    const ST_ERROR = 3; //错误，丢弃此包
    static  $content_type='text/html';
    private $requsts = array();
    private $config = array();
    public function log($msg,$level = 'debug'){
        echo date('Y-m-d H:i:s').' ['.$level."]\t" .$msg."\n";
    }
    public function __construct($config = array()){
        $this->config = array(
            'wwwroot' => BASE_PATH.'/wwwroot/public/',
            'index' => 'index.php',
            'path_deny' => array('/protected/'),
        );
    }
    public function onReceive($serv,$fd,$data){
        $ret = $this->checkData($fd, $data);
        switch ($ret){
            case self::ST_ERROR:
                $serv->close($fd);
                $this->cleanBuffer($fd);
                $this->log('Recevie error.');
                break;
            case self::ST_WAIT:
                $this->log('Recevie wait.');
                return;
            default:
                break;
        }
        //开始完整的请求
        $request = $this->requsts[$fd];
        $info = $serv->connection_info($fd);
        $request = $this->parseRequest($request);
        $request['remote_ip'] = $info['remote_ip'];
        $response = $this->onRequest($request);
        $output = $this->parseResponse($request,$response);
        $serv->send($fd,$output);
        if(isset($request['head']['Connection']) && strtolower($request['head']['Connection']) == 'close'){
            $serv->close($fd);
        }
        unset($this->requsts[$fd]);
        $_REQUEST = $_SESSION = $_COOKIE = $_FILES = $_POST = $_SERVER = $_GET = array();
    }
    /**
     * 处理请求
     * @param array $request
     * @return array $response
     *
     * $request=array(
     * 'time'=>
     * 'head'=>array(
     * 'method'=>
     * 'path'=>
     * 'protocol'=>
     * 'uri'=>
     * //other http header
     * '..'=>value
     * )
     * 'body'=>
     * 'get'=>(if appropriate)
     * 'post'=>(if appropriate)
     * 'cookie'=>(if appropriate)
     *
     *
     * )
     */
    public function onRequest($request){
        if($request['head']['path'][strlen($request['head']['path']) - 1] == '/'){
            $request['head']['path'] .= $this->config['index'];
        }
        $response = $this->process($request);
        return $response;
    }
    /**
     * 清除数据
     * @param unknown $fd
     */
    public function cleanBuffer($fd){
        unset($this->requsts[$fd]);
        unset($this->buffer_header[$fd]);
    }
    /**
     * 检查数据
     * @param unknown $fd
     * @param unknown $data
     * @return string
     */
    public function checkData($fd,$data){
        if(isset($this->buffer_header[$fd])){
            $data = $this->buffer_header[$fd].$data;
        }
        $request = $this->checkHeader($fd, $data);
        //请求头错误
        if($request === false){
            $this->buffer_header[$fd] = $data;
            if(strlen($data) > self::HTTP_HEAD_MAXLEN){
                return self::ST_ERROR;
            }else{
                return self::ST_WAIT;
            }
        }
        //post请求检查
        if($request['head']['method'] == 'POST'){
            return $this->checkPost($request);
        }else{
            return self::ST_FINISH;
        }
    }
    /**
     * 检查请求头
     * @param unknown $fd
     * @param unknown $data
     * @return boolean|array
     */
    public function checkHeader($fd, $data){
        //新的请求
        if(!isset($this->requsts[$fd])){
            //http头结束符
            $ret = strpos($data,self::HTTP_EOF);
            if($ret === false){
                return false;
            }else{
                $this->buffer_header[$fd] = '';
                $request = array();
                list($header,$request['body']) = explode(self::HTTP_EOF, $data,2);
                $request['head'] = $this->parseHeader($header);
                $this->requsts[$fd] = $request;
                if($request['head'] == false){
                    return false;
                }
            }
        }else{
            //post 数据合并
            $request = $this->requsts[$fd];
            $request['body'] .= $data;
        }
        return $request;
    }
    /**
     * 解析请求头
     * @param string $header
     * @return array
     * array(
     * 'method'=>,
     * 'uri'=>
     * 'protocol'=>
     * 'name'=>value,...
     *
     *
     *
     * }
     */
    public function parseHeader($header){
        $request = array();
        $headlines = explode("\r\n", $header);
        list($request['method'],$request['uri'],$request['protocol']) = explode(' ', $headlines[0],3);
        foreach ($headlines as $k=>$line){
            $line = trim($line);
            if($k && !empty($line) && strpos($line,':') !== false){
                list($name,$value) = explode(':', $line,2);
                $request[trim($name)] = trim($value);
            }
        }
        return $request;
    }
    /**
     * 检查post数据是否完整
     * @param unknown $request
     * @return string
     */
    public function checkPost($request){
        if(isset($request['head']['Content-Length'])){
            if(intval($request['head']['Content-Length']) > self::HTTP_POST_MAXLEN){
                return self::ST_ERROR;
            }
            if(intval($request['head']['Content-Length']) > strlen($request['body'])){
                return self::ST_WAIT;
            }else{
                return self::ST_FINISH;
            }
        }
        return self::ST_ERROR;
    }
    /**
     * 解析请求
     * @param unknown $request
     * @return Ambigous <unknown, mixed, multitype:string >
     */
    public function parseRequest($request){
        $request['time'] = time();
        $url_info = parse_url($request['head']['uri']);
        $request['head']['path'] = $url_info['path'];
        if(isset($url_info['fragment']))$request['head']['fragment'] = $url_info['fragment'];
        if(isset($url_info['query'])){
            parse_str($url_info['query'],$request['get']);
        }
        //parse post body
        if($request['head']['method'] == 'POST'){
            //目前只处理表单提交
            if (isset($request['head']['Content-Type']) && substr($request['head']['Content-Type'], 0, 33) == 'application/x-www-form-urlencoded'
                || isset($request['head']['X-Request-With']) && $request['head']['X-Request-With'] == 'XMLHttpRequest'){
                    parse_str($request['body'],$request['post']);
            }
        }
        //parse cookies
        if(!empty($request['head']['Cookie'])){
            $params = array();
            $blocks = explode(";", $request['head']['Cookie']);
            foreach ($blocks as $b){
                $_r = explode("=", $b, 2);
                if(count($_r)==2){
                    list ($key, $value) = $_r;
                    $params[trim($key)] = trim($value, "\r\n \t\"");
                }else{
                    $params[$_r[0]] = '';
                }
            }
            $request['cookie'] = $params;
        }
        return $request;
    }
    public function parseResponse($request,$response){
        if(!isset($response['head']['Date'])){
            $response['head']['Date'] = gmdate("D, d M Y H:i:s T");
        }
        if(!isset($response['head']['Content-Type'])){
            $response['head']['Content-Type'] = 'text/html;charset=utf-8';
        }
        if(!isset($response['head']['Content-Length'])){
            $response['head']['Content-Length'] = strlen($response['body']);
        }
        if(!isset($response['head']['Connection'])){
            if(isset($request['head']['Connection']) && strtolower($request['head']['Connection']) == 'keep-alive'){
                $response['head']['Connection'] = 'keep-alive';
            }else{
                $response['head']['Connection'] = 'close';
            }
        }
        $response['head']['Server'] = CFtpServer::$software.'/'.CFtpServer::VERSION;
        $out = '';
        if(isset($response['head']['Status'])){
            $out .= 'HTTP/1.1 '.$response['head']['Status']."\r\n";
            unset($response['head']['Status']);
        }else{
            $out .= "HTTP/1.1 200 OK\r\n";
        }
        //headers
        foreach($response['head'] as $k=>$v){
            $out .= $k.': '.$v."\r\n";
        }
        //cookies
        if($_COOKIE){
            $arr = array();
            foreach ($_COOKIE as $k => $v){
                $arr[] = $k.'='.$v;
            }
            $out .= 'Set-Cookie: '.implode(';', $arr)."\r\n";
        }
        //End
        $out .= "\r\n";
        $out .= $response['body'];
        return $out;
    }
    /**
     * 处理请求
     * @param unknown $request
     * @return array
     */
    public function process($request){
        $path = $request['head']['path'];
        $isDeny = false;
        foreach ($this->config['path_deny'] as $p){
            if(strpos($path, $p) === 0){
                $isDeny = true;
                break;
            }
        }
        if($isDeny){
            return $this->httpError(403, '服务器拒绝访问:路径错误');
        }
        if(!in_array($request['head']['method'],array('GET','POST'))){
            return $this->httpError(500, '服务器拒绝访问:错误的请求方法');
        }
        $file_ext = strtolower(trim(substr(strrchr($path, '.'), 1)));
        $path = realpath(rtrim($this->config['wwwroot'],'/'). '/' . ltrim($path,'/'));
        $this->log('WEB:['.$request['head']['method'].'] '.$request['head']['uri'] .' '.json_encode(isset($request['post'])?$request['post']:array()));
        $response = array();
        if($file_ext == 'php'){
            if(is_file($path)){
                //设置全局变量
                if(isset($request['get']))$_GET = $request['get'];
                if(isset($request['post']))$_POST = $request['post'];
                if(isset($request['cookie']))$_COOKIE = $request['cookie'];
                $_REQUEST = array_merge($_GET,$_POST, $_COOKIE);
                foreach ($request['head'] as $key => $value){
                    $_key = 'HTTP_'.strtoupper(str_replace('-', '_', $key));
                    $_SERVER[$_key] = $value;
                }
                $_SERVER['REMOTE_ADDR'] = $request['remote_ip'];
                $_SERVER['REQUEST_URI'] = $request['head']['uri'];
                //进行http auth
//                 if(isset($_GET['c']) && strtolower($_GET['c']) != 'site'){
//                     if(isset($request['head']['Authorization'])){
//                         $user = new User();
//                         if($user->checkUserBasicAuth($request['head']['Authorization'])){
//                             $response['head']['Status'] = self::$HTTP_HEADERS[200];
//                             goto process;
//                         }
//                     }
//                     $response['head']['Status'] = self::$HTTP_HEADERS[401];
//                     $response['head']['WWW-Authenticate'] = 'Basic realm="Real-Data-FTP"';
//                     $_GET['c'] = 'Site';
//                     $_GET['a'] = 'Unauthorized';
//                 }
                process:
                ob_start();
                try{
                    include $path;
                    $response['body'] = ob_get_contents();
                    $response['head']['Content-Type'] = self::$content_type;
                    $response['head']['Charset'] = 'utf-8';
                }catch (Exception $e){
                    $response = $this->httpError(500, $e->getMessage());
                }
                ob_end_clean();
            }else{
                $response = $this->httpError(404, '页面不存在');
            }
        }else{
            //处理静态文件
            if(is_file($path)){
                $response['head']['Content-Type'] = isset(self::$MIME_TYPES[$file_ext]) ? self::$MIME_TYPES[$file_ext]:"application/octet-stream";
                //使用缓存
                if(!isset($request['head']['If-Modified-Since'])){
                    $fstat = stat($path);
                    $expire = 2592000;//30 days
                    $response['head']['Status'] = self::$HTTP_HEADERS[200];
                    $response['head']['Cache-Control'] = "max-age={$expire}";
                    $response['head']['Pragma'] = "max-age={$expire}";
                    $response['head']['Last-Modified'] = date(self::DATE_FORMAT_HTTP, $fstat['mtime']);
                    $response['head']['Expires'] = "max-age={$expire}";
                    $response['body'] = file_get_contents($path);
                }else{
                    $response['head']['Status'] = self::$HTTP_HEADERS[304];
                    $response['body'] = '';
                }
            }else{
                $response = $this->httpError(404, '页面不存在');
            }
        }
        return $response;
    }
    public function httpError($code, $content){
        $response = array();
        $version = CFtpServer::$software.'/'.CFtpServer::VERSION;
        $response['head']['Content-Type'] = 'text/html;charset=utf-8';
        $response['head']['Status'] = self::$HTTP_HEADERS[$code];
        $response['body'] = <<<html
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>FTP后台管理 </title>
</head>
<body>
<h1>{$code}</h1>
<p>{$content}</p>
<div style="text-align:center">
<hr>
{$version} Copyright © 2017 by <a target='_new' href='http://jose.scjtqs.cn'>莫名居</a> 维护支持.
</div>
</body>
</html>
html;
return $response;
    }
    static $HTTP_HEADERS = array(
        100 => "100 Continue",
        101 => "101 Switching Protocols",
        200 => "200 OK",
        201 => "201 Created",
        204 => "204 No Content",
        206 => "206 Partial Content",
        300 => "300 Multiple Choices",
        301 => "301 Moved Permanently",
        302 => "302 Found",
        303 => "303 See Other",
        304 => "304 Not Modified",
        307 => "307 Temporary Redirect",
        400 => "400 Bad Request",
        401 => "401 Unauthorized",
        403 => "403 Forbidden",
        404 => "404 Not Found",
        405 => "405 Method Not Allowed",
        406 => "406 Not Acceptable",
        408 => "408 Request Timeout",
        410 => "410 Gone",
        413 => "413 Request Entity Too Large",
        414 => "414 Request URI Too Long",
        415 => "415 Unsupported Media Type",
        416 => "416 Requested Range Not Satisfiable",
        417 => "417 Expectation Failed",
        500 => "500 Internal Server Error",
        501 => "501 Method Not Implemented",
        503 => "503 Service Unavailable",
        506 => "506 Variant Also Negotiates",
    );
    static $MIME_TYPES = array(
        'jpg' => 'image/jpeg',
        'bmp' => 'image/bmp',
        'ico' => 'image/x-icon',
        'gif' => 'image/gif',
        'png' => 'image/png' ,
        'bin' => 'application/octet-stream',
        'js' => 'application/javascript',
        'css' => 'text/css' ,
        'html' => 'text/html' ,
        'xml' => 'text/xml',
        'tar' => 'application/x-tar' ,
        'ppt' => 'application/vnd.ms-powerpoint',
        'pdf' => 'application/pdf' ,
        'svg' => ' image/svg+xml',
        'woff' => 'application/x-font-woff',
        'woff2' => 'application/x-font-woff',
    );
}