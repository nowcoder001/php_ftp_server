<?php
/**
 * Created by Zend Studio
 * User: scjtqs
 * Email: jose@scjtqs.cn
 * Date: 2017年6月3日
 * Time: 上午10:53:37
 */
class ShareMemory{
    private $mode = 0644;
    private $shm_key;
    private $shm_size;
    /**
     * 构造函数
     */
    public function __construct(){
        $key = 'F';
        $size = 1024*1024;
        $this->shm_key = ftok(__FILE__,$key);
        $this->shm_size = $size + 1;
    }
    /**
     * 读取内存数组
     * @return array|boolean
     */
    public function read(){
        if(($shm_id = shmop_open($this->shm_key,'c',$this->mode,$this->shm_size)) !== false){
            $str = shmop_read($shm_id,1,$this->shm_size-1);
            shmop_close($shm_id);
            if(($i = strpos($str,"\0")) !== false)$str = substr($str,0,$i);
            if($str){
                return json_decode($str,true);
            }else{
                return array();
            }
        }
        return false;
    }
    /**
     * 写入数组到内存
     * @param array $arr
     * @return int|boolean
     */
    public function write($arr){
        if(!is_array($arr))return false;
        $str = json_encode($arr)."\0";
        if(strlen($str) > $this->shm_size) return false;
        if(($shm_id = shmop_open($this->shm_key,'c',$this->mode,$this->shm_size)) !== false){
            $count = shmop_write($shm_id,$str,1);
            shmop_close($shm_id);
            return $count;
        }
        return false;
    }
    /**
     * 删除内存块，下次使用时将重新开辟内存块
     * @return boolean
     */
    public function delete(){
        if(($shm_id = shmop_open($this->shm_key,'c',$this->mode,$this->shm_size)) !== false){
            $result = shmop_delete($shm_id);
            shmop_close($shm_id);
            return $result;
        }
        return false;
    }
}