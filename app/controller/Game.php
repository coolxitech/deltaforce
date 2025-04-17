<?php

namespace app\controller;

use app\utils\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use think\facade\Request;

class Game
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'verify' => false,
            'version' => 2.0,
        ]);
    }

    public function getData()
    {
        $openId = Request::param('openid');
        $accessToken = Request::param('access_token');
        if (empty($openId) || empty($accessToken)) {
            return Response::json(-1, '参数错误');
        }
        $gameData = [];
        $cookie = CookieJar::fromArray([
            'openid' => $openId,
            'access_token' => $accessToken,
            'acctype' => 'qc',
            'appid' => 101491592,
        ], '.qq.com');

        // 战绩列表
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 319386,
                'iSubChartId' => 319386,
                'sIdeToken' => 'zMemOt',
                'type' => 4,
                'page' => 1,
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        $gameData['matchList'] = $data['ret'] != 0 ? [] : $data['jData']['data'];
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 317814,
                'iSubChartId' => 317814,
                'sIdeToken' => 'QIRBwm',
                'seasonid' => 3, // 赛季
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['ret'] != 0) {
            $gameData['gameInfo'] = [];
        } else {
            $data['jData']['userData']['charac_name'] = urldecode($data['jData']['userData']['charac_name']);
            $gameData['gameInfo'] = [
                'userData' => $data['jData']['userData'],
                'careerData' => $data['jData']['careerData'],
            ];
        }

        return Response::json(0, '获取成功', $gameData);
    }
}