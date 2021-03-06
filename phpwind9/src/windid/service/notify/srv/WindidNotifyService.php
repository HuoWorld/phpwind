<?php

Wind::import('WSRV:notify.dm.WindidNotifyLogDm');
/**
 * 客户端通知服务
 * the last known user to change this file in the repository  <$LastChangedBy: gao.wanggao $>.
 *
 * @author $Author: gao.wanggao $ Foxsee@aliyun.com
 * @copyright ?2003-2103 phpwind.com
 * @license http://www.phpwind.com
 *
 * @version $Id: WindidNotifyService.php 29741 2013-06-28 07:54:24Z gao.wanggao $
 */
class WindidNotifyService
{
    protected function getOperation($method)
    {
        $config = include Wind::getRealPath('WSRV:base.WindidNotifyConf.php', true);
        $operation = '';
        foreach ($config as $k => $v) {
            if ($v['method'] == $method) {
                $operation = $k;
                break;
            }
        }

        return $operation;
    }

    /**
     * 写入通知信息.
     *
     * @param int   $method
     * @param array $data
     * @param bool  $iswindid
     */
    public function send($method, $data, $appid = 0)
    {
        if (! $operation = $this->getOperation($method)) {
            return false;
        }

        $time = Pw::getTime();
        if (! $nid = $this->_getNotifyDs()->addNotify($appid, $operation, serialize($data), $time)) {
            return false;
        }
        $apps = $this->_getAppDs()->getList();
        $dms = [];
        foreach ($apps as $val) {
            if (! $val['isnotify'] || $val['id'] == $appid) {
                continue;
            }
            $dm = new WindidNotifyLogDm();
            $dm->setAppid($val['id'])->setNid($nid);
            $dms[] = $dm;
        }
        $this->_getNotifyLogDs()->multiAddLog($dms);
        register_shutdown_function([&$this, 'shutdownSend'], $nid);

        return true;
    }

    public function shutdownSend($nid)
    {
        $url = Wekit::app('windid')->url->base.'/index.php?m=queue';

        $client = new \GuzzleHttp\Client();
        $client->request('POST', $url, [
            'form_params' => [
                'nid' => $nid,
            ],
        ]);

        return true;
    }

    /**
     * 同步登录登出.
     *
     * @param string $notify
     * @param int    $uid
     */
    public function syn($method, $uid, $appid = 0)
    {
        $operation = $this->getOperation($method);
        $time = Pw::getTime();
        $data = [];
        $apps = $this->_getAppDs()->getList();

        $syn = false;
        foreach ($apps as $val) {
            if (! $val['issyn'] && $val['id'] == $appid) {
                $syn = true;
                break;
            }
            if (! $val['issyn'] || $val['id'] == $appid) {
                continue;
            }
            $array = [
                'windidkey' => WindidUtility::appKey($val['id'], $time, $val['secretkey'], ['uid' => $uid], []),
                'operation' => $operation,
                'uid'       => $uid,
                'clientid'  => $val['id'],
                'time'      => $time,
            ];
            $data[] = WindidUtility::buildClientUrl($val['siteurl'], $val['apifile']).http_build_query($array);
        }

        return $syn ? [] : $data;
    }

    private function _getAppDs()
    {
        return Wekit::load('WSRV:app.WindidApp');
    }

    private function _getNotifyDs()
    {
        return Wekit::load('WSRV:notify.WindidNotify');
    }

    private function _getNotifyLogDs()
    {
        return Wekit::load('WSRV:notify.WindidNotifyLog');
    }
}
