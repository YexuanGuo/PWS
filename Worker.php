<?php
/**
 * Created by PhpStorm.
 * User: guoyexuan
 * Date: 2017/12/1
 * Time: 下午3:45
 */


class Worker
{
    //worker进程数
    protected static $_worker_count = 3;
    //Master进程ID
    protected static $_master_pid;
    //Worker进程
    protected static $_worker_pids = array();
    //统计信息
    protected static $_statis_info = array();
    //workerId
    public $_worker_id = 0;
    //监听列表
    protected static $_socketList = array();


    public function __construct()
    {
        //获取主进程ID
        self::$_master_pid = posix_getpid();
        //设置主进程名称
        self::set_process_title(sprintf('PWS Master Process [%s]',self::$_master_pid));
    }


    public function run()
    {
        //安装信号
        self::install_signal();
        //创建监听套接字
        self::create_sockets_listen();
        //fork一些worker执行任务
        for($i=1;$i<=self::$_worker_count;$i++)
        {
            $this->fork_one_worker($i);
        }
        //监控worker
        $this->monitor_workers();
    }
    //安装相关信号
    protected function install_signal()
    {
        //stop
        pcntl_signal(SIGINT,array($this,'signal_handler'),SIGINT);
        //status
        pcntl_signal(SIGUSR2,array($this,'signal_handler'),false);
    }
    //信号处理函数
    protected function signal_handler($signal)
    {

        switch($signal)
        {
            //Ctrl+C
            case SIGINT:
                echo "stop signal!"."\n";
                $this->stop_all_worker();
                break;
            //Status
            case SIGUSR2:
                echo "worker status";
                break;
        }
    }


    protected function fork_one_worker($worker_name)
    {
        // 触发alarm信号处理
        pcntl_signal_dispatch();

        $pid = pcntl_fork();
        if($pid > 0)
        {
            //记录master进程id
            self::$_worker_pids[$worker_name] = $pid;
        }
        //子进程
        else if($pid === 0)
        {
            //屏蔽alarm信号
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, SIG_IGN);
            pcntl_signal_dispatch();
            //给进程设置一个名字
            self::set_process_title(sprintf('PWS Worker Process [%s]',self::$_master_pid));
            //当前workerID
            $this->worker_pid = posix_getpid();
            //初始化进程ID为0,以便于统计任务处理次数
            self::$_statis_info[getmypid()] = 0;
            //进程开始任务
            $this->serve();
        }
        //出错退出
        else
        {
            exit("Fork Err");
        }
    }

    //创建监听套接字
    public function create_sockets_listen()
    {
        //循环创建sockets监听,TODOLoop

        self::$_socketList['demo_master'] = stream_socket_server("tcp://0.0.0.0:8011",$error_no,$erros_msg);

    }

    //让woker开始服务
    public function serve()
    {
        while(true)
        {
            $conn = @stream_socket_accept(self::$_socketList['demo_master']);
            if($conn)
            {
                //统计每个进程接收了多少次请求
                self::$_statis_info[getmypid()] ++;
                file_put_contents('task_count.log',getmypid().'====>'.self::$_statis_info[getmypid()].PHP_EOL,FILE_APPEND);
                $_SERVER = array();
                $this->decode(fgets($conn));
                fwrite($conn,$this->encode(print_R($_SERVER)));
                fclose($conn);
            }
        }
    }

    public function decode($info)
    {
        global $_SERVER;
        list($header,) = explode("\r\n\r\n",$info);
        //将请求头变为数组
        $header = explode("\r\n",$header);
        list($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL']) = explode(' ', $header[0]);
        $_SERVER['QUERY_STRING'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    }
    //http协议加密
    public function encode($str)
    {
        $content = "HTTP/1.1 200 OK\r\nServer: PWS/1.0.0\r\nContent-Type: text/html;charset=utf-8\r\nContent-Length: " . strlen($str   )."\r\n\r\n{$str}";
        return $content;
    }


    public function monitor_workers()
    {
        while(true)
        {
            //如果有信号进来，尝试触发信号处理函数
            pcntl_signal_dispatch();
            $pid = pcntl_wait($status, WUNTRACED | WNOHANG);
            //$pid = pcntl_waitpid(-1, $status, WUNTRACED | WNOHANG);
            if($pid > 0)
            {
                //如果不是正常退出,是被kill杀的
                if($status !== 0)
                {
                    echo "Kill worker {$pid} exit status:{$status}\n";
                }
            }
            if($pid == -1)
            {
                //worker进程接收到master信号退出会到这里来
                echo "Master Stop! Worker pid : {$pid} exit! Status:{$status}\n";
                exit(0);
            }
        }
    }

    /**
     * 强制停止所有worker
     */
    public function stop_all_worker()
    {
        if(self::$_master_pid === posix_getpid())
        {
            //循环停止所有Worker
            foreach (self::$_worker_pids as $_worker_id)
            {
                //循环发送worker终止信号
                posix_kill($_worker_id,SIGINT);
            }
        }
        else
        {
            exit(0);
        }
    }

    /**
     * @param $title
     * 设置进程名称
     */
    protected function set_process_title($title)
    {
        if (!empty($title))
        {
            // 需要扩展
            if(extension_loaded('proctitle') && function_exists('setproctitle'))
            {
                @setproctitle($title);
            }
            // >=php 5.5
            elseif (function_exists('cli_set_process_title'))
            {
                cli_set_process_title($title);
            }
        }
    }
}

$worker = new Worker();
$worker->run();