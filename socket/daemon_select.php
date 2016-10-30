<?php
/**
 * Created by PhpStorm.
 * User: kingyqs
 * Date: 2016/10/29
 * Time: 21:37
 * Usage：使用 select/poll 的同步模型：属于同步非阻塞 IO 模型，支持的并发连接数小于1024，性能最好
 */
namespace DaemonSelect;

class DaemonSelect
{

    private static $serverIp;           //服务器ip
    private static $serverPort;         //端口号
    private static $serverSocket;       //服务端socket

    private static $timeOut;            //select超时时间
    private static $maxCons;            //最大连接数

    private static $connections = array();  //所有的客户端连接
    private static $readFds     = array();  //所有发送数据的客户端
    private static $writeFds    = array();  //所有写的客户端
    private static $exceptFds   = array();  //错误异常


    public function __construct($serverIp='0.0.0.0', $serverPort = 10000, $timeOut = 60, $maxCons = 1024)
    {
        self::$serverIp     = $serverIp;
        self::$serverPort   = $serverPort;
        self::$timeOut      = $timeOut;
        self::$maxCons      = $maxCons;

        //创建守护进程
        self::runDaemon();
        //创建服务器
        self::runSocket();
    }

    /**
     * 创建守护进程
     */
    public static function runDaemon()
    {
        if(!extension_loaded('pcntl')){
            exit("pcntl module does not installl！");
        }

        $pid = pcntl_fork();

        if($pid < 0){
            exit('pcntl_fork error!');
        }elseif($pid > 0){
            exit(0);
        }
    }

    public static function runSocket()
    {

    }


}