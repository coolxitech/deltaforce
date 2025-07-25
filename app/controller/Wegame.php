<?php

namespace app\controller;

use Exception;
use app\model\Access;
use app\utils\Response;
use app\utils\Time;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\BadResponseException;
use think\facade\Request;
use think\response\Json;

class Wegame
{
    protected Client $client;
    protected CookieJar $cookie;

    public function __construct()
    {
        $this->cookie = new CookieJar();
        $this->client = new Client([
            'cookies' => $this->cookie,
            'allow_redirects' => false,
            'verify' => false,
            'version' => 2.0,
        ]);
    }

    public function getQrSig()
    {
        if (!$this->getLoginToken()) {
            throw new Exception('LoginToken获取失败', -1);
        }
        $response = $this->client->request('GET', 'https://xui.ptlogin2.qq.com/ssl/ptqrshow', [
            'query' => [
                'appid' => 1600001063,
                'e' => 2,
                'l' => 'M',
                's' => 3,
                'd' => 72,
                'v' => 4,
                't' => 0.6142752744667854,
                'daid' => 733,
                'pt_3rd_aid' => 0,
                'u1' => 'https://www.wegame.com.cn/login/callback.html?t=qq&c=0&a=0',
            ],
        ]);
        if ($response->getStatusCode() != 200) {
            return Response::json(-1, '获取失败');
        }

        $result = $response->getBody()->getContents();
        $sig = $this->getCookieValue('qrsig');
        $cookies = $this->cookie->toArray();
        $cookie = [];
        foreach ($cookies as $value) {
            $cookie[$value['Name']] = $value['Value'];
        }
        return Response::json(0, '获取成功', [
            'qrSig' => $sig,
            'image' => base64_encode($result),
            'token' => $this->getQrToken($sig),
            'loginSig' => $this->getCookieValue('pt_login_sig'),
            'cookie' => $cookie,
        ]);
    }

    public function getLoginToken(): bool
    {
        $response = $this->client->request('GET', 'https://xui.ptlogin2.qq.com/cgi-bin/xlogin', [
            'query' => [
                's_url' => 'https://www.wegame.com.cn/login/callback.html?t=qq&c=0&a=0',
                'appid' => 1600001063,
                'daid' => 733,
                'style' => 20,
                'pt_no_auth' => 0,
                'target' => 'self',
                'hide_close_icon' => 1,
                'hide_border' => 1,
            ],
        ]);
        return $response->getStatusCode() === 200;
    }

    public function getAction(string $qrToken, string $qrSig, string $loginSig): Json
    {
        try {
            $cookie = Request::param('cookie');
            if (!$cookie) {
                return Response::json(-1, '缺少cookie参数');
            }
            $cookies = json_decode($cookie, true);
            $cookies['qrsig'] = $qrSig;
            $this->cookie = $this->cookie::fromArray($cookies, '.ptlogin2.qq.com');
            $response = $this->client->request('GET', 'https://xui.ptlogin2.qq.com/ssl/ptqrlogin', [
                'query' => [
                    'u1' => 'https://www.wegame.com.cn/login/callback.html?t=qq&c=0&a=0',
                    'ptqrtoken' => $qrToken,
                    'ptredirect' => 0,
                    'h' => 1,
                    't' => 1,
                    'g' => 1,
                    'from_ui' => 1,
                    'ptlang' => 2052,
                    'action' => '0-0-'.Time::getMillisecondTimestamp(),
                    'js_ver' => 25051315,
                    'js_type' => 1,
                    'login_sig' => $loginSig,
                    'pt_uistyle' => 40,
                    'aid' => 1600001063,
                    'daid' => 733,
                    null,
                    'o1vId' => '3f7262f28e2853a1549dbdd4f0008b0f',
                    'pt_js_version' => '9fce2a54',
                ],
                'cookies' => $this->cookie,
            ]);
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getStatusCode()) {
                return Response::json(-5, '响应错误');
            }
        }
        $result = $response->getBody()->getContents();
        try {
            if ($result == '') {
                throw new Exception('qrSig参数不正确', -1);
            }
            preg_match("/ptuiCB\s*\(\s*'(.*?)'\s*,\s*'(.*?)'\s*,\s*'(.*?)'\s*,\s*'(.*?)'\s*,\s*'(.*?)'\s*,\s*'(.*?)'\s*\)/u", $result, $matches);
            if ($matches[1] == '65') {
                throw new Exception($matches[5], -2);
            }
            if ($matches[1] == '66') {
                throw new Exception($matches[5], 1);
            }
            if ($matches[1] == '67') {
                throw new Exception($matches[5], 2);
            }
            if ($matches[1] == '86') {
                throw new Exception($matches[5], -3);
            }
            if ($matches[1] != '0') {
                throw new Exception($matches[5], -4);
            }
        } catch (\Exception $e) {
            return Response::json($e->getCode(), $e->getMessage());
        }
        $q_url = $matches[3];
        preg_match('/uin=(.*?)&/', $q_url, $matches);
        $qq = $matches[1];
        $access = new Access();
        $access->qq = $qq;

        $this->client->request('GET', $q_url, [
            'cookies' => $this->cookie,
        ]);
        $cookies = $this->cookie->toArray();
        $t_cookie = [];
        foreach ($cookies as $value) {
            if ($value['Value'] != '') {
                $t_cookie[$value['Name']] = $value['Value'];
            }
        }
        $access->cookie = json_encode($t_cookie);
        $access->replace()->save();
        return Response::json(0, '登录成功', [
            'cookie' => $t_cookie,
        ]);
    }

    public function getAccessToken(): Json
    {
        $params = Request::param('cookie');
        if (!$params) {
            return Response::json(-1, 'cookie参数必填');
        }
        if (str_contains($params, '\\')) { // 判断cookie字符串中有转义字符
            $params = stripslashes($params); // 去除转义字符
        }

        $params = json_decode($params, true);
        $this->cookie = $this->cookie::fromArray($params, '.qq.com');
        $response = $this->client->request('POST', 'https://www.wegame.com.cn/api/middle/clientapi/auth/login_by_qq', [
            'json' => [
                'clienttype' => '1000005',
                'mappid' => '10001',
                'mcode',
                'config_params' => [
                    'lang_type' => 0,
                ],
                'login_info' => [
                    'qq_info_type' => 6,
                    'uin' => str_replace('o', '', $params['uin']),
                    'sig' => $params['p_skey'],
                ],
            ],
            'headers' => [
                'referer' => 'https://www.wegame.com.cn/login/callback.html?t=qq&c=0&a=0',
            ],
            'cookies' => $this->cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['code'] != 0) {
            return Response::json($data['code'], $data['msg']);
        }
        return Response::json(0, '获取成功', [
            'tgp_id' => $data['data']['user_id'],
            'tgp_ticket' => $data['data']['wt'],
        ]);
    }

    public function getQrToken(string $qrSig): int
    {
        $len = strlen($qrSig);
        $hash = 0;
        for ($i = 0; $i < $len; $i++) {
            $hash += (($hash << 5) & 2147483647) + ord($qrSig[$i]) & 2147483647;
            $hash &= 2147483647;
        }
        return $hash & 2147483647;
    }

    private function getCookieValue($name)
    {
        $cookies = array_column($this->cookie->toArray(), 'Value', 'Name');
        return $cookies[$name] ?? null;
    }

    public function gift()
    {

        $id = Request::param('id');
        $ticket = Request::param('ticket');
        if (!$id || !$ticket) {
            return Response::json(-1, '缺少参数');
        }
        $this->cookie = $this->cookie::fromArray([
            'tgp_id' => $id,
            'tgp_ticket' => $ticket,
        ], '.wegame.com.cn');
        // 打开保险箱礼包
        $response = $this->client->request('POST', 'https://www.wegame.com.cn/api/v1/wegame.pallas.dfm.DfmSocial/OpenTreasureChest', [
            'cookies' => $this->cookie,
            'json' => [
                'account_type' => 1,
                'from_src' => 'df_web',
            ],
            'headers' => [
                'referer' => 'https://www.wegame.com.cn/helper/df/',
            ],
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['result']['error_code'] != 0) {
            return Response::json(-1, '获取礼包失败');
        }
        $rewards = $data['rewards'];
        // 领取保险箱礼包
        if ($data['is_obtain']) {
            return Response::json(0, '已领取', $rewards);
        }
        $response = $this->client->request('POST', 'https://www.wegame.com.cn/api/v1/wegame.pallas.dfm.DfmSocial/ObtainTreasureChest', [
            'cookies' => $this->cookie,
            'json' => [
                'account_type' => 1,
                'from_src' => 'df_web',
            ],
            'headers' => [
                'referer' => 'https://www.wegame.com.cn/helper/df/',
            ],
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['result']['error_code'] != 0) {
            return Response::json(-1, '领取礼包失败');
        }
        return Response::json(0, '领取成功', $rewards);
    }
}