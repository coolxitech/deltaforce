<?php

namespace app\controller;

use app\utils\Response;
use GuzzleHttp\Cookie\CookieJar;
use think\facade\Request;
use think\response\Json;
use GuzzleHttp\Client;

const APPID = 'wxfa0c35392d06b82f';
class Wechat
{
    protected Client $client;

    protected CookieJar $cookie;
    public function __construct()
    {
        $curlVersion = curl_version(); // 获取curl版本信息
        $http2Supported = isset($curlVersion['features']) && ($curlVersion['features'] & CURL_VERSION_HTTP2) !== 0; // 判断是否支持http2
        $httpVersion = $http2Supported ? 2.0 : 1.1;
        $this->cookie = new CookieJar();
        $this->client = new Client([
            'cookies' => $this->cookie,
            'allow_redirects' => false,
            'verify' => false,
            'version' => $httpVersion,
        ]);
    }
    public function login():  Json
    {
        $response = $this->client->request('GET', 'https://open.weixin.qq.com/connect/qrconnect', [
            'query' => [
                'appid' => APPID,
                'scope' => 'snsapi_login',
                'redirect_uri' => 'https://iu.qq.com/comm-htdocs/login/milosdk/wx_pc_redirect.html?appid='.APPID.'&sServiceType=undefined&originalUrl=https%3A%2F%2Fdf.qq.com%2Fcp%2Frecord202410ver%2F&oriOrigin=https%3A%2F%2Fdf.qq.com',
                'state' => 1,
                'login_type' => 'jssdk',
                'self_redirect' => true,
                'ts' => getMicroTime(),
                'style' => 'black',
            ],
            'headers' => [
                'referer' => 'https://df.qq.com/',
            ],
        ]);
        $result = $response->getBody()->getContents();
        preg_match('~/connect/qrcode/[^\s<>"]+~', $result, $qrcode_match);
        $qrcodeUrl = $qrcode_match[0];
        $uuid = substr($qrcodeUrl, 16);
        $qrcodeUrl = 'https://open.weixin.qq.com'.$qrcodeUrl;

        return Response::json(0, '获取成功', [
            'qrCode' => $qrcodeUrl,
            'uuid' => $uuid,
        ]);
    }

    public function status():  Json
    {
        $uuid = Request::param('uuid') ?? '';
        if (!$uuid || $uuid == '') {
            return Response::json(-1, '缺少参数');
        }
        $response = $this->client->request('GET', 'https://lp.open.weixin.qq.com/connect/l/qrconnect', [
            'query' => [
                'uuid' => $uuid,
            ],
        ]);
        $result = $response->getBody()->getContents();
        preg_match('/wx_errcode=(\d+);/', $result, $errcode_match);
        preg_match('/wx_code=\'([^\']*)\';/', $result, $code_match);
        $wx_errcode = (int) $errcode_match[1] ?? null;
        $wx_code = $code_match[1] ?? null;

        if ($wx_errcode == 402) {
            return Response::json(-2, '二维码超时');
        }

        if ($wx_errcode == 408) {
            return Response::json(1, '等待扫描');
        }

        if ($wx_errcode == 404) {
            return Response::json(2, '已扫码');
        }

        if ($wx_errcode == 405) {
            return Response::json(3, '扫码成功', [
                'wx_errcode' => $wx_errcode,
                'wx_code' => $wx_code,
            ]);
        }

        if ($wx_errcode == 403) {
            return Response::json(-3, '扫码被拒绝');
        }

        return Response::json(-4, '其他错误代码', [
            'wx_errcode' => $wx_errcode,
            'wx_code' => $wx_code,
        ]);
    }

    public function getAccessToken():  Json
    {
        $code = Request::param('code') ?? '';
        if (!$code || $code == '') {
            return Response::json(-1, '缺少参数');
        }
        $response = $this->client->request('GET', 'https://apps.game.qq.com/ams/ame/codeToOpenId.php', [
            'query' => [
                'callback' => '',
                'appid' => APPID,
                'wxcode' => $code,
                'originalUrl' => 'https://df.qq.com/cp/record202410ver/',
                'wxcodedomain' => 'iu.qq.com',
                'acctype' => 'wx',
                'sServiceType' => 'undefined',
                '_' => getMicroTime(),
            ],
            'headers' => [
                'referer' => 'https://df.qq.com/',
            ],
        ]);

        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['iRet'] == 0) {
            $data = json_decode($data['sMsg'], true);
            return Response::json(0, '获取成功', [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'openid' => $data['openid'],
                'unionid' => $data['unionid'],
                'expires_in' => $data['expires_in'],
            ]);
        }
        return Response::json(-2, '获取失败:'.$data['sMsg']);
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
                'acctype' => 'wx',
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
}