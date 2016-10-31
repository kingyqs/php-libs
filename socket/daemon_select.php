<?php
/**
 * Created by PhpStorm.
 * User: kingyqs
 * Date: 2016/10/29
 * Time: 21:37
 * Usage：使用 select/poll 的同步模型：属于同步非阻塞 IO 模型，支持的并发连接数小于1024，性能最好
 *  socket_select 接受三个套接字数组，分别检查数组中的套接字是否处于可以操作的状态（返回时只保留可操作的套接字）
    使用最多的是 $read，因此以读为例
    在套接字数组 $read 中最初应保有一个服务端监听套接字
    每当该套接字可读时，就表示有一个用户发起了连接。此时你需要对该连接创建一个套接字，并加入到 $read 数组中
    当然，并不只是服务端监听的套接字会变成可读的，用户套接字也会变成可读的，此时你就可以读取用户发来的数据了
    socket_select 只在套接字数组发生了变化时才返回。也就是说，一旦执行到 socket_select 的下一条语句，则必有一个套接字是需要你操作的
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
        //检测socket扩展是否安装
        if(!extension_loaded('sockets')){
            exit('sockets module does not install!');
        }

        //创建socket
        self::$serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!self::$serverSocket){
            exit('socket_create error!');
        }

        //绑定服务器ip和端口号
        socket_bind(self::$serverSocket, self::$serverIp, self::$serverPort);

        //监听端口
        socket_listen(self::$serverSocket, 128);

        //设置socket为非阻塞
        socket_set_nonblock(self::$serverSocket);

        //处理连接的客户端
        while(true){
            //将服务器socket放入监听的句柄，简化一下处理
            self::$readFds = array_merge(self::$connections, array(self::$serverSocket));
            self::$writeFds = array();

            //使用select/poll模型，支持多个并发客户端连接
            if(socket_select(self::$readFds, self::$writeFds, self::$exceptFds, self::$timeOut)){

                if(in_array(self::$serverSocket, self::$readFds)){
                    $client_socket = socket_accept(self::$serverSocket);
                    $i = (int)$client_socket;

                    if(count(self::$readFds) > self::$maxCons){
                        $reject = 'Server is Full, Please Try Again Later!';
                        socket_write($client_socket, $reject, strlen($reject));
                        socket_shutdown($client_socket);
                        socket_close($client_socket);
                    }else{
                        //添加到连接数组里面
                        self::$connections[$i] = $client_socket;
                        self::$writeFds[$i] = $client_socket;

                        //回复客户端连接成功
                        $success = "Welcome To The Server!\n";
                        socket_write(self::$serverSocket, $success, strlen($success));
                    }

                    //删除服务端socket
                    $key = array_search(self::$serverSocket, self::$readFds);
                    unset(self::$readFds[$key]);
                }

                //读数据
                self::readSocket();

                //写
                self::writeSocket();

            }
        }

    }


    /**
     * 循环发送数据的客户端，读取数据
     */
    public static function readSocket()
    {
        foreach(self::$readFds as $client_socket){
            //todo 数据没有读取完的缺陷
            $data = socket_read($client_socket, 2084);
            $msg =  "Get Client Data >> " . $data . PHP_EOL;
            if($data){
                //echo $msg;
                self::log(trim($msg));
                //todo log insert into mysql
            }

        }

    }


    /**
     * 循环写
     */
    public static function writeSocket()
    {
        //todo
    }


    /**
     * 记录日志
     * @param $msg
     */
    protected static function log($msg)
    {
        $logFile = date('Ymd').'.log';
        $msg = '['.date('Y-m-d H:i:s').']'.json_encode($msg) .PHP_EOL;
        file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
    }


}