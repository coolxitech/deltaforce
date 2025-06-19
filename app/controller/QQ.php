<?php

namespace app\controller;

use app\model\Access;
use app\utils\Response;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\BadResponseException;
use think\facade\Request;
use think\response\Json;

class QQ
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
                'appid' => 716027609,
                'e' => 2,
                'l' => 'M',
                's' => 3,
                'd' => 72,
                'v' => 4,
                't' => 0.6142752744667854,
                'daid' => 383,
                'pt_3rd_aid' => 101491592,
                'u1' => 'https://graph.qq.com/oauth2.0/login_jump',
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
                'appid' => 716027609,
                'daid' => 383,
                'style' => 33,
                'login_text' => '登录',
                'hide_title_bar' => 1,
                'hide_border' => 1,
                'target' => 'self',
                's_url' => 'https://graph.qq.com/oauth2.0/login_jump',
                'pt_3rd_aid' => 101491592,
                'pt_feedback_link' => 'https://support.qq.com/products/77942?customInfo=milo.qq.com.appid101491592',
                'theme' => 2,
                'verify_theme',
            ],
        ]);
        return $response->getStatusCode() === 200;
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
            $response = $this->client->request('GET', 'https://ssl.ptlogin2.qq.com/ptqrlogin', [
                'query' => [
                    'u1' => 'https://graph.qq.com/oauth2.0/login_jump',
                    'ptqrtoken' => $qrToken,
                    'ptredirect' => 0,
                    'h' => 1,
                    't' => 1,
                    'g' => 1,
                    'from_ui' => 1,
                    'ptlang' => 2052,
                    'action' => '0-0-1744807890273',
                    'js_ver' => 25040111,
                    'js_type' => 1,
                    'login_sig' => $loginSig,
                    'pt_uistyle' => 40,
                    'aid' => 716027609,
                    'daid' => 383,
                    'pt_3rd_aid' => 101491592,
                    null,
                    'o1vId' => '378b06c889d9113b39e814ca627809e3',
                    'pt_js_version' => '530c3f68',
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
        $qq = Request::param('qq');
        if (!$qq && !$params) {
            return Response::json(-1, 'QQ号或cookie参数必须填一个');
        }
        if ($qq) {
            $access = Access::where('qq', $qq)->find();
            if (empty($access)) {
                return Response::json(-2, '未找到该QQ号');
            }
            $params = $access->cookie;
        } else {
            if (str_contains($params, '\\')) { // 判断cookie字符串中有转义字符
                $params = stripslashes($params); // 去除转义字符
            }
        }

        $params = json_decode($params, true);
        $this->cookie = $this->cookie::fromArray($params, '.qq.com');
        $response = $this->client->request('POST', 'https://graph.qq.com/oauth2.0/authorize', [
            'form_params' => [
                'response_type' => 'code',
                'client_id' => '101491592',
                'redirect_uri' => 'https://milo.qq.com/comm-htdocs/login/qc_redirect.html?parent_domain=https://df.qq.com&isMiloSDK=1&isPc=1',
                'scope',
                'state' => 'STATE',
                'switch',
                'form_plogin' => 1,
                'src' => 1,
                'update_auth' => 1,
                'openapi' => 1010,
                'g_tk' => $this->getGtk($params['p_skey']),
                'auth_time' => time(),
                'ui' => '979D48F3-6CE2-4E95-A789-3BD3187648B6',
            ],
            'headers' => [
                'referer' => 'https://xui.ptlogin2.qq.com/',
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
        $response = $this->client->request('GET', 'https://ams.game.qq.com/ams/userLoginSvr', [
            'query' => [
                'a' => 'qcCodeToOpenId',
                'qc_code' => $qcCode,
                'appid' => 101491592,
                'redirect_uri' => 'https://milo.qq.com/comm-htdocs/login/qc_redirect.html',
                'callback' => 'miloJsonpCb_86690',
                '_' => getMicroTime(),
            ],
            'cookies' => $this->cookie,
            'headers' => [
                'referer' => 'https://df.qq.com/',
            ],
        ]);
        $result = $response->getBody()->getContents();
        preg_match('/miloJsonpCb_86690\((.*?)\)/', $result, $matches);
        $result = $matches[1];
        $data = json_decode($result, true);
        if ($data['iRet'] != 0) {
            return Response::json(-1, 'AccessToken获取失败');
        }
        $access = new Access();
        $cookie = $access->where('cookie', json_encode($params))->find();
        if (!$cookie->isEmpty()) {
            $access->where('cookie', json_encode($params))->replace()->update([
                'access_token' => $data['access_token'],
                'openid' => $data['openid'],
            ]);
        }
        return Response::json(0, '获取成功', [
            'access_token' => $data['access_token'],
            'expires_in' => $data['expires_in'],
            'openid' => $data['openid'],
        ]);
    }


    private function getCookieValue($name)
    {
        $cookies = array_column($this->cookie->toArray(), 'Value', 'Name');
        return $cookies[$name] ?? null;
    }

    private function getGTK(string $skey): int
    {
        $hash = 5381;
        $len = strlen($skey);

        for ($i = 0; $i < $len; $i++) {
            // Using ord() to get ASCII value similar to charCodeAt()
            // Left shift and addition operations are the same
            $hash += ($hash << 5) + ord($skey[$i]);
            // Ensure 32-bit integer precision by applying bitwise AND with 0x7fffffff
            $hash = $hash & 0x7fffffff;
        }

        return $hash & 0x7fffffff;
    }
}