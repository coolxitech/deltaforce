<?php

namespace app\controller;

use app\model\Access;
use app\utils\Response;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\BadResponseException;
use think\facade\Request;
use think\response\Json;

const APPID = 716027609;

class Pioneer
{
    private CookieJar $cookie;
    private Client $client;

    public function __construct()
    {
        $this->cookie = new CookieJar();
        $curlVersion = curl_version(); // 获取curl版本信息
        $http2Supported = isset($curlVersion['features']) && ($curlVersion['features'] & CURL_VERSION_HTTP2) !== 0; // 判断是否支持http2
        $httpVersion = $http2Supported ? 2.0 : 1.1;
        $this->client = new Client([
            'cookies' => $this->cookie,
            'allow_redirects' => false,
            'verify' => false,
            'version' => $httpVersion,
            'http_errors' => false,
            'proxy' => 'http://127.0.0.1:9001'
        ]);
    }

    public function getQrSig()
    {
        if (!$this->getLoginToken()) {
            throw new Exception('LoginToken获取失败', -1);
        }
        $response = $this->client->request('GET', 'https://xui.ptlogin2.qq.com/ssl/ptqrshow', [
            'query' => [
                'appid' => APPID,
                'e' => 2,
                'l' => 'M',
                's' => 3,
                'd' => 72,
                'v' => 4,
                't' => 0.6142752744667854,
                'daid' => 383,
                'pt_3rd_aid' => '101477677',
                'u1' => 'https://graph.qq.com/oauth2.0/login_jump',
            ],
        ]);
        if ($response->getStatusCode() != 200) {
            return Response::json(-1, '获取失败');
        }

        $result = $response->getBody()->getContents();
        $sig = getCookieValue($this->cookie, 'qrsig');
        $cookies = $this->cookie->toArray();
        $cookie = [];
        foreach ($cookies as $value) {
            $cookie[$value['Name']] = $value['Value'];
        }
        return Response::json(0, '获取成功', [
            'qrSig' => $sig,
            'image' => base64_encode($result),
            'token' => getQrToken($sig),
            'loginSig' => getCookieValue($this->cookie, 'pt_login_sig'),
            'cookie' => $cookie,
        ]);
    }

    private function getLoginToken(): bool
    {
        $response = $this->client->request('GET', 'https://xui.ptlogin2.qq.com/cgi-bin/xlogin', [
            'query' => [
                'appid' => APPID,
                'daid' => 383,
                'style' => 33,
                'login_text' => '登录',
                'hide_title_bar' => 1,
                'hide_border' => 1,
                'target' => 'self',
                's_url' => 'https://graph.qq.com/oauth2.0/login_jump',
                'pt_3rd_aid' => '101477677',
                'pt_feedback_link' => 'https://support.qq.com/products/77942?customInfo=milo.qq.com.appid101477677',
                'theme' => 2,
                'verify_theme',
            ],
        ]);
        return $response->getStatusCode() === 200;
    }

    public function getAction(string $qrToken, string $qrSig, string $loginSig): Json
    {
        try {
            $cookie = Request::param('cookie');
            if (str_contains($cookie, '\\')) { // 判断cookie字符串中有转义字符
                $cookie = stripslashes($cookie); // 去除转义字符
            }
            if (!$cookie) {
                return Response::json(-1, '缺少cookie参数');
            }
            $cookies = json_decode($cookie, true);
            $cookies['qrsig'] = $qrSig;
            $this->cookie = $this->cookie::fromArray($cookies, '.ptlogin2.qq.com');
            $response = $this->client->request('GET', 'https://xui.ptlogin2.qq.com/ssl/ptqrlogin', [
                'query' => [
                    'u1' => 'https://graph.qq.com/oauth2.0/login_jump',
                    'ptqrtoken' => $qrToken,
                    'from_ui' => 1,
                    'login_sig' => $loginSig,
                    'aid' => APPID,
                    'daid' => 383,
                    'pt_3rd_aid' => '101477677',
                ],
                'cookies' => $this->cookie,
            ]);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            if ($response->getStatusCode()) {
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
        preg_match('/ptsigx=(.*?)&/', $q_url, $matches);
        $ptsigx = $matches[1];

        // check_sig
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
        return Response::json(0, '登录成功', [
            'cookie' => $t_cookie,
        ]);
    }

    public function getAccessToken(): Json
    {
        $params = Request::param('cookie');
        if (!$params) {
            return Response::json(-1, 'cookie参数必须填');
        }
        if (str_contains($params, '\\')) { // 判断cookie字符串中有转义字符
            $params = stripslashes($params); // 去除转义字符
        }

        $params = json_decode($params, true);
        $this->cookie = $this->cookie::fromArray($params, '.qq.com');
        $response = $this->client->request('POST', 'https://graph.qq.com/oauth2.0/authorize', [
            'form_params' => [
                'response_type' => 'code',
                'client_id' => '101477677',
                'redirect_uri' => 'https://m.gamer.qq.com/v2/passport/qq/callback?url=https%3A%2F%2Fm.gamer.qq.com%2Fv2%2Fhome%2Fmy',
                'scope' => 'get_user_info',
                'state' => 'gamer.qq.com',
                'switch',
                'form_plogin' => 1,
                'src' => 1,
                'update_auth' => 1,
                'openapi' => 1010,
                'g_tk' => getGtk($params['p_skey']),
                'auth_time' => time(),
                'ui' => '4F384776-3605-4955-B015-DBA77968FC7C',
            ],
            'headers' => [
                'referer' => 'https://gamer.qq.com/',
            ],
            'cookies' => $this->cookie,
        ]);
        preg_match('/code=(.*?)&/', $response->getHeaderLine('Location'), $matches);
        if (!isset($matches[1])) { // 过期的Cookie或者触发风控会不返回带code的Location,触发风控只能等待。
            return Response::json(-1, 'Cookie过期，请重新扫码登录');
        }
        $qcCode = $matches[1];
        $this->client->request('GET', $response->getHeaderLine('Location'), [
            'cookies' => $this->cookie,
        ]);
        $response = $this->client->request('GET', 'https://gamer.qq.com/v2/passport/qq/callback', [
            'query' => [
                'code' => $qcCode,
                'state' => 'gamer.qq.com',
            ],
            'cookies' => $this->cookie,
        ]);

        // login获取鉴权cookie
        $this->client->request('GET', $response->getHeaderLine('Location'), [
            'cookies' => $this->cookie,
        ]);
        if ($response->getStatusCode() != 302) {
            return Response::json(-1, 'AccessToken获取失败:'.$response->getStatusCode());
        }

        $key = getCookieValue($this->cookie, 'key');
        if (!$key) {
            return Response::json(-1, 'AccessToken获取失败');
        }
        return Response::json(0, '获取成功', [
            'key' => $key,
        ]);
    }

    public function updateAccessToken(): Json
    {
        $cookie = Request::param('cookie');
        if (str_contains($cookie, '\\')) { // 判断cookie字符串中有转义字符
            $cookie = stripslashes($cookie); // 去除转义字符
        }
        $openId = Request::param('openid');
        $accessToken = Request::param('access_token');
        $cookies = json_decode($cookie, true);
        $this->cookie = $this->cookie::fromArray($cookies, '.ptlogin2.qq.com');
        $response = $this->client->request('POST', 'https://ams.game.qq.com/ams/userLoginSvr', [
            'query' => [
                'callback' => 'coolxitech',
                'acctype' => 'qc',
                'appid' => APPID,
                'access_token' => $accessToken,
                'openid' => $openId,
                'refresh_token',
                'ieg_ams_sign' => 'null',
                'expires_time' => 'null',
                '_' => getMicroTime(),
            ],
            'cookies' => $this->cookie,
            'headers' => [
                'referer' => 'https://df.qq.com/',
            ],
        ]);
        $result = $response->getBody()->getContents();
        preg_match('/coolxitech\((.*?)\);/', $result, $matches);
        $data = json_decode($matches[1], true);
        if ($data['isLogin'] != 1) {
            return Response::json(-1, '更新失败');
        }
        return Response::json(0, '更新成功');
    }

    public function getGameTestList(): Json
    {
        $key = Request::param('key'); // 可选, 填入后会获取更多数据
        $type = Request::param('type') ?? 'pc'; // 可选
        $this->cookie = $this->cookie::fromArray([
            'key' => $key,
        ], '.gamer.qq.com');

        $response = $this->client->request('POST', 'https://m.gamer.qq.com/graph/wxmini/GetCollList', [
            'json' => [
                'subType' => match ($type) {
                    'pc', '' => 12,
                    'mobile' => 22
                }
            ],
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['errCode'] != 0) {
            return Response::json(-1, $data['msg']);
        }
        $data = json_decode($data['result']['collList'][0]['content'], true)['list'];
        if (!$key) {
            return Response::json(0, 'success', $data);
        }

        foreach ($data as &$item) {
            if (preg_match('#/detail/\d+/(\d+)#', $item['szJumpUrl'], $matches)) {
                $item['detail'] = $this->getGameDetail(key: $key, id: (int) $matches[1]);
            }

        }
        return Response::json(0, 'success', $data);
    }

    private function getGameDetail(string $key, int $id): array
    {
        $this->cookie = $this->cookie::fromArray([
            'key' => $key,
        ], '.gamer.qq.com');
        $response = $this->client->request('GET', 'https://m.gamer.qq.com/task/misc/gettask2', [
            'query' => [
                'iTaskID' => $id,
            ],
            'cookies' => $this->cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['errCode'] != 0) {
            return [];
        }
        return $data['result'];
    }
}