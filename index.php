<?php
require_once './Log.php';

function getXhop($xhop = "", $reset = false)
{
    static $_bhop = "";
    static $_hop_num = 0;

    if ($reset) {
        $_bhop = "";
        $_hop_num = 0;
    }

    if (empty($_bhop)) {
        //初始化
        if (empty($xhop)) {
            $header = $_SERVER;
            if (!empty($header['X-Hop'])) {
                $xhop = $header['X-Hop'];
            } else {
                $xhop = "01";
            }

        }
        $_bhop = base_convert($xhop, 16, 2);
    } else {
        $xhop = base_convert(base_convert((1 << $_hop_num), 10, 2) . $_bhop, 2, 16);
        $_hop_num++;
    }
    return strlen($xhop) % 2 == 1 ? '0' . $xhop : $xhop;
}


$log = new Log();
$log->setConfig('why');
$log->addLog('x_hop', getXhop());
$log->addLog('result', ['code' => 0, 'msg' => 'success', 'data' => 'info']);

$log->rpcStart();
sleep(1);
$log->rpc([
    'input'  => ['params' => '123'],
    'output' => ['code' => '0', 'msg' => 'success', 'data' => 'rpc']
], 'test');

$log->info('test');

trigger_error('eflekgen');

function test($a, $b)
{
    echo 1;
}
test(1);