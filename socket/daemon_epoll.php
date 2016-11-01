<?php
/**
 * Created by PhpStorm.
 * User: kingyqs
 * Date: 2016/10/31
 * Time: 20:15
 * 使用 epoll/kqueue 的异步模型：属于异步阻塞/非阻塞 IO 模型,支持连接数笔select要多，适合高并发短连接类型
 */
class DaemonEpoll
{

    private static $serverIp;               //tcp服务器ip
    private static $serverPort;             //tcp服务器端口
    private static $serverSocket;           //tcp服务器socket

    private static $connections = array();  //缓存所有的客服端
    private static $buffers = array();

    public function __construct($serverIp = '0.0.0.0', $serverPort = 10000)
    {
        self::$serverIp = $serverIp;
        self::$serverPort = $serverPort;

        self::runSocket();
    }

    /**
     * 创建tcp服务器
     */
    public static function runSocket()
    {
        //创建一个tcp服务器
        $tcp_str = 'tcp://'.self::$serverIp.':'.self::$serverPort;
        self::$serverSocket = stream_socket_server($tcp_str, $error_no, $error_str);
        if(!self::$serverSocket){
            exit('stream_socket_server error!');
        }
        //设置tcp服务器为非阻塞
        stream_set_blocking(self::$serverSocket, 0);

        //创建一个事件管理器event_base
        $base = event_base_new();
        //创建一个事件event
        $event = event_new();
        //配置事件的监控对象，回调函数，监控的事件
        event_set($event, self::$serverSocket, EV_READ|EV_PERSIST, array(__CLASS__, 'ev_accept'), $base);
        //将事件添加到事件管理器
        event_base_set($event, $base);
        //激活事件
        event_add($event);
        //启动事件管理器
        event_base_loop($base);

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

    /**
     * event事件回调函数，用于循环接收客户端连接
     *
     * @param $server_socket
     * @param $flag
     * @param $base
     */
    private static function ev_accept($server_socket, $flag, $base)
    {
        //接收客户端连接
        $client_socket = stream_socket_accept($server_socket);
        //将客服端连接设置为非阻塞
        stream_set_blocking($client_socket, 0);
        //设置socket连接标号
        $client_id = (int)$client_socket;
        //创建一个buffer_event事件
        $buffer_event = event_buffer_new($client_socket, array(__CLASS__, 'ev_read'), array(__CLASS__, 'ev_write'), array(__CLASS__, 'ev_error'), $client_id);
        //将事件添加到事件管理器
        event_buffer_base_set($buffer_event, $base);
        //设置超时间,超过时间后，连接就中断，客户端不能给服务端发送数据
        event_buffer_timeout_set($buffer_event, 60, 60);
        //给每一个事件生成水印
        event_buffer_watermark_set($buffer_event, EV_READ|EV_PERSIST, 0, 0xffffff);
        //设置事件优先级
        event_base_priority_init($buffer_event, 10);
        //激活事件
        event_buffer_enable($buffer_event, EV_READ|EV_PERSIST);

        //缓存所有客户端连接
        self::$connections[$client_id] = $client_socket;
        self::$buffers[$client_id] = $buffer_event;

    }

    /**
     * buffer_event事件读回调函数
     *
     * @param $buffer_event
     * @param $client_id
     */
    private static function ev_read($buffer_event, $client_id)
    {
        //接收完后才退出循环
        while ($data = event_buffer_read($buffer_event, 1024)) {
            //群发到其他客户端
            foreach(self::$buffers as $client){
                $res = event_buffer_write($client, $data, strlen($data));
            }
        }
    }


    /**
     * buffer_event写回调函数
     *
     * @param $buffer_event
     * @param $client_id
     */
    private static function ev_write($buffer_event, $client_id)
    {
        echo "write_cb begin!".PHP_EOL;
        //var_dump($client_id);
        echo "write_cb end!".PHP_EOL;
    }


    /**
     * buffer_event事件错误处理回调函数
     *
     * @param $buffer_event
     * @param $error
     * @param $client_id
     */
    private static function ev_error($buffer_event, $error, $client_id)
    {
        //17close
        //65 timeout
        //var_dump($error);
        //todo log
        //event_buffer_disable(self::$connections[$client_id], EV_READ | EV_WRITE);
        //event_buffer_free(self::$connections[$client_id]);
        //fclose(self::$connections[$client_id]);
        //unset(self::$connections[$client_id]);
    }


}

//实例化
//new DaemonEpoll();