<?php
/**
 * Created by PhpStorm.
 * User: guoyexuan
 * Date: 2017/11/28
 * Time: 下午2:04
 */

class Worker
{
    //master进程ID
    protected static $_master_id   = 0;
    //worker进程ID
    protected static $_worker_pids = array();
    //进程开始时间
    public $_worker_start_time;
    // worker id，worker进程从1开始，0被master进程所使用
    public $_worker_id = 0;
    //worker 进程pid 0是Master
    public $_worker_pid = 0;
    //worker Title
    public $_worker_title = '';
    //worker 进程数量
    public $_worker_count = 5;

    // server统计信息
    protected static $serverStatusInfo = array(
        'start_time' => 0,
        'err_info'=>array(),
    );


    public function __construct()
    {
        self::$_master_id = posix_getpid();
        echo "Master_ID:".self::$_master_id.PHP_EOL;
        // 设置进程名称
        self::set_process_title('PHPServer:master process:'.self::$_master_id);
    }

    public function run()
    {
        //安装信号
        $this->install_signal();

        //记录worker启动时间
        $this->_worker_start_time = microtime(true);
        //MasterWorker为0
        $this->_worker_id  = 0;
        //记录workerPID
        $this->_worker_pid = posix_getpid();

        //从1开始0是MasterWorker,循环fork子进程
        for($i = 1; $i <= 5; $i++)
        {
            echo $i.PHP_EOL;
            $this->fork_worker($i);
        }


        //监控worker
        $this->monitor_workers_status();

    }

    /**
     * 安装信号处理
     */
    protected function install_signal()
    {
        //stop
        pcntl_signal(SIGINT,array($this,'signal_handler'),false);
        //reload
        pcntl_signal(SIGUSR1,array($this,'signal_handler'),false);

    }

    /**
     * @param $signal
     * 信号处理函数
     */
    public function signal_handler($signal)
    {
        switch ($signal)
        {
            case SIGINT:
                echo 'stop';
                break;
            case SIGUSR1:
                echo 'reload';
                break;
        }
    }

    /**
     * @param $worker_id
     * fork_worker
     */
    public function fork_worker($worker_id)
    {
        $pid = pcntl_fork();

        if($pid > 0)
        {
            //主进程记录子进程PID
            self::$_worker_pids[$worker_id] = $pid;
        }
        else if($pid === 0)
        {
            //屏蔽alarm信号
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, SIG_IGN);
            pcntl_signal_dispatch();

            //记录进程启动时间
            $this->_worker_start_time = microtime(true);
            //记录进程ID
            $this->_worker_id = $worker_id;
            //记录进程PID
            $this->_worker_pid= posix_getpid();
            //设置进程名称
            self::set_process_title('PHPServer:worker process :'.$worker_id);

            exit(0);
        }
        else
        {
            //出错退出
            exit('Fork Error!');
        }
    }

    /**
     * 监控Worker状态
     */
    public function monitor_workers_status()
    {
        // 由于SIGCHLD信号可能重叠导致信号丢失，所以这里要循环获取所有退出的进程id
        //$pid = pcntl_waitpid(-1, $status, WUNTRACED | WNOHANG);
        while(1)
        {
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED);
            //子进程退出信号
            if($pid > 0)
            {
                //非正常退出,KILL掉的。
                if($status !== 0)
                {
                    echo "PID{$pid} exit,status:{$status}\n";
                }
            }
            else    //其他位置信号
            {
                // worker进程接受到master进行信号退出的，会到这里来
                echo ("Undefile PID:{$pid},status:{$status}\n");
                exit(0);
            }
        }
    }



    /**
     * @param $title
     * 测试php7不支持setTitle
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
                @cli_set_process_title($title);
            }
        }
    }
}

$start = new Worker();
$start->run();