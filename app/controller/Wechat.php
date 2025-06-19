<?php

namespace app\controller;

use app\utils\Response;
use GuzzleHttp\Cookie\CookieJar;
use think\facade\Request;
use think\response\Json;
use GuzzleHttp\Client;

class Wechat
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
    public function login():  Json
    {
        $response = $this->client->request('GET', 'https://open.weixin.qq.com/connect/qrconnect', [
            'query' => [
                'appid' => 'wxfa0c35392d06b82f',
                'scope' => 'snsapi_login',
                'redirect_uri' => 'https://iu.qq.com/comm-htdocs/login/milosdk/wx_pc_redirect.html?appid=wxfa0c35392d06b82f&sServiceType=undefined&originalUrl=https%3A%2F%2Fdf.qq.com%2Fcp%2Frecord202410ver%2F&oriOrigin=https%3A%2F%2Fdf.qq.com',
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
                'appid' => 'wxfa0c35392d06b82f',
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
}