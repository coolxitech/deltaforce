<?php

namespace app\controller;

use app\utils\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use think\facade\Request;
use think\response\Json;

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

    public function getData(): Json
    {
        $openId = Request::param('openid');
        $accessToken = Request::param('access_token');
        $seasonId = Request::param('seasonid'); // 可查指定赛季
        if (empty($openId) || empty($accessToken)) {
            return Response::json(-1, '参数错误');
        }
        if (empty($seasonId) || $seasonId == '') {
            $seasonId = 3;
        }
        $gameData = [];
        $cookie = CookieJar::fromArray([
            'openid' => $openId,
            'access_token' => $accessToken,
            'acctype' => 'qc',
            'appid' => 101491592,
        ], '.qq.com');

        // 烽火地带战绩
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
        $gameData['matchList']['touchGold'] = $data['ret'] != 0 ? [] : $data['jData']['data'];

        // 全面战场战绩
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 319386,
                'iSubChartId' => 319386,
                'sIdeToken' => 'zMemOt',
                'type' => 5,
                'page' => 1,
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        $gameData['matchList']['battlefield'] = $data['ret'] != 0 ? [] : $data['jData']['data'];

        // 游戏配置信息
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 316968,
                'iSubChartId' => 316968,
                'sIdeToken' => 'KfXJwH',
                'source' => 5,
                'method' => 'dfm/config.list',
                'param' => json_encode([
                    'configType' => 'all',
                ]),
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        $gameData['config'] = $data['ret'] != 0 ? [] : $data['jData']['data']['data']['config'];

        // 玩家赛季信息
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 317814,
                'iSubChartId' => 317814,
                'sIdeToken' => 'QIRBwm',
                'seasonid' => $seasonId,
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

        // 登录流水信息
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 319386,
                'iSubChartId' => 319386,
                'sIdeToken' => 'zMemOt',
                'type' => 1,
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['ret'] != 0) {
            $gameData['PlayerInfo']['login'] = [];
        } else {
            $gameData['PlayerInfo']['login'] = $data['jData']['data'];
        }

        // 道具流水信息
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 319386,
                'iSubChartId' => 319386,
                'sIdeToken' => 'zMemOt',
                'type' => 2,
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['ret'] != 0) {
            $gameData['PlayerInfo']['item'] = [];
        } else {
            $gameData['PlayerInfo']['item'] = $data['jData']['data'];
        }

        // 哈夫币流水信息
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 319386,
                'iSubChartId' => 319386,
                'sIdeToken' => 'zMemOt',
                'type' => 3,
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['ret'] != 0) {
            $gameData['PlayerInfo']['money'] = [];
        } else {
            $gameData['PlayerInfo']['money'] = $data['jData']['data'];
            $gameData['PlayerInfo']['money']['total'] = $gameData['PlayerInfo']['money'][0]['totalMoney'];
            unset($gameData['PlayerInfo']['money'][0]);
        }

        // 玩家资产
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 318948,
                'iSubChartId' => 318948,
                'sIdeToken' => 'Plaqzy',
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['ret'] != 0) {
            $gameData['PlayerAssets'] = [
                'userData' => [],
                'weponData' => [],
                'dCData' => [],
            ];
        } else {
            $gameData['PlayerAssets']['userData'] = $data['jData']['userData'];
            $gameData['PlayerAssets']['weponData'] = $data['jData']['weponData'];
            $gameData['PlayerAssets']['dCData'] = $data['jData']['dCData'][0] ?? [];
        }

        // 三角劵数量
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 319386,
                'iSubChartId' => 319386,
                'sIdeToken' => 'zMemOt',
                'type' => 3,
                'item' => 17888808888,
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['ret'] != 0) {
            $gameData['PlayerAssets']['coin'] = 0;
        } else {
            $gameData['PlayerAssets']['coin'] = $data['jData']['data'][0]['totalMoney'];
        }

        // 三角币数量
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 319386,
                'iSubChartId' => 319386,
                'sIdeToken' => 'zMemOt',
                'type' => 3,
                'item' => 17888808889,
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['ret'] != 0) {
            $gameData['PlayerAssets']['tickets'] = 0;
        } else {
            $gameData['PlayerAssets']['tickets'] = $data['jData']['data'][0]['totalMoney'];
        }

        //哈夫币数量
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 319386,
                'iSubChartId' => 319386,
                'sIdeToken' => 'zMemOt',
                'type' => 3,
                'item' => 17020000010,
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['ret'] != 0) {
            $gameData['PlayerAssets']['money'] = 0;
        } else {
            $gameData['PlayerAssets']['money'] = $data['jData']['data'][0]['totalMoney'];
        }

        return Response::json(0, '获取成功', $gameData);
    }
}
