<?php
/**FTP主类
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月3日
 * Time: 上午10:55:28
 */
//有了前面类，就可以在ftp进行引用了。使用ssl时，请注意进行防火墙passive 端口范围的nat配置。

class CFtpServer{
    //软件版本
    const VERSION = '1.1';
    const EOF = "\r\n";
    public static $software ="FTP-Server";
    private static $server_mode = SWOOLE_PROCESS;
    private static $pid_file;
    private static $log_file=BASE_PATH.'/logs/log';
    //待写入文件的日志队列（缓冲区）
    private $queue = array();
    private $pasv_port_range = array(PASV_PORT_MIN,PASV_PORT_MAX);
    public $host = LOCALHOST;
    public $port = FTP_PORT;
    public $setting = array();
    //最大连接数
    public $max_connection = MAX_CONNECTION;
    //web管理端口
    public $manager_port = HTTP_PORT;
    //tls
    public $ftps_port =FTPS_PORT;
    /**
     * @var swoole_server
     */
    protected $server;
    protected $connection = array();
    protected $session = array();
    protected $user;//用户类，复制验证与权限
    //共享内存类
    protected $shm;//ShareMemory
    /**
    *
    * @var embedded http server
    */
    protected $webserver;
    /*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++
     + 静态方法
     +++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
    public static function setPidFile($pid_file){
        self::$pid_file = $pid_file;
    }
    /**
     * 服务启动控制方法
     */
    public static function start($startFunc){
        if(empty(self::$pid_file)){
            exit("Require pid file.\n");
        }
        if(!extension_loaded('posix')){
            exit("Require extension `posix`.\n");
        }
        if(!extension_loaded('swoole')){
            exit("Require extension `swoole`.\n");
        }
        if(!extension_loaded('shmop')){
            exit("Require extension `shmop`.\n");
        }
        if(!extension_loaded('openssl')){
            exit("Require extension `openssl`.\n");
        }
        $pid_file = self::$pid_file;
        $server_pid = 0;
        if(is_file($pid_file)){
            $server_pid = file_get_contents($pid_file);
        }
        global $argv;
        if(empty($argv[1])){
            goto usage;
        }elseif($argv[1] == 'reload'){
            if (empty($server_pid)){
                exit("FtpServer is not running\n");
            }
            posix_kill($server_pid, SIGUSR1);
            exit;
        }elseif ($argv[1] == 'stop'){
            if (empty($server_pid)){
                exit("FtpServer is not running\n");
            }
            posix_kill($server_pid, SIGTERM);
            exit;
        }elseif ($argv[1] == 'start'){
            //已存在ServerPID，并且进程存在
            if (!empty($server_pid) and posix_kill($server_pid,(int) 0)){
                exit("FtpServer is already running.\n");
            }
            //启动服务器
            $startFunc();
        }else{
            usage:
            exit("Usage: php {$argv[0]} start|stop|reload\n");
        }
    }
    /*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++
     + 方法
     +++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
    public function __construct(){
        $this->user = new User();
        $this->shm = new ShareMemory();
        $this->shm->write(array());
        $flag = SWOOLE_SOCK_TCP;
        $host=$this->host;
        $port=$this->port;
        $this->server = new swoole_server($host,$port,self::$server_mode,$flag);
        $this->setting = array(
            'backlog' => 128,
            'dispatch_mode' => 2,
            "worker_num"=>100,
        );
    }
    /***
     * 设置ssl证书路径
     * @param string $crt crt文件的绝对路径
     * @param string $key key文件的绝对路径
     */
    public function set_ssl($crt,$key){
        $this->setting['ssl_key_file']=$key;
        $this->setting['ssl_cert_file']=$crt;
    }
    public function daemonize(){
        $this->setting['daemonize'] = 1;
    }
    public function getConnectionInfo($fd){
        return $this->server->connection_info($fd);
    }
    /**
     * 启动服务进程
     * @param array $setting
     * @throws Exception
     */
    public function run($setting = array()){
        $this->setting = array_merge($this->setting,$setting);
        //不使用swoole的默认日志
        if(isset($this->setting['log_file'])){
            self::$log_file = $this->setting['log_file'];
            unset($this->setting['log_file']);
        }
        if(isset($this->setting['max_connection'])){
            $this->max_connection = $this->setting['max_connection'];
            unset($this->setting['max_connection']);
        }
        if(isset($this->setting['manager_port'])){
            $this->manager_port = $this->setting['manager_port'];
            unset($this->setting['manager_port']);
        }
        if(isset($this->setting['ftps_port'])){
            $this->ftps_port = $this->setting['ftps_port'];
            unset($this->setting['ftps_port']);
        }
        if(isset($this->setting['passive_port_range'])){
            $this->pasv_port_range = $this->setting['passive_port_range'];
            unset($this->setting['passive_port_range']);
        }
        $this->server->set($this->setting);
        $version = explode('.', SWOOLE_VERSION);
        if($version[0] == 1 && $version[1] < 7 && $version[2] <20){
            throw new Exception('Swoole version require 1.7.20 +.');
        }
        //事件绑定
        $this->server->on('start',array($this,'onMasterStart'));
        $this->server->on('shutdown',array($this,'onMasterStop'));
        $this->server->on('ManagerStart',array($this,'onManagerStart'));
        $this->server->on('ManagerStop',array($this,'onManagerStop'));
        $this->server->on('WorkerStart',array($this,'onWorkerStart'));
        $this->server->on('WorkerStop',array($this,'onWorkerStop'));
        $this->server->on('WorkerError',array($this,'onWorkerError'));
        $this->server->on('Connect',array($this,'onConnect'));
        $this->server->on('Receive',array($this,'onReceive'));
        $this->server->on('Close',array($this,'onClose'));
        //管理端口
        $this->server->addlistener($this->host,$this->manager_port,SWOOLE_SOCK_TCP);
        //tls
        $this->server->addlistener($this->host,$this->ftps_port,SWOOLE_SOCK_TCP | SWOOLE_SSL);
        $this->server->start();
    }
    public function log($msg,$level = 'debug',$flush = false){
        if(DEBUG_ON){
            $log = date('Y-m-d H:i:s').' ['.$level."]\t" .$msg."\n";
            if(!empty(self::$log_file)){
                $debug_file = dirname(self::$log_file).'/debug.log';
                file_put_contents($debug_file, $log,FILE_APPEND);
                if(filesize($debug_file) > 10485760){//10M
                    unlink($debug_file);
                }
            }
            echo $log;
        }
        if($level != 'debug'){
            //日志记录
            $this->queue[] = date('Y-m-d H:i:s')."\t[".$level."]\t".$msg;
        }
        if(count($this->queue)>10 && !empty(self::$log_file) || $flush){
            if (filesize(self::$log_file) > 209715200){ //200M
                rename(self::$log_file,self::$log_file.'.'.date('His'));
            }
            $logs = '';
            foreach ($this->queue as $q){
                $logs .= $q."\n";
            }
            file_put_contents(self::$log_file, $logs,FILE_APPEND);
            $this->queue = array();
        }
    }
    public function shutdown(){
        return $this->server->shutdown();
    }
    public function close($fd){
        return $this->server->close($fd);
    }
    public function send($fd,$data){
        $data = strtr($data,array("\n" => "", "\0" => "", "\r" => ""));
        $this->log("[-->]\t" . $data);
        return $this->server->send($fd,$data.self::EOF);
    }
    /*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++
     + 事件回调
     +++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
    public function onMasterStart($serv){
        global $argv;
        swoole_set_process_name('php '.$argv[0].': master -host='.$this->host.' -port='.$this->port.'/'.$this->manager_port);
        if(!empty($this->setting['pid_file'])){
            file_put_contents(self::$pid_file, $serv->master_pid);
        }
        $this->log('Master started.');
    }
    public function onMasterStop($serv){
        if (!empty($this->setting['pid_file'])){
            unlink(self::$pid_file);
        }
        $this->shm->delete();
        $this->log('Master stop.');
    }
    public function onManagerStart($serv){
        global $argv;
        swoole_set_process_name('php '.$argv[0].': manager');
        $this->log('Manager started.');
    }
    public function onManagerStop($serv){
        $this->log('Manager stop.');
    }
    public function onWorkerStart($serv,$worker_id){
        global $argv;
        if($worker_id >= $serv->setting['worker_num']) {
            swoole_set_process_name("php {$argv[0]}: worker [task]");
            //cli_set_process_title("php {$argv[0]}: worker [task]");
        } else {
            swoole_set_process_name("php {$argv[0]}: worker [{$worker_id}]");
           // cli_set_process_title("php {$argv[0]}: worker [{$worker_id}]");
        }
        $this->log("Worker {$worker_id} started.");
    }
    public function onWorkerStop($serv,$worker_id){
        $this->log("Worker {$worker_id} stop.");
    }
    public function onWorkerError($serv,$worker_id,$worker_pid,$exit_code){
        $this->log("Worker {$worker_id} error:{$exit_code}.");
    }
    public function onConnect($serv,$fd,$from_id){
        $info = $this->getConnectionInfo($fd);
        if($info['server_port'] == $this->manager_port){
            //web请求
            $this->webserver = new CWebServer();
        }else{
            $this->send($fd, "220---------- Welcome to " . self::$software . " ----------");
            $this->send($fd, "220-Local time is now " . date("H:i"));
            $this->send($fd, "220 This is a private system - No anonymous login");
            if(count($this->server->connections) <= $this->max_connection){
                if($info['server_port'] == $this->port && isset($this->setting['force_ssl']) && $this->setting['force_ssl']){
                    //如果启用强制ssl
                    $this->send($fd, "421 Require implicit FTP over tls, closing control connection.");
                    $this->close($fd);
                    return ;
                }
                $this->connection[$fd] = array();
                $this->session = array();
                $this->queue = array();
            }else{
                $this->send($fd, "421 Too many connections, closing control connection.");
                $this->close($fd);
            }
        }
    }
    public function onReceive($serv,$fd,$from_id,$recv_data){
        $info = $this->getConnectionInfo($fd);
        if($info['server_port'] == $this->manager_port){
            //web请求
            $this->webserver->onReceive($this->server, $fd, $recv_data);
        }else{
            $read = trim($recv_data);
            $this->log("[<--]\t" . $read);
            $cmd = explode(" ", $read);
            $func = 'cmd_'.strtoupper($cmd[0]);
            $data = trim(str_replace($cmd[0], '', $read));
            if (!method_exists($this, $func)){
                $this->send($fd, "500 Unknown Command");
                return;
            }
            if (empty($this->connection[$fd]['login'])){
                switch($cmd[0]){
                    case 'TYPE':
                    case 'USER':
                    case 'PASS':
                    case 'QUIT':
                    case 'AUTH':
                    case 'PBSZ':
                        break;
                    default:
                        $this->send($fd,"530 You aren't logged in");
                        return;
                }
            }
            $this->$func($fd,$data);
        }
    }
    public function onClose($serv,$fd,$from_id){
        //在线用户
        $shm_data = $this->shm->read();
        if($shm_data !== false){
            if(isset($shm_data['online'])){
                $list = array();
                foreach($shm_data['online'] as $u => $info){
                    if(!preg_match('/\.*-'.$fd.'$/',$u,$m))
                        $list[$u] = $info;
                }
                $shm_data['online'] = $list;
                $this->shm->write($shm_data);
            }
        }
        $this->log('Socket '.$fd.' close. Flush the logs.','debug',true);
    }
    /*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++
     + 工具函数
     +++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
    /**
     * 获取用户名
     * @param $fd
     */
    public function getUser($fd){
        return isset($this->connection[$fd]['user'])?$this->connection[$fd]['user']:'';
    }
    /**
     * 获取文件全路径
     * @param $user
     * @param $file
     * @return string|boolean
     */
    public function getFile($user, $file){
        $file = $this->fillDirName($user, $file);
        if (is_file($file)){
            return realpath($file);
        }else{
            return false;
        }
    }
    /**
     * 遍历目录
     * @param $rdir
     * @param $showHidden
     * @param $format list/mlsd
     * @return string
     *
     * list 使用local时间
     * mlsd 使用gmt时间
     */
    public function getFileList($user, $rdir, $showHidden = false, $format = 'list'){
        $filelist = '';
        if($format == 'mlsd'){
            $stats = stat($rdir);
            $filelist.= 'Type=cdir;Modify='.gmdate('YmdHis',$stats['mtime']).';UNIX.mode=d'.$this->mode2char($stats['mode']).'; '.$this->getUserDir($user)."\r\n";
        }
        if ($handle = opendir($rdir)){
            $isListable = $this->user->isFolderListable($user, $rdir);
            while (false !== ($file = readdir($handle))){
                if ($file == '.' or $file == '..'){
                    continue;
                }
                if ($file{0} == "." and !$showHidden){
                    continue;
                }
                //如果当前目录$rdir不允许列出，则判断当前目录下的目录是否配置为可以列出
                if(!$isListable){
                    $dir = $rdir . $file;
                    if(is_dir($dir)){
                        $dir = $this->joinPath($dir, '/');
                        if($this->user->isFolderListable($user, $dir)){
                            goto listFolder;
                        }
                    }
                    continue;
                }
                listFolder:
                $stats = stat($rdir . $file);
                if (is_dir($rdir . "/" . $file)) $mode = "d"; else $mode = "-";
                $mode .= $this->mode2char($stats['mode']);
                if($format == 'mlsd'){
                    if($mode[0] == 'd'){
                        $filelist.= 'Type=dir;Modify='.gmdate('YmdHis',$stats['mtime']).';UNIX.mode='.$mode.'; '.$file."\r\n";
                    }else{
                        $filelist.= 'Type=file;Size='.$stats['size'].';Modify='.gmdate('YmdHis',$stats['mtime']).';UNIX.mode='.$mode.'; '.$file."\r\n";
                    }
                }else{
                    $uidfill = "";
                    for ($i = strlen($stats['uid']); $i < 5; $i++) $uidfill .= " ";
                    $gidfill = "";
                    for ($i = strlen($stats['gid']); $i < 5; $i++) $gidfill .= " ";
                    $sizefill = "";
                    for ($i = strlen($stats['size']); $i < 11; $i++) $sizefill .= " ";
                    $nlinkfill = "";
                    for ($i = strlen($stats['nlink']); $i < 5; $i++) $nlinkfill .= " ";
                    $mtime = date("M d H:i", $stats['mtime']);
                    $filelist .= $mode . $nlinkfill . $stats['nlink'] . " " . $stats['uid'] . $uidfill . $stats['gid'] . $gidfill . $sizefill . $stats['size'] . " " . $mtime . " " . $file . "\r\n";
                }
            }
            closedir($handle);
        }
        return $filelist;
    }
    /**
     * 将文件的全新从数字转换为字符串
     * @param int $int
     */
    public function mode2char($int){
        $mode = '';
        $moded = sprintf("%o", ($int & 000777));
        $mode1 = substr($moded, 0, 1);
        $mode2 = substr($moded, 1, 1);
        $mode3 = substr($moded, 2, 1);
        switch ($mode1) {
            case "0":
                $mode .= "---";
                break;
            case "1":
                $mode .= "--x";
                break;
            case "2":
                $mode .= "-w-";
                break;
            case "3":
                $mode .= "-wx";
                break;
            case "4":
                $mode .= "r--";
                break;
            case "5":
                $mode .= "r-x";
                break;
            case "6":
                $mode .= "rw-";
                break;
            case "7":
                $mode .= "rwx";
                break;
        }
        switch ($mode2) {
            case "0":
                $mode .= "---";
                break;
            case "1":
                $mode .= "--x";
                break;
            case "2":
                $mode .= "-w-";
                break;
            case "3":
                $mode .= "-wx";
                break;
            case "4":
                $mode .= "r--";
                break;
            case "5":
                $mode .= "r-x";
                break;
            case "6":
                $mode .= "rw-";
                break;
            case "7":
                $mode .= "rwx";
                break;
        }
        switch ($mode3) {
            case "0":
                $mode .= "---";
                break;
            case "1":
                $mode .= "--x";
                break;
            case "2":
                $mode .= "-w-";
                break;
            case "3":
                $mode .= "-wx";
                break;
            case "4":
                $mode .= "r--";
                break;
            case "5":
                $mode .= "r-x";
                break;
            case "6":
                $mode .= "rw-";
                break;
            case "7":
                $mode .= "rwx";
                break;
        }
        return $mode;
    }
    /**
     * 设置用户当前的路径
     * @param $user
     * @param $pwd
     */
    public function setUserDir($user, $cdir){
        $old_dir = $this->session[$user]['pwd'];
        if ($old_dir == $cdir){
            return $cdir;
        }
        if($cdir[0] != '/')
            $cdir = $this->joinPath($old_dir,$cdir);
            $this->session[$user]['pwd'] = $cdir;
            $abs_dir = realpath($this->getAbsDir($user));
            if (!$abs_dir){
                $this->session[$user]['pwd'] = $old_dir;
                return false;
            }
            $this->session[$user]['pwd'] = $this->joinPath('/',substr($abs_dir, strlen($this->session[$user]['home'])));
            $this->session[$user]['pwd'] = $this->joinPath($this->session[$user]['pwd'],'/');
            $this->log("CHDIR: $old_dir -> $cdir");
            return $this->session[$user]['pwd'];
    }
    /**
     * 获取全路径
     * @param $user
     * @param $file
     * @return string
     */
    public function fillDirName($user, $file){
        if (substr($file, 0, 1) != "/"){
            $file = '/'.$file;
            $file = $this->joinPath($this->getUserDir( $user), $file);
        }
        $file = $this->joinPath($this->session[$user]['home'],$file);
        return $file;
    }
    /**
     * 获取用户路径
     * @param unknown $user
     */
    public function getUserDir($user){
        return $this->session[$user]['pwd'];
    }
    /**
     * 获取用户的当前文件系统绝对路径，非chroot路径
     * @param $user
     * @return string
     */
    public function getAbsDir($user){
        $rdir = $this->joinPath($this->session[$user]['home'],$this->session[$user]['pwd']);
        return $rdir;
    }
    /**
     * 路径连接
     * @param string $path1
     * @param string $path2
     * @return string
     */
    public function joinPath($path1,$path2){
        $path1 = rtrim($path1,'/');
        $path2 = trim($path2,'/');
        return $path1.'/'.$path2;
    }
    /**
     * IP判断
     * @param string $ip
     * @return boolean
     */
    public function isIPAddress($ip){
        if (!is_numeric($ip[0]) || $ip[0] < 1 || $ip[0] > 254) {
            return false;
        } elseif (!is_numeric($ip[1]) || $ip[1] < 0 || $ip[1] > 254) {
            return false;
        } elseif (!is_numeric($ip[2]) || $ip[2] < 0 || $ip[2] > 254) {
            return false;
        } elseif (!is_numeric($ip[3]) || $ip[3] < 1 || $ip[3] > 254) {
            return false;
        } elseif (!is_numeric($ip[4]) || $ip[4] < 1 || $ip[4] > 500) {
            return false;
        } elseif (!is_numeric($ip[5]) || $ip[5] < 1 || $ip[5] > 500) {
            return false;
        } else {
            return true;
        }
    }
    /**
     * 获取pasv端口
     * @return number
     */
    public function getPasvPort(){
        $min = is_int($this->pasv_port_range[0])?$this->pasv_port_range[0]:55000;
        $max = is_int($this->pasv_port_range[1])?$this->pasv_port_range[1]:60000;
        $max = $max <= 65535 ? $max : 65535;
        $loop = 0;
        $port = 0;
        while($loop < 10){
            $port = mt_rand($min, $max);
            if($this->isAvailablePasvPort($port)){
                break;
            }
            $loop++;
        }
        return $port;
    }
    public function pushPasvPort($port){
        $shm_data = $this->shm->read();
        if($shm_data !== false){
            if(isset($shm_data['pasv_port'])){
                array_push($shm_data['pasv_port'], $port);
            }else{
                $shm_data['pasv_port'] = array($port);
            }
            $this->shm->write($shm_data);
            $this->log('Push pasv port: '.implode(',', $shm_data['pasv_port']));
            return true;
        }
        return false;
    }
    public function popPasvPort($port){
        $shm_data = $this->shm->read();
        if($shm_data !== false){
            if(isset($shm_data['pasv_port'])){
                $tmp = array();
                foreach ($shm_data['pasv_port'] as $p){
                    if($p != $port){
                        $tmp[] = $p;
                    }
                }
                $shm_data['pasv_port'] = $tmp;
            }
            $this->shm->write($shm_data);
            $this->log('Pop pasv port: '.implode(',', $shm_data['pasv_port']));
            return true;
        }
        return false;
    }
    public function isAvailablePasvPort($port){
        $shm_data = $this->shm->read();
        if($shm_data !== false){
            if(isset($shm_data['pasv_port'])){
                return !in_array($port, $shm_data['pasv_port']);
            }
            return true;
        }
        return false;
    }
    /**
     * 获取当前数据链接tcp个数
     */
    public function getDataConnections(){
        $shm_data = $this->shm->read();
        if($shm_data !== false){
            if(isset($shm_data['pasv_port'])){
                return count($shm_data['pasv_port']);
            }
        }
        return 0;
    }
    /**
     * 关闭数据传输socket
     * @param $user
     * @return bool
     */
    public function closeUserSock($user){
        $peer = stream_socket_get_name($this->session[$user]['sock'], false);
        list($ip,$port) = explode(':', $peer);
        //释放端口占用
        $this->popPasvPort($port);
        fclose($this->session[$user]['sock']);
        $this->session[$user]['sock'] = 0;
        return true;
    }
    /**
     * @param $user
     * @return resource
     */
    public function getUserSock($user){
        //被动模式
        if ($this->session[$user]['pasv'] == true){
            if (empty($this->session[$user]['sock'])){
                $addr = stream_socket_get_name($this->session[$user]['serv_sock'], false);
                list($ip, $port) = explode(':', $addr);
                $sock = stream_socket_accept($this->session[$user]['serv_sock'], 5);
                if ($sock){
                    $peer = stream_socket_get_name($sock, true);
                    $this->log("Accept: success client is $peer.");
                    $this->session[$user]['sock'] = $sock;
                    //关闭server socket
                    fclose($this->session[$user]['serv_sock']);
                }else{
                    $this->log("Accept: failed.");
                    //释放端口
                    $this->popPasvPort($port);
                    return false;
                }
            }
        }
        return $this->session[$user]['sock'];
    }
    /*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++
     + FTP Command
     +++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
    //==================
    //RFC959
    //==================
    /**
    * 登录用户名
    * @param $fd
    * @param $data
    */
    public function cmd_USER($fd, $data){
        if (preg_match("/^([a-z0-9.@]+)$/", $data)){
            $user = strtolower($data);
            $this->connection[$fd]['user'] = $user;
            $this->send($fd, "331 User $user OK. Password required");
        }else{
            $this->send($fd, "530 Login authentication failed");
        }
    }
    /**
     * 登录密码
     * @param $fd
     * @param $data
     */
    public function cmd_PASS($fd, $data){
        $user = $this->connection[$fd]['user'];
        $pass = $data;
        $info = $this->getConnectionInfo($fd);
        $ip = $info['remote_ip'];
        //判断登陆失败次数
        if($this->user->isAttemptLimit($this->shm, $user, $ip)){
            $this->send($fd, "530 Login authentication failed: Too many login attempts. Blocked in 10 minutes.");
            return;
        }
        if ($this->user->checkUser($user, $pass, $ip)){
            $dir = "/";
            $this->session[$user]['pwd'] = $dir;
            //ftp根目录
            $this->session[$user]['home'] = $this->user->getHomeDir($user);
            if(empty($this->session[$user]['home']) || !is_dir($this->session[$user]['home'])){
                $this->send($fd, "530 Login authentication failed: `home` path error.");
            }else{
                $this->connection[$fd]['login'] = true;
                //在线用户
                $shm_data = $this->user->addOnline($this->shm, $this->server, $user, $fd, $ip);
                $this->log('SHM: '.json_encode($shm_data) );
                $this->send($fd, "230 OK. Current restricted directory is " . $dir);
                $this->log('User '.$user .' has login successfully! IP: '.$ip,'warn');
            }
        }else{
            $this->user->addAttempt($this->shm, $user, $ip);
            $this->log('User '.$user .' login fail! IP: '.$ip,'warn');
            $this->send($fd, "530 Login authentication failed: check your pass or ip allow rules.");
        }
    }
    /**
     * 更改当前目录
     * @param $fd
     * @param $data
     */
    public function cmd_CWD($fd, $data){
        $user = $this->getUser($fd);
        if (($dir = $this->setUserDir($user, $data)) != false){
            $this->send($fd, "250 OK. Current directory is " . $dir);
        }else{
            $this->send($fd, "550 Can't change directory to " . $data . ": No such file or directory");
        }
    }
    /**
     * 返回上级目录
     * @param $fd
     * @param $data
     */
    public function cmd_CDUP($fd, $data){
        $data = '..';
        $this->cmd_CWD($fd, $data);
    }
    /**
     * 退出服务器
     * @param $fd
     * @param $data
     */
    public function cmd_QUIT($fd, $data){
        $this->send($fd,"221 Goodbye.");
        unset($this->connection[$fd]);
    }
    /**
     * 获取当前目录
     * @param $fd
     * @param $data
     */
    public function cmd_PWD($fd, $data){
        $user = $this->getUser($fd);
        $this->send($fd, "257 \"" . $this->getUserDir($user) . "\" is your current location");
    }
    /**
     * 下载文件
     * @param $fd
     * @param $data
     */
    public function cmd_RETR($fd, $data){
        $user = $this->getUser($fd);
        $ftpsock = $this->getUserSock($user);
        if (!$ftpsock){
            $this->send($fd, "425 Connection Error");
            return;
        }
        if (($file = $this->getFile($user, $data)) != false){
            if($this->user->isReadable($user, $file)){
                $this->send($fd, "150 Connecting to client");
                if ($fp = fopen($file, "rb")){
                    //断点续传
                    if(isset($this->session[$user]['rest_offset'])){
                        if(!fseek($fp, $this->session[$user]['rest_offset'])){
                            $this->log("RETR at offset ".ftell($fp));
                        }else{
                            $this->log("RETR at offset ".ftell($fp).' fail.');
                        }
                        unset($this->session[$user]['rest_offset']);
                    }
                    while (!feof($fp)){
                        $cont = fread($fp, 8192);
                        if (!fwrite($ftpsock, $cont)) break;
                    }
                    if (fclose($fp) and $this->closeUserSock($user)){
                        $this->send($fd, "226 File successfully transferred");
                        $this->log($user."\tGET:".$file,'info');
                    }else{
                        $this->send($fd, "550 Error during file-transfer");
                    }
                }else{
                    $this->send($fd, "550 Can't open " . $data . ": Permission denied");
                }
            }else{
                $this->send($fd, "550 You're unauthorized: Permission denied");
            }
        }else{
            $this->send($fd, "550 Can't open " . $data . ": No such file or directory");
        }
    }
    /**
     * 上传文件
     * @param $fd
     * @param $data
     */
    public function cmd_STOR($fd, $data){
        $user = $this->getUser($fd);
        $ftpsock = $this->getUserSock($user);
        if (!$ftpsock){
            $this->send($fd, "425 Connection Error");
            return;
        }
        $file = $this->fillDirName($user, $data);
        $isExist = false;
        if(file_exists($file))$isExist = true;
        if((!$isExist && $this->user->isWritable($user, $file)) ||
            ($isExist && $this->user->isAppendable($user, $file))){
                if($isExist){
                    $fp = fopen($file, "rb+");
                    $this->log("OPEN for STOR.");
                }else{
                    $fp = fopen($file, 'wb');
                    $this->log("CREATE for STOR.");
                }
                if (!$fp){
                    $this->send($fd, "553 Can't open that file: Permission denied");
                }else{
                    //断点续传，需要Append权限
                    if(isset($this->session[$user]['rest_offset'])){
                        if(!fseek($fp, $this->session[$user]['rest_offset'])){
                            $this->log("STOR at offset ".ftell($fp));
                        }else{
                            $this->log("STOR at offset ".ftell($fp).' fail.');
                        }
                        unset($this->session[$user]['rest_offset']);
                    }
                    $this->send($fd, "150 Connecting to client");
                    while (!feof($ftpsock)){
                        $cont = fread($ftpsock, 8192);
                        if (!$cont) break;
                        if (!fwrite($fp, $cont)) break;
                    }
                    touch($file);//设定文件的访问和修改时间
                    if (fclose($fp) and $this->closeUserSock($user)){
                        $this->send($fd, "226 File successfully transferred");
                        $this->log($user."\tPUT: $file",'info');
                    }else{
                        $this->send($fd, "550 Error during file-transfer");
                    }
                }
        }else{
            $this->send($fd, "550 You're unauthorized: Permission denied");
            $this->closeUserSock($user);
        }
    }
    /**
     * 文件追加
     * @param $fd
     * @param $data
     */
    public function cmd_APPE($fd,$data){
        $user = $this->getUser($fd);
        $ftpsock = $this->getUserSock($user);
        if (!$ftpsock){
            $this->send($fd, "425 Connection Error");
            return;
        }
        $file = $this->fillDirName($user, $data);
        $isExist = false;
        if(file_exists($file))$isExist = true;
        if((!$isExist && $this->user->isWritable($user, $file)) ||
            ($isExist && $this->user->isAppendable($user, $file))){
                $fp = fopen($file, "rb+");
                if (!$fp){
                    $this->send($fd, "553 Can't open that file: Permission denied");
                }else{
                    //断点续传，需要Append权限
                    if(isset($this->session[$user]['rest_offset'])){
                        if(!fseek($fp, $this->session[$user]['rest_offset'])){
                            $this->log("APPE at offset ".ftell($fp));
                        }else{
                            $this->log("APPE at offset ".ftell($fp).' fail.');
                        }
                        unset($this->session[$user]['rest_offset']);
                    }
                    $this->send($fd, "150 Connecting to client");
                    while (!feof($ftpsock)){
                        $cont = fread($ftpsock, 8192);
                        if (!$cont) break;
                        if (!fwrite($fp, $cont)) break;
                    }
                    touch($file);//设定文件的访问和修改时间
                    if (fclose($fp) and $this->closeUserSock($user)){
                        $this->send($fd, "226 File successfully transferred");
                        $this->log($user."\tAPPE: $file",'info');
                    }else{
                        $this->send($fd, "550 Error during file-transfer");
                    }
                }
        }else{
            $this->send($fd, "550 You're unauthorized: Permission denied");
            $this->closeUserSock($user);
        }
    }
    /**
     * 文件重命名,源文件
     * @param $fd
     * @param $data
     */
    public function cmd_RNFR($fd, $data){
        $user = $this->getUser($fd);
        $file = $this->fillDirName($user, $data);
        if (file_exists($file) || is_dir($file)){
            $this->session[$user]['rename'] = $file;
            $this->send($fd, "350 RNFR accepted - file exists, ready for destination");
        }else{
            $this->send($fd, "550 Sorry, but that '$data' doesn't exist");
        }
    }
    /**
     * 文件重命名,目标文件
     * @param $fd
     * @param $data
     */
    public function cmd_RNTO($fd, $data){
        $user = $this->getUser($fd);
        $old_file = $this->session[$user]['rename'];
        $new_file = $this->fillDirName($user, $data);
        $isDir = false;
        if(is_dir($old_file)){
            $isDir = true;
            $old_file = $this->joinPath($old_file, '/');
        }
        if((!$isDir && $this->user->isRenamable($user, $old_file)) ||
            ($isDir && $this->user->isFolderRenamable($user, $old_file))){
                if (empty($old_file) or !is_dir(dirname($new_file))){
                    $this->send($fd, "451 Rename/move failure: No such file or directory");
                }elseif (rename($old_file, $new_file)){
                    $this->send($fd, "250 File successfully renamed or moved");
                    $this->log($user."\tRENAME: $old_file to $new_file",'warn');
                }else{
                    $this->send($fd, "451 Rename/move failure: Operation not permitted");
                }
        }else{
            $this->send($fd, "550 You're unauthorized: Permission denied");
        }
        unset($this->session[$user]['rename']);
    }
    /**
     * 删除文件
     * @param $fd
     * @param $data
     */
    public function cmd_DELE($fd, $data){
        $user = $this->getUser($fd);
        $file = $this->fillDirName($user, $data);
        if($this->user->isDeletable($user, $file)){
            if (!file_exists($file)){
                $this->send($fd, "550 Could not delete " . $data . ": No such file or directory");
            }
            elseif (unlink($file)){
                $this->send($fd, "250 Deleted " . $data);
                $this->log($user."\tDEL: $file",'warn');
            }else{
                $this->send($fd, "550 Could not delete " . $data . ": Permission denied");
            }
        }else{
            $this->send($fd, "550 You're unauthorized: Permission denied");
        }
    }
    /**
     * 创建目录
     * @param $fd
     * @param $data
     */
    public function cmd_MKD($fd, $data){
        $user = $this->getUser($fd);
        $path = '';
        if($data[0] == '/'){
            $path = $this->joinPath($this->session[$user]['home'],$data);
        }else{
            $path = $this->joinPath($this->getAbsDir($user),$data);
        }
        $path = $this->joinPath($path, '/');
        if($this->user->isFolderCreatable($user, $path)){
            if (!is_dir(dirname($path))){
                $this->send($fd, "550 Can't create directory: No such file or directory");
            }elseif(file_exists($path)){
                $this->send($fd, "550 Can't create directory: File exists");
            }else{
                if (mkdir($path)){
                    $this->send($fd, "257 \"" . $data . "\" : The directory was successfully created");
                    $this->log($user."\tMKDIR: $path",'info');
                }else{
                    $this->send($fd, "550 Can't create directory: Permission denied");
                }
            }
        }else{
            $this->send($fd, "550 You're unauthorized: Permission denied");
        }
    }
    /**
     * 删除目录
     * @param $fd
     * @param $data
     */
    public function cmd_RMD($fd, $data){
        $user = $this->getUser($fd);
        $dir = '';
        if($data[0] == '/'){
            $dir = $this->joinPath($this->session[$user]['home'], $data);
        }else{
            $dir = $this->fillDirName($user, $data);
        }
        $dir = $this->joinPath($dir, '/');
        if($this->user->isFolderDeletable($user, $dir)){
            if (is_dir(dirname($dir)) and is_dir($dir)){
                if (count(glob($dir . "/*"))){
                    $this->send($fd, "550 Can't remove directory: Directory not empty");
                }elseif (rmdir($dir)){
                    $this->send($fd, "250 The directory was successfully removed");
                    $this->log($user."\tRMDIR: $dir",'warn');
                }else{
                    $this->send($fd, "550 Can't remove directory: Operation not permitted");
                }
            }elseif (is_dir(dirname($dir)) and file_exists($dir)){
                $this->send($fd, "550 Can't remove directory: Not a directory");
            }else{
                $this->send($fd, "550 Can't create directory: No such file or directory");
            }
        }else{
            $this->send($fd, "550 You're unauthorized: Permission denied");
        }
    }
    /**
     * 得到服务器类型
     * @param $fd
     * @param $data
     */
    public function cmd_SYST($fd, $data){
        $this->send($fd, "215 UNIX Type: L8");
    }
    /**
     * 权限控制
     * @param $fd
     * @param $data
     */
    public function cmd_SITE($fd, $data){
        if (substr($data, 0, 6) == "CHMOD "){
            $user = $this->getUser($fd);
            $chmod = explode(" ", $data, 3);
            $file = $this->fillDirName($user, $chmod[2]);
            if($this->user->isWritable($user, $file)){
                if (chmod($file, octdec($chmod[1]))){
                    $this->send($fd, "200 Permissions changed on {$chmod[2]}");
                    $this->log($user."\tCHMOD: $file to {$chmod[1]}",'info');
                }else{
                    $this->send($fd, "550 Could not change perms on " . $chmod[2] . ": Permission denied");
                }
            }else{
                $this->send($fd, "550 You're unauthorized: Permission denied");
            }
        }else{
            $this->send($fd, "500 Unknown Command");
        }
    }
    /**
     * 更改传输类型
     * @param $fd
     * @param $data
     */
    public function cmd_TYPE($fd, $data){
        switch ($data){
            case "A":
                $type = "ASCII";
                break;
            case "I":
                $type = "8-bit binary";
                break;
        }
        $this->send($fd, "200 TYPE is now " . $type);
    }
    /**
     * 遍历目录
     * @param $fd
     * @param $data
     */
    public function cmd_LIST($fd, $data){
        $user = $this->getUser($fd);
        $ftpsock = $this->getUserSock($user);
        if (!$ftpsock){
            $this->send($fd, "425 Connection Error");
            return;
        }
        $path = $this->joinPath($this->getAbsDir($user),'/');
        $this->send($fd, "150 Opening ASCII mode data connection for file list");
        $filelist = $this->getFileList($user, $path, true);
        fwrite($ftpsock, $filelist);
        $this->send($fd, "226 Transfer complete.");
        $this->closeUserSock($user);
    }
    /**
     * 建立数据传输通
     * @param $fd
     * @param $data
     */
    // 不使用主动模式
    // public function cmd_PORT($fd, $data){
    // $user = $this->getUser($fd);
    // $port = explode(",", $data);
    // if (count($port) != 6){
    // $this->send($fd, "501 Syntax error in IP address");
    // }else{
    // if (!$this->isIPAddress($port)){
    // $this->send($fd, "501 Syntax error in IP address");
    // return;
    // }
    // $ip = $port[0] . "." . $port[1] . "." . $port[2] . "." . $port[3];
    // $port = hexdec(dechex($port[4]) . dechex($port[5]));
    // if ($port < 1024){
    // $this->send($fd, "501 Sorry, but I won't connect to ports < 1024");
    // }elseif ($port > 65000){
    // $this->send($fd, "501 Sorry, but I won't connect to ports > 65000");
    // }else{
    // $ftpsock = fsockopen($ip, $port);
    // if ($ftpsock){
    // $this->session[$user]['sock'] = $ftpsock;
    // $this->session[$user]['pasv'] = false;
    // $this->send($fd, "200 PORT command successful");
    // }else{
    // $this->send($fd, "501 Connection failed");
    // }
    // }
    // }
    // }
    /**
     * 被动模式
     * @param unknown $fd
     * @param unknown $data
     */
    public function cmd_PASV($fd, $data){
        $user = $this->getUser($fd);
        $ssl = false;
        $pasv_port = $this->getPasvPort();
        if($this->connection[$fd]['ssl'] === true){
            $ssl = true;
            $context = stream_context_create();
            // local_cert must be in PEM format
            stream_context_set_option($context, 'ssl', 'local_cert', $this->setting['ssl_cert_file']);
            // Path to local private key file
            stream_context_set_option($context, 'ssl', 'local_pk', $this->setting['ssl_key_file']);
            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
            stream_context_set_option($context, 'ssl', 'passphrase', '');
            // Create the server socket
            $sock = stream_socket_server('ssl://0.0.0.0:'.$pasv_port, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        }else{
            $sock = stream_socket_server('tcp://0.0.0.0:'.$pasv_port, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        }
        if ($sock){
            $addr = stream_socket_get_name($sock, false);
            list($ip, $port) = explode(':', $addr);
            $ipArr = swoole_get_local_ip();
            foreach($ipArr as $nic => $addr){
                $ip = $addr;
            }
            $this->log("ServerSock: $ip:$port");
            $ip = str_replace('.', ',', $ip);
            $this->send($fd, "227 Entering Passive Mode ({$ip},".(intval($port) >> 8 & 0xff).",".(intval($port) & 0xff)."). ".$port." ".($ssl?'ssl':''));
            $this->session[$user]['serv_sock'] = $sock;
            $this->session[$user]['pasv'] = true;
            $this->pushPasvPort($port);
        }else{
            fclose($sock);
            $this->send($fd, "500 failed to create data socket: ".$errstr);
        }
    }
    public function cmd_NOOP($fd,$data){
        $this->send($fd, "200 OK");
    }
    //==================
    //RFC2228
    //==================
    public function cmd_PBSZ($fd,$data){
        $this->send($fd, '200 Command okay.');
    }
    public function cmd_PROT($fd,$data){
        if(trim($data) == 'P'){
            $this->connection[$fd]['ssl'] = true;
            $this->send($fd, '200 Set Private level on data connection.');
        }elseif(trim($data) == 'C'){
            $this->connection[$fd]['ssl'] = false;
            $this->send($fd, '200 Set Clear level on data connection.');
        }else{
            $this->send($fd, '504 Command not implemented for that parameter.');
        }
    }
    //==================
    //RFC2389
    //==================
    public function cmd_FEAT($fd,$data){
        $this->send($fd, '211-Features supported');
        $this->send($fd, 'MDTM');
        $this->send($fd, 'SIZE');
        $this->send($fd, 'SITE CHMOD');
        $this->send($fd, 'REST STREAM');
        $this->send($fd, 'MLSD Type*;Size*;Modify*;UNIX.mode*;');
        $this->send($fd, 'PBSZ');
        $this->send($fd, 'PROT');
        $this->send($fd, '211 End');
    }
    //关闭utf8对中文文件名有影响
    public function cmd_OPTS($fd,$data){
        $this->send($fd, '502 Command not implemented.');
    }
    //==================
    //RFC3659
    //==================
    /**
    * 获取文件修改时间
    * @param unknown $fd
    * @param unknown $data
    */
    public function cmd_MDTM($fd,$data){
        $user = $this->getUser($fd);
        if (($file = $this->getFile($user, $data)) != false){
            $this->send($fd, '213 '.date('YmdHis.u',filemtime($file)));
        }else{
            $this->send($fd, '550 No file named "'.$data.'"');
        }
    }
    /**
     * 获取文件大小
     * @param $fd
     * @param $data
     */
    public function cmd_SIZE($fd,$data){
        $user = $this->getUser($fd);
        if (($file = $this->getFile($user, $data)) != false){
            $this->send($fd, '213 '.filesize($file));
        }else{
            $this->send($fd, '550 No file named "'.$data.'"');
        }
    }
    /**
     * 获取文件列表
     * @param unknown $fd
     * @param unknown $data
     */
    public function cmd_MLSD($fd,$data){
        $user = $this->getUser($fd);
        $ftpsock = $this->getUserSock($user);
        if (!$ftpsock){
            $this->send($fd, "425 Connection Error");
            return;
        }
        $path = $this->joinPath($this->getAbsDir($user),'/');
        $this->send($fd, "150 Opening ASCII mode data connection for file list");
        $filelist = $this->getFileList($user, $path, true,'mlsd');
        fwrite($ftpsock, $filelist);
        $this->send($fd, "226 Transfer complete.");
        $this->closeUserSock($user);
    }
    /**
     * 设置文件offset
     * @param unknown $fd
     * @param unknown $data
     */
    public function cmd_REST($fd,$data){
        $user = $this->getUser($fd);
        $data= preg_replace('/[^0-9]/', '', $data);
        if($data != ''){
            $this->session[$user]['rest_offset'] = $data;
            $this->send($fd, '350 Restarting at '.$data.'. Send STOR or RETR');
        }else{
            $this->send($fd, '500 Syntax error, offset unrecognized.');
        }
    }
    /**
     * 获取文件hash值
     * @param unknown $fd
     * @param unknown $data
     */
    public function cmd_HASH($fd,$data){
        $user = $this->getUser($fd);
        $ftpsock = $this->getUserSock($user);
        if (($file = $this->getFile($user, $data)) != false){
            if(is_file($file)){
                $algo = 'sha512';
                $this->send($fd, "200 ".hash_file($algo, $file));
            }else{
                $this->send($fd, "550 Can't open " . $data . ": No such file。");
            }
        }else{
            $this->send($fd, "550 Can't open " . $data . ": No such file。");
        }
    }
    /**
     * 控制台命令
     * @param unknown $fd
     * @param unknown $data
     */
    public function cmd_CONSOLE($fd,$data){
        $group = $this->user->getUserProfile($this->getUser($fd));
        $group = $group['group'];
        if($group != 'admin'){
            $this->send($fd, "550 You're unauthorized: Permission denied");
            return;
        }
        $data = explode('||', $data);
        $cmd = strtoupper($data[0]);
        switch ($cmd){
            case 'USER-ONLINE':
                $shm_data = $this->shm->read();
                $list = array();
                if($shm_data !== false){
                    if(isset($shm_data['online'])){
                        $list = $shm_data['online'];
                    }
                }
                $this->send($fd, '200 '.json_encode($list));
                break;
                //Format: user-add||{"user":"","pass":"","home":"","expired":"","active":boolean,"group":"","description":"","email":""}
            case 'USER-ADD':
                if(isset($data[1])){
                    $json = json_decode(trim($data[1]),true);
                    $user = isset($json['user'])?$json['user']:'';
                    $pass = isset($json['pass'])?$json['pass']:'';
                    $home = isset($json['home'])?$json['home']:'';
                    $expired = isset($json['expired'])?$json['expired']:'1999-01-01';
                    $active = isset($json['active'])?$json['active']:false;
                    $group = isset($json['group'])?$json['group']:'';
                    $description = isset($json['description'])?$json['description']:'';
                    $email = isset($json['email'])?$json['email']:'';
                    if($this->user->addUser($user,$pass,$home,$expired,$active,$group,$description,$email)){
                        $this->user->save();
                        $this->user->reload();
                        $this->send($fd, '200 User "'.$user.'" added.');
                    }else{
                        $this->send($fd, '550 Add fail!');
                    }
                }else{
                    $this->send($fd, '500 Syntax error: USER-ADD||{"user":"","pass":"","home":"","expired":"","active":boolean,"group":"","description":""}');
                }
                break;
                //Format: user-set-profile||{"user":"","profile":[]}
            case 'USER-SET-PROFILE':
                if(isset($data[1])){
                    $json = json_decode(trim($data[1]),true);
                    $user = isset($json['user'])?$json['user']:'';
                    $profile = isset($json['profile'])?$json['profile']:array();
                    if($this->user->setUserProfile($user, $profile)){
                        $this->user->save();
                        $this->user->reload();
                        $this->send($fd, '200 User "'.$user.'" profile changed.');
                    }else{
                        $this->send($fd, '550 Set profile fail!');
                    }
                }else{
                    $this->send($fd, '500 Syntax error: USER-SET-PROFILE||{"user":"","profile":[]}');
                }
                break;
                //Format: user-get-profile||{"user":""}
            case 'USER-GET-PROFILE':
                if(isset($data[1])){
                    $json = json_decode(trim($data[1]),true);
                    $user = isset($json['user'])?$json['user']:'';
                    $this->user->reload();
                    if($profile = $this->user->getUserProfile($user)){
                        $this->send($fd, '200 '.json_encode($profile));
                    }else{
                        $this->send($fd, '550 Get profile fail!');
                    }
                }else{
                    $this->send($fd, '500 Syntax error: USER-GET-PROFILE||{"user":""}');
                }
                break;
                //Format: user-delete||{"user":""}
            case 'USER-DELETE':
                if(isset($data[1])){
                    $json = json_decode(trim($data[1]),true);
                    $user = isset($json['user'])?$json['user']:'';
                    if($this->user->delUser($user)){
                        $this->user->save();
                        $this->user->reload();
                        $this->send($fd, '200 User '.$user.' deleted.');
                    }else{
                        $this->send($fd, '550 Delete user fail!');
                    }
                }else{
                    $this->send($fd, '500 Syntax error: USER-DELETE||{"user":""}');
                }
                break;
            case 'USER-LIST':
                $this->user->reload();
                $list = $this->user->getUserList();
                $this->send($fd, '200 '.json_encode($list));
                break;
                //Format: group-add||{"group":"","home":""}
            case 'GROUP-ADD':
                if(isset($data[1])){
                    $json = json_decode(trim($data[1]),true);
                    $group = isset($json['group'])?$json['group']:'';
                    $home = isset($json['home'])?$json['home']:'';
                    if($this->user->addGroup($group, $home)){
                        $this->user->save();
                        $this->user->reload();
                        $this->send($fd, '200 Group "'.$group.'" added.');
                    }else{
                        $this->send($fd, '550 Add group fail!');
                    }
                }else{
                    $this->send($fd, '500 Syntax error: GROUP-ADD||{"group":"","home":""}');
                }
                break;
                //Format: group-set-profile||{"group":"","profile":[]}
            case 'GROUP-SET-PROFILE':
                if(isset($data[1])){
                    $json = json_decode(trim($data[1]),true);
                    $group = isset($json['group'])?$json['group']:'';
                    $profile = isset($json['profile'])?$json['profile']:array();
                    if($this->user->setGroupProfile($group, $profile)){
                        $this->user->save();
                        $this->user->reload();
                        $this->send($fd, '200 Group "'.$group.'" profile changed.');
                    }else{
                        $this->send($fd, '550 Set profile fail!');
                    }
                }else{
                    $this->send($fd, '500 Syntax error: GROUP-SET-PROFILE||{"group":"","profile":[]}');
                }
                break;
                //Format: group-get-profile||{"group":""}
            case 'GROUP-GET-PROFILE':
                if(isset($data[1])){
                    $json = json_decode(trim($data[1]),true);
                    $group = isset($json['group'])?$json['group']:'';
                    $this->user->reload();
                    if($profile = $this->user->getGroupProfile($group)){
                        $this->send($fd, '200 '.json_encode($profile));
                    }else{
                        $this->send($fd, '550 Get profile fail!');
                    }
                }else{
                    $this->send($fd, '500 Syntax error: GROUP-GET-PROFILE||{"group":""}');
                }
                break;
                //Format: group-delete||{"group":""}
            case 'GROUP-DELETE':
                if(isset($data[1])){
                    $json = json_decode(trim($data[1]),true);
                    $group = isset($json['group'])?$json['group']:'';
                    if($this->user->delGroup($group)){
                        $this->user->save();
                        $this->user->reload();
                        $this->send($fd, '200 Group '.$group.' deleted.');
                    }else{
                        $this->send($fd, '550 Delete group fail!');
                    }
                }else{
                    $this->send($fd, '500 Syntax error: GROUP-DELETE||{"group":""}');
                }
                break;
            case 'GROUP-LIST':
                $this->user->reload();
                $list = $this->user->getGroupList();
                $this->send($fd, '200 '.json_encode($list));
                break;
                //获取组用户列表
                //Format: group-user-list||{"group":""}
            case 'GROUP-USER-LIST':
                if(isset($data[1])){
                    $json = json_decode(trim($data[1]),true);
                    $group = isset($json['group'])?$json['group']:'';
                    $this->user->reload();
                    $this->send($fd, '200 '.json_encode($this->user->getUserListOfGroup($group)));
                }else{
                    $this->send($fd, '500 Syntax error: GROUP-USER-LIST||{"group":""}');
                }
                break;
                // 获取磁盘空间
                //Format: disk-total||{"path":""}
            case 'DISK-TOTAL':
                if(isset($data[1])){
                    $json = json_decode(trim($data[1]),true);
                    $path = isset($json['path'])?$json['path']:'';
                    $size = 0;
                    if($path){
                        $size = disk_total_space($path);
                    }
                    $this->send($fd, '200 '.$size);
                }else{
                    $this->send($fd, '500 Syntax error: DISK-TOTAL||{"path":""}');
                }
                break;
                // 获取磁盘空间
                //Format: disk-total||{"path":""}
            case 'DISK-FREE':
                if(isset($data[1])){
                    $json = json_decode(trim($data[1]),true);
                    $path = isset($json['path'])?$json['path']:'';
                    $size = 0;
                    if($path){
                        $size = disk_free_space($path);
                    }
                    $this->send($fd, '200 '.$size);
                }else{
                    $this->send($fd, '500 Syntax error: DISK-FREE||{"path":""}');
                }
                break;
            case 'HELP':
                $list = 'USER-ONLINE USER-ADD USER-SET-PROFILE USER-GET-PROFILE USER-DELETE USER-LIST GROUP-ADD GROUP-SET-PROFILE GROUP-GET-PROFILE GROUP-DELETE GROUP-LIST GROUP-USER-LIST DISK-TOTAL DISK-FREE';
                $this->send($fd, '200 '.$list);
                break;
            default:
                $this->send($fd, '500 Syntax error.');
        }
    }
}