<?php

class Log
{
    public $debug;
    protected $config = array(
        'log_path' => '/tmp/logs/',
        'log_app'  => 'default',
        'product'  => 'default',
        'level'    => 3,
        'log_rpc'   => 500,
        'path'     => array(
            'FATAL' => 'php/php',
            'RPC'   => 'rpc/rpc',
            'SYS'   => 'sys/sys',
        ),
        'subffix'  => array(
            'WARNING' => '.wf',
        ),
        'area' => 1
    );
    protected $infoLog;
    protected $logPath;
    protected $open = true;
    protected $levels = array('FATAL' => 1, 'ERROR' => 2, 'INFO' => 3, 'RPC' => 4, 'WARNING' => 5, 'NOTICE' => 6, 'DEBUG' => 7, 'SYS' => 8);
    protected $dateFmt = 'Y-m-d H:i:s';
    private $logBase = array('level', 'logid', 'timestamp', 'millisecond', 'date', 'product', 'module', 'uri', 'service_id', 'instance_id', 'xhop', 'human_time', 'msg');
    private $marker;

    public function __construct()
    {
        $this->logPath = $this->config['log_path'];
        $this->init();
        set_error_handler(array($this, 'errorHandler'));
        register_shutdown_function(array($this, 'fatalHandler'));
        $this->requestStart(false);
    }

    public function turn($turn = true)
    {
        $this->open = $turn;
    }

    public function setConfig($name = 'default')
    {
        $config = require_once './log_config.php';
        $this->config = $config[$name] ?: $this->config;
        $this->logPath = $this->config['log_path'];
    }

    public function init($reset = false)
    {
        static $infoLog;
        if (!empty($infoLog) && is_array($infoLog) && false === $reset) {
            $this->infoLog = $infoLog;
            return $infoLog;
        }
        $this->infoLog['level']       = 'INFO';
        $this->infoLog['logid']       = self::genLogID($reset);
        $this->infoLog['timestamp']   = time();
        $this->infoLog['millisecond'] = intval(microtime(true) * 1000);
        $this->infoLog['date']        = date($this->dateFmt, $this->infoLog['timestamp']);
        $this->infoLog['product']     = isset($this->config['product']) ? $this->config['product'] : 'unknow';
        $this->infoLog['module']      = '';
        $this->infoLog['errno']       = '';
        $this->infoLog['msg']         = '';
        $this->infoLog['cookie']      = isset($_COOKIE) ? $_COOKIE : '';
        $this->infoLog['method']      = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
        $this->infoLog['uri']         = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : $this->getUri();
        $this->infoLog['caller_ip']   = self::getClientIp();
        $this->infoLog['host_ip']     = self::getServerHost();
        $this->infoLog['user_agent']  = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        $this->infoLog['service_id']  = $this->infoLog['product'];
        $this->infoLog['instance_id'] = $this->infoLog['host_ip'];
        $this->infoLog['x_hop']       = '';
        $this->infoLog['human_time']  = date('Y-m-d H:i:s,', $this->infoLog['timestamp']) . ($this->infoLog['millisecond'] % 1000);
        $path   =   explode('/',$this->infoLog['uri']);
        if( isset($path[0]) ){
            $this->infoLog['module']   =   $path[0];
        }

        $infoLog = $this->infoLog;
        return $infoLog;
    }

    public function requestStart($force = true)
    {
        if (true === $force || empty($this->marker['request_start'])) {
            $this->mark('request_start');
        }
    }

    public function rpcStart()
    {
        $this->mark('rpc_start');
    }


    public function errorHandler($errno, $message, $file, $line)
    {
        $warning = array(
            'errno'  => $errno,
            'errmsg' => $message,
            'file'   => $file,
            'line'   => $line,
        );
        $this->error($warning);
    }

    public function fatalHandler($msg = '')
    {
        $app = $this->config['log_app'];
        $message = $this->mergeLog($this->logBase, $this->init());

        $message['status_code'] = 0;
        $message['request_url'] = '';
        $message['uri_path']    = $message['uri'];
        if (error_get_last() && $this->config['level'] >= $this->levels['FATAL']) {
            $errorMsg = error_get_last();
            $message['error'] = substr($errorMsg['message'], 0, strpos($errorMsg['message'], 'Stack trace:'));
            $message['trace'] = $this->getTrace();
            $this->write('FATAL', $message, $app);
        } elseif (!empty($msg)) {
            if (is_array($msg)) {
                $message = array_merge($message, $msg);
            } else {
                $message['error'] = $msg;
            }
            $message['trace'] = $this->getTrace();
            $this->write('FATAL', $message, $app);
        }
    }

    public function info($module)
    {
        $this->infoLog['module'] = $module;
        $message['request_start'] = isset($this->marker['request_start']) ? $this->marker['request_start'] * 1000 : 0;
        $this->infoLog['elapsed_time']   = $this->elapsedTime('request_start', 'request_end') * 1000;
        return $this->write('INFO', $this->infoLog, $module);
    }

    public function addLog($key, $value)
    {
        if (isset($this->infoLog[$key]) && is_array($this->infoLog[$key]) && is_array($value)) {
            $this->infoLog[$key] = array_merge($this->infoLog[$key], $value);
        } else {
            $this->infoLog[$key] = $value;
        }
    }

    public function rpc($rpcData, $module)
    {
        $message = $this->mergeLog($this->logBase, $this->init());
        $message = array_merge($message, $rpcData);
        $message['module']   = $module;
        $message['rpc_start'] = isset($this->marker['rpc_start']) ? $this->marker['rpc_start'] * 1000 : 0;
        $message['elapsed_time'] = $this->elapsedTime('rpc_start', 'rpc_end') * 1000;

        return $this->write('RPC', $message, $module);
    }

    public function error($warning)
    {
        $module = $this->config['log_app'];
        $message = $this->mergeLog($this->logBase, $this->init());
        $message['module'] = $this->config['log_app'];
        $message['trace']  = $this->getTrace();
        $message = array_merge($message, $warning);

        return $this->write('ERROR', $message, $module);
    }

    private function getTrace()
    {
        $trace  = debug_backtrace();
        $need   = array(
            'object_name',
            'type',
            'class',
            'function',
            'file',
            'line',
        );
        $returnTrace = array();
        foreach ($trace as $key => $value) {
            $value['object_name'] = isset($value['object']) ? get_class($value['object']) : '';
            $message              = $this->mergeLog($need, $value);
            $returnTrace[]       = $message;
        }
        return $returnTrace;
    }

    public static function genLogID($reset = false)
    {
        static $logid;
        if (!empty($logid) && false === $reset) {
            return $logid;
        }
        if (!empty($_SERVER['HTTP_X_YMT_LOGID']) && intval(trim($_SERVER['HTTP_X_YMT_LOGID'])) !== 0) {
            $logid = trim($_SERVER['HTTP_X_YMT_LOGID']);
        } elseif (isset($_REQUEST['logid']) && intval($_REQUEST['logid']) !== 0) {
            $logid = trim($_REQUEST['logid']);
        } else {
            $ip        = intval(self::getServerHost());
            $timestamp = explode(' ', microtime());
            $item1     = sprintf('%04d', $timestamp[1] % 3600);
            $item2     = sprintf('%04d', intval(($timestamp[0] * 1000000) % 1000));
            $item3     = sprintf('%04d', mt_rand(0, 987654321) % 1000);
            $item4     = sprintf('%04d', crc32($ip * (mt_rand(0, 987654321) % 1000)) % 10000);
            $logid     = ($item1 . $item2 . $item3 . $item4 . $item1 . $item3);
        }
        return $logid;
    }

    private static function getClientIp()
    {
        $ip = array_key_exists('HTTP_X_REAL_IP', $_SERVER) ? $_SERVER['HTTP_X_REAL_IP'] : (
        array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : (
        array_key_exists('REMOTE_ADDR', $_SERVER) ? $_SERVER['REMOTE_ADDR'] :
            '0.0.0.0'));
        return $ip;
    }

    private static function getServerHost()
    {
        return isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
    }

    private function mergeLog($items, $array)
    {
        $return = array();
        is_array($items) or $items = array($items);
        foreach ($items as $item) {
            $return[$item] = array_key_exists($item, $array) ? $array[$item] : '';
        }
        return $return;
    }
    
    private function write($level, $msg, $module = '')
    {
        $msg['module'] = $msg['module'] ?: $module;
        $level = strtoupper($level);

        $isLog = true;
        if (!$this->open){
            $isLog = false;
        }else{
            if ( isset($this->levels[$level]) && $this->config['level'] < $this->levels[$level] ){
                $isLog = false;
            }elseif ( $level == 'RPC' && isset($this->config['log_rpc']) && intval($msg['elapsed_time']) < $this->config['log_rpc'] ){
                $isLog = false;
            }
        }
        if (!$isLog){
            return false;
        }
        $msg['level'] = $level = empty($level) ? $msg['level'] : $level;
        $subffix = isset($this->config['subffix'][$level]) ? $this->config['subffix'][$level] : '.log';
        $host     = trim(gethostname());
        $hostname = 'UNKNOWNHOST';
        if (!empty($host)) {
            $hosts    = explode('.', $host);
            $hostname = !empty($hosts[0]) ? $hosts[0] : $hostname;
        }

        $level = strtolower($level);
        $fileBase = rtrim($this->logPath, '/') . '/' . $this->config['log_app'] . '/' . $level;
        $filePath = $fileBase . '/' . $level . '.' . $hostname  .  date('YmdH');
        $symlink = $fileBase . '/' . $level . $subffix;
        if (!file_exists($filePath)) {
            @mkdir($filePath, 0777, true);
            @unlink($fileBase);
            @symlink($filePath, $symlink);
            @chmod($filePath, 0777);
        }
        if (is_dir($filePath)) {
            $area = isset($this->config['area']) && $this->config['area'] > 0 ? intval($this->config['area']) : 10;

            file_put_contents($filePath . "/" . rand(0, $area - 1), json_encode($msg) . "\n", FILE_APPEND);
        } else {
            file_put_contents($filePath, json_encode($msg) . "\n", FILE_APPEND);
        }
        return true;
    }

    private function mark($name)
    {
        $this->marker[$name] = microtime(true);
    }

    private function elapsedTime($point1 = '', $point2 = '', $decimals = 4)
    {
        if (!isset($this->marker[$point1])) {
            return 0;
        }
        $this->marker[$point2] = microtime(true);
        return number_format($this->marker[$point2] - $this->marker[$point1], $decimals);
    }

    protected function getUri()
    {
        if (!isset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'])) {
            return '';
        }

        $uri   = parse_url($_SERVER['REQUEST_URI']);
        $query = isset($uri['query']) ? $uri['query'] : '';
        $uri   = isset($uri['path']) ? $uri['path'] : '';

        if (isset($_SERVER['SCRIPT_NAME'][0])) {
            if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
                $uri = (string) substr($uri, strlen($_SERVER['SCRIPT_NAME']));
            } elseif (strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0) {
                $uri = (string) substr($uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
            }
        }

        // This section ensures that even on servers that require the URI to be in the query string (Nginx) a correct
        // URI is found, and also fixes the QUERY_STRING server var and $_GET array.
        if (trim($uri, '/') === '' && strncmp($query, '/', 1) === 0) {
            $query                   = explode('?', $query, 2);
            $uri                     = $query[0];
            $_SERVER['QUERY_STRING'] = isset($query[1]) ? $query[1] : '';
        } else {
            $_SERVER['QUERY_STRING'] = $query;
        }

        parse_str($_SERVER['QUERY_STRING'], $_GET);

        if ($uri === '/' or $uri === '') {
            return '/';
        }

        // Do some final cleaning of the URI and return it
        return '/' . $uri;
    }
}
