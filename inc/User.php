<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月3日
 * Time: 上午10:52:55
 */
class User{
    const I = 1; // inherit
    const FD = 2; // folder delete
    const FN = 4; // folder rename
    const FC = 8; // folder create
    const FL = 16; // folder list
    const D = 32; // file delete
    const N = 64; // file rename
    const A = 128; // file append
    const W = 256; // file write (upload)
    const R = 512; // file read (download)
    private $hash_salt = '';
    private $user_file;
    private $group_file;
    private $users = array();
    private $groups = array();
    private $file_hash = '';
    public function __construct(){
        $this->user_file = BASE_PATH.'/conf/users';
        $this->group_file = BASE_PATH.'/conf/groups';
        $this->reload();
    }
    /**
     * 返回权限表达式
     * @param int $access
     * @return string
     */
    public static function AC($access){
        $str = '';
        $char = array('R','W','A','N','D','L','C','N','D','I');
        for($i = 0; $i < 10; $i++){
            if($access & pow(2,9-$i))$str.= $char[$i];else $str.= '-';
        }
        return $str;
    }
    /**
     * 加载用户数据
     */
    public function reload(){
        $user_file_hash = md5_file($this->user_file);
        $group_file_hash = md5_file($this->group_file);
        if($this->file_hash != md5($user_file_hash.$group_file_hash)){
            if(($user = file_get_contents($this->user_file)) !== false){
                $this->users = json_decode($user,true);
                if($this->users){
                    //folder排序
                    foreach ($this->users as $user=>$profile){
                        if(isset($profile['folder'])){
                            $this->users[$user]['folder'] = $this->sortFolder($profile['folder']);
                        }
                    }
                }
            }
            if(($group = file_get_contents($this->group_file)) !== false){
                $this->groups = json_decode($group,true);
                if($this->groups){
                    //folder排序
                    foreach ($this->groups as $group=>$profile){
                        if(isset($profile['folder'])){
                            $this->groups[$group]['folder'] = $this->sortFolder($profile['folder']);
                        }
                    }
                }
            }
            $this->file_hash = md5($user_file_hash.$group_file_hash);
        }
    }
    /**
     * 对folder进行排序
     * @return array
     */
    private function sortFolder($folder){
        uasort($folder, function($a,$b){
            return strnatcmp($a['path'], $b['path']);
        });
            $result = array();
            foreach ($folder as $v){
                $result[] = $v;
            }
            return $result;
    }
    /**
     * 保存用户数据
     */
    public function save(){
        file_put_contents($this->user_file, json_encode($this->users),LOCK_EX);
        file_put_contents($this->group_file, json_encode($this->groups),LOCK_EX);
    }
    /**
     * 添加用户
     * @param string $user
     * @param string $pass
     * @param string $home
     * @param string $expired
     * @param boolean $active
     * @param string $group
     * @param string $description
     * @param string $email
     * @return boolean
     */
    public function addUser($user,$pass,$home,$expired,$active=true,$group='',$description='',$email = ''){
        $user = strtolower($user);
        if(isset($this->users[$user]) || empty($user)){
            return false;
        }
        $this->users[$user] = array(
            'pass' => md5($user.$this->hash_salt.$pass),
            'home' => $home,
            'expired' => $expired,
            'active' => $active,
            'group' => $group,
            'description' => $description,
            'email' => $email,
            
        );
        return true;
    }
    /**
     * 设置用户资料
     * @param string $user
     * @param array $profile
     * @return boolean
     */
    public function setUserProfile($user,$profile){
        $user = strtolower($user);
        if(is_array($profile) && isset($this->users[$user])){
            if(isset($profile['pass'])){
                $profile['pass'] = md5($user.$this->hash_salt.$profile['pass']);
            }
            if(isset($profile['active'])){
                if(!is_bool($profile['active'])){
                    $profile['active'] = $profile['active'] == 'true' ? true : false;
                }
            }
            $this->users[$user] = array_merge($this->users[$user],$profile);
            return true;
        }
        return false;
    }
    /**
     * 获取用户资料
     * @param string $user
     * @return multitype:|boolean
     */
    public function getUserProfile($user){
        $user = strtolower($user);
        if(isset($this->users[$user])){
            return $this->users[$user];
        }
        return false;
    }
    /**
     * 删除用户
     * @param string $user
     * @return boolean
     */
    public function delUser($user){
        $user = strtolower($user);
        if(isset($this->users[$user])){
            unset($this->users[$user]);
            return true;
        }
        return false;
    }
    /**
     * 获取用户列表
     * @return array
     */
    public function getUserList(){
        $list = array();
        if($this->users){
            foreach ($this->users as $user=>$profile){
                $list[] = $user;
            }
        }
        sort($list);
        return $list;
    }
    /**
     * 添加组
     * @param string $group
     * @param string $home
     * @return boolean
     */
    public function addGroup($group,$home){
        $group = strtolower($group);
        if(isset($this->groups[$group])){
            return false;
        }
        $this->groups[$group] = array(
            'home' => $home
        );
        return true;
    }
    /**
     * 设置组资料
     * @param string $group
     * @param array $profile
     * @return boolean
     */
    public function setGroupProfile($group,$profile){
        $group = strtolower($group);
        if(is_array($profile) && isset($this->groups[$group])){
            $this->groups[$group] = array_merge($this->groups[$group],$profile);
            return true;
        }
        return false;
    }
    /**
     * 获取组资料
     * @param string $group
     * @return multitype:|boolean
     */
    public function getGroupProfile($group){
        $group = strtolower($group);
        if(isset($this->groups[$group])){
            return $this->groups[$group];
        }
        return false;
    }
    /**
     * 删除组
     * @param string $group
     * @return boolean
     */
    public function delGroup($group){
        $group = strtolower($group);
        if(isset($this->groups[$group])){
            unset($this->groups[$group]);
            foreach ($this->users as $user => $profile){
                if($profile['group'] == $group)
                    $this->users[$user]['group'] = '';
            }
            return true;
        }
        return false;
    }
    /**
     * 获取组列表
     * @return array
     */
    public function getGroupList(){
        $list = array();
        if($this->groups){
            foreach ($this->groups as $group=>$profile){
                $list[] = $group;
            }
        }
        sort($list);
        return $list;
    }
    /**
     * 获取组用户列表
     * @param string $group
     * @return array
     */
    public function getUserListOfGroup($group){
        $list = array();
        if(isset($this->groups[$group]) && $this->users){
            foreach ($this->users as $user=>$profile){
                if(isset($profile['group']) && $profile['group'] == $group){
                    $list[] = $user;
                }
            }
        }
        sort($list);
        return $list;
    }
    /**
     * 用户验证
     * @param string $user
     * @param string $pass
     * @param string $ip
     * @return boolean
     */
    public function checkUser($user,$pass,$ip = ''){
        $this->reload();
        $user = strtolower($user);
        if(isset($this->users[$user])){
            if($this->users[$user]['active'] && time() <= strtotime($this->users[$user]['expired'])
                && $this->users[$user]['pass'] == md5($user.$this->hash_salt.$pass)){
                    if(empty($ip)){
                        return true;
                    }else{
                        //ip验证
                        return $this->checkIP($user, $ip);
                    }
            }else{
                return false;
            }
        }
        return false;
    }
    /**
     * basic auth
     * @param string $base64
     */
    public function checkUserBasicAuth($base64){
        $base64 = trim(str_replace('Basic ', '', $base64));
        $str = base64_decode($base64);
        if($str !== false){
            list($user,$pass) = explode(':', $str,2);
            $this->reload();
            $user = strtolower($user);
            if(isset($this->users[$user])){
                $group = $this->users[$user]['group'];
                if($group == 'admin' && $this->users[$user]['active'] && time() <= strtotime($this->users[$user]['expired'])
                    && $this->users[$user]['pass'] == md5($user.$this->hash_salt.$pass)){
                        return true;
                }else{
                    return false;
                }
            }
        }
        return false;
    }
    /**
     * 用户登录ip验证
     * @param string $user
     * @param string $ip
     *
     * 用户的ip权限继承组的IP权限。
     * 匹配规则：
     * 1.进行组允许列表匹配；
     * 2.如同通过，进行组拒绝列表匹配；
     * 3.进行用户允许匹配
     * 4.如果通过，进行用户拒绝匹配
     *
     */
    public function checkIP($user,$ip){
        $pass = true;
        //先进行组验证
        $group = $this->users[$user]['group'];
        //组允许匹配
        if(isset($this->groups[$group]['ip']['allow'])){
            foreach ($this->groups[$group]['ip']['allow'] as $addr){
                $pattern = '/'.str_replace('*','\d+',str_replace('.', '\.', $addr)).'/';
                if(preg_match($pattern, $ip) && !empty($addr)){
                    $pass = true;
                    break;
                }
            }
        }
        //如果允许通过，进行拒绝匹配
        if($pass){
            if(isset($this->groups[$group]['ip']['deny'])){
                foreach ($this->groups[$group]['ip']['deny'] as $addr){
                    $pattern = '/'.str_replace('*','\d+',str_replace('.', '\.', $addr)).'/';
                    if(preg_match($pattern, $ip) && !empty($addr)){
                        $pass = false;
                        break;
                    }
                }
            }
        }
        if(isset($this->users[$user]['ip']['allow'])){
            foreach ($this->users[$user]['ip']['allow'] as $addr){
                $pattern = '/'.str_replace('*','\d+',str_replace('.', '\.', $addr)).'/';
                if(preg_match($pattern, $ip) && !empty($addr)){
                    $pass = true;
                    break;
                }
            }
        }
        if($pass){
            if(isset($this->users[$user]['ip']['deny'])){
                foreach ($this->users[$user]['ip']['deny'] as $addr){
                    $pattern = '/'.str_replace('*','\d+',str_replace('.', '\.', $addr)).'/';
                    if(preg_match($pattern, $ip) && !empty($addr)){
                        $pass = false;
                        break;
                    }
                }
            }
        }
        echo date('Y-m-d H:i:s')." [debug]\tIP ACCESS:".' '.($pass?'true':'false')."\n";
        return $pass;
    }
    /**
     * 获取用户主目录
     * @param string $user
     * @return string
     */
    public function getHomeDir($user){
        $user = strtolower($user);
        $group = $this->users[$user]['group'];
        $dir = '';
        if($group){
            if(isset($this->groups[$group]['home']))$dir = $this->groups[$group]['home'];
        }
        $dir = !empty($this->users[$user]['home'])?$this->users[$user]['home']:$dir;
        return $dir;
    }
    //文件权限判断
    public function isReadable($user,$path){
        $result = $this->getPathAccess($user, $path);
        if($result['isExactMatch']){
            return $result['access'][0] == 'R';
        }else{
            return $result['access'][0] == 'R' && $result['access'][9] == 'I';
        }
    }
    public function isWritable($user,$path){
        $result = $this->getPathAccess($user, $path);
        if($result['isExactMatch']){
            return $result['access'][1] == 'W';
        }else{
            return $result['access'][1] == 'W' && $result['access'][9] == 'I';
        }
    }
    public function isAppendable($user,$path){
        $result = $this->getPathAccess($user, $path);
        if($result['isExactMatch']){
            return $result['access'][2] == 'A';
        }else{
            return $result['access'][2] == 'A' && $result['access'][9] == 'I';
        }
    }
    public function isRenamable($user,$path){
        $result = $this->getPathAccess($user, $path);
        if($result['isExactMatch']){
            return $result['access'][3] == 'N';
        }else{
            return $result['access'][3] == 'N' && $result['access'][9] == 'I';
        }
    }
    public function isDeletable($user,$path){
        $result = $this->getPathAccess($user, $path);
        if($result['isExactMatch']){
            return $result['access'][4] == 'D';
        }else{
            return $result['access'][4] == 'D' && $result['access'][9] == 'I';
        }
    }
    //目录权限判断
    public function isFolderListable($user,$path){
        $result = $this->getPathAccess($user, $path);
        if($result['isExactMatch']){
            return $result['access'][5] == 'L';
        }else{
            return $result['access'][5] == 'L' && $result['access'][9] == 'I';
        }
    }
    public function isFolderCreatable($user,$path){
        $result = $this->getPathAccess($user, $path);
        if($result['isExactMatch']){
            return $result['access'][6] == 'C';
        }else{
            return $result['access'][6] == 'C' && $result['access'][9] == 'I';
        }
    }
    public function isFolderRenamable($user,$path){
        $result = $this->getPathAccess($user, $path);
        if($result['isExactMatch']){
            return $result['access'][7] == 'N';
        }else{
            return $result['access'][7] == 'N' && $result['access'][9] == 'I';
        }
    }
    public function isFolderDeletable($user,$path){
        $result = $this->getPathAccess($user, $path);
        if($result['isExactMatch']){
            return $result['access'][8] == 'D';
        }else{
            return $result['access'][8] == 'D' && $result['access'][9] == 'I';
        }
    }
    /**
     * 获取目录权限
     * @param string $user
     * @param string $path
     * @return array
     * 进行最长路径匹配
     *
     * 返回：
     * array(
     * 'access'=>目前权限
     * ,'isExactMatch'=>是否精确匹配
     *
     * );
     *
     * 如果精确匹配，则忽略inherit.
     * 否则应判断是否继承父目录的权限，
     * 权限位表：
     * +---+---+---+---+---+---+---+---+---+---+
     * | 0 | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 |
     * +---+---+---+---+---+---+---+---+---+---+
     * | R | W | A | N | D | L | C | N | D | I |
     * +---+---+---+---+---+---+---+---+---+---+
     * | FILE | FOLDER |
     * +-------------------+-------------------+
     */
    public function getPathAccess($user,$path){
        $this->reload();
        $user = strtolower($user);
        $group = $this->users[$user]['group'];
        //去除文件名称
        $path = str_replace(substr(strrchr($path, '/'),1),'',$path);
        $access = self::AC(0);
        $isExactMatch = false;
        if($group){
            if(isset($this->groups[$group]['folder'])){
                foreach ($this->groups[$group]['folder'] as $f){
                    //中文处理
                    $t_path = iconv('UTF-8','GB18030',$f['path']);
                    if(strpos($path, $t_path) === 0){
                        $access = $f['access'];
                        $isExactMatch = ($path == $t_path?true:false);
                    }
                }
            }
        }
        if(isset($this->users[$user]['folder'])){
            foreach ($this->users[$user]['folder'] as $f){
                //中文处理
                $t_path = iconv('UTF-8','GB18030',$f['path']);
                if(strpos($path, $t_path) === 0){
                    $access = $f['access'];
                    $isExactMatch = ($path == $t_path?true:false);
                }
            }
        }
        echo date('Y-m-d H:i:s')." [debug]\tACCESS:$access ".' '.($isExactMatch?'1':'0')." $path\n";
        return array('access'=>$access,'isExactMatch'=>$isExactMatch);
    }
    /**
     * 添加在线用户
     * @param ShareMemory $shm
     * @param swoole_server $serv
     * @param unknown $user
     * @param unknown $fd
     * @param unknown $ip
     * @return Ambigous <multitype:, boolean, mixed, multitype:unknown number multitype:Ambigous <unknown, number> >
     */
    public function addOnline(ShareMemory $shm ,$serv,$user,$fd,$ip){
        $shm_data = $shm->read();
        if($shm_data !== false){
            $shm_data['online'][$user.'-'.$fd] = array('ip'=>$ip,'time'=>time());
            $shm_data['last_login'][] = array('user' => $user,'ip'=>$ip,'time'=>time());
            //清除旧数据
            if(count($shm_data['last_login'])>30)array_shift($shm_data['last_login']);
            $list = array();
            foreach ($shm_data['online'] as $k =>$v){
                $arr = explode('-', $k);
                if($serv->connection_info($arr[1]) !== false){
                    $list[$k] = $v;
                }
            }
            $shm_data['online'] = $list;
            $shm->write($shm_data);
        }
        return $shm_data;
    }
    /**
     * 添加登陆失败记录
     * @param ShareMemory $shm
     * @param unknown $user
     * @param unknown $ip
     * @return Ambigous <number, multitype:, boolean, mixed>
     */
    public function addAttempt(ShareMemory $shm ,$user,$ip){
        $shm_data = $shm->read();
        if($shm_data !== false){
            if(isset($shm_data['login_attempt'][$ip.'||'.$user]['count'])){
                $shm_data['login_attempt'][$ip.'||'.$user]['count'] += 1;
            }else{
                $shm_data['login_attempt'][$ip.'||'.$user]['count'] = 1;
            }
            $shm_data['login_attempt'][$ip.'||'.$user]['time'] = time();
            //清除旧数据
            if(count($shm_data['login_attempt'])>30)array_shift($shm_data['login_attempt']);
            $shm->write($shm_data);
        }
        return $shm_data;
    }
    /**
     * 密码错误上限
     * @param unknown $shm
     * @param unknown $user
     * @param unknown $ip
     * @return boolean
     */
    public function isAttemptLimit(ShareMemory $shm,$user,$ip){
        $shm_data = $shm->read();
        if($shm_data !== false){
            if(isset($shm_data['login_attempt'][$ip.'||'.$user]['count'])){
                if($shm_data['login_attempt'][$ip.'||'.$user]['count'] > 10 &&
                    time() - $shm_data['login_attempt'][$ip.'||'.$user]['time'] < 600){
                        return true;
                }
            }
        }
        return false;
    }
    /**
     * 生成随机密钥
     * @param int $len
     * @return Ambigous <NULL, string>
     */
    public static function genPassword($len){
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz@!#$%*+-";
        $max = strlen($strPol)-1;
        for($i=0;$i<$len;$i++){
            $str.=$strPol[rand(0,$max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }
        return $str;
    }
}