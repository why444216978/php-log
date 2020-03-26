<?php
//日志配置
return [
    'default' => [
        'log_path' => '/tmp/logs/',
        'log_app'  => 'default',
        'product'  => 'default',
        'level'    => 5,
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
    ],
    'why' => [
        'log_path' => '/data/logs/',
        'log_app'  => 'why',
        'product'  => 'why',
        'level'    => 5,
        'log_rpc'   => 500,
        'path'     => array(
            'FATAL' => 'php/php',
            'RPC'   => 'rpc/rpc',
            'SYS'   => 'sys/sys',
            'INFO'  => 'info/info',
        ),
        'subffix'  => array(
            'WARNING' => '.wf',
        ),
        'area' =>1
    ]
];