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
            $seasonId = 0;
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
        $gameData['matchList']['gun'] = $data['ret'] != 0 ? [] : $data['jData']['data'];

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
        $gameData['matchList']['operator'] = $data['ret'] != 0 ? [] : $data['jData']['data'];

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

    public function record(): Json
    {
        $openId = Request::param('openid');
        $accessToken = Request::param('access_token');
        if (empty($openId) || empty($accessToken)) {
            return Response::json(-1, '缺少参数');
        }
        $cookie = CookieJar::fromArray([
            'openid' => $openId,
            'access_token' => $accessToken,
            'acctype' => 'qc',
            'appid' => 101491592,
        ], '.qq.com');
        $gameData = [];
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
        $gameData['gun'] = $data['ret'] != 0 ? null : $data['jData']['data'];

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
        $gameData['operator'] = $data['ret'] != 0 ? null : $data['jData']['data'];

        if ($gameData['gun'] == null && $gameData['operator'] == null) {
            return Response::json(-1, 'AccessToken已失效');
        }
        return Response::json(0, '获取成功', $gameData);
    }

    public function player(): Json
    {
        $openId = Request::param('openid');
        $accessToken = Request::param('access_token');
        $seasonId = Request::param('season_id') ?? 0;
        if (empty($openId) || empty($accessToken)) {
            return Response::json(-1, '缺少参数');
        }
        $cookie = CookieJar::fromArray([
            'openid' => $openId,
            'access_token' => $accessToken,
            'acctype' => 'qc',
            'appid' => 101491592,
        ], '.qq.com');
        $gameData = [
            'player' => [],
            'game' => [],
            'coin' => 0,
            'tickets' => 0,
            'money' => 0,
        ];

        // 玩家信息
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
        if ($data['ret'] == 0) {
            $data['jData']['userData']['charac_name'] = urldecode($data['jData']['userData']['charac_name']);
            $gameData['player'] = $data['jData']['userData'];
            $gameData['game'] = $data['jData']['careerData'];
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
        if ($data['ret'] == 0) {
            $gameData['coin'] = (int) $data['jData']['data'][0]['totalMoney'];
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
        if ($data['ret'] == 0) {
            $gameData['tickets'] = (int) $data['jData']['data'][0]['totalMoney'];
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
        if ($data['ret'] == 0) {
            $gameData['money'] = (int) $data['jData']['data'][0]['totalMoney'];
        }

        return Response::json(0, '获取成功', $gameData);
    }

    public function config(): Json
    {
        $openId = Request::param('openid');
        $accessToken = Request::param('access_token');
        if (empty($openId) || empty($accessToken)) {
            return Response::json(-1, '缺少参数');
        }
        $cookie = CookieJar::fromArray([
            'openid' => $openId,
            'access_token' => $accessToken,
            'acctype' => 'qc',
            'appid' => 101491592,
        ], '.qq.com');

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
        $gameData = $data['ret'] != 0 ? [] : $data['jData']['data']['data']['config'];

        return Response::json(0, '获取成功', $gameData);
    }

    public function items(): Json
    {
        $openId = Request::param('openid');
        $accessToken = Request::param('access_token');
        $type = Request::param('type') ?? '';
        $subType = Request::param('sub_type') ?? '';
        $itemId = Request::param('item_id') ?? '';
        if (empty($openId) || empty($accessToken)) {
            return Response::json(-1, '缺少参数');
        }
        $cookie = CookieJar::fromArray([
            'openid' => $openId,
            'access_token' => $accessToken,
            'acctype' => 'qc',
            'appid' => 101491592,
        ], '.qq.com');
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 316968,
                'iSubChartId' => 316968,
                'sIdeToken' => 'KfXJwH',
                'source' => 2,
                'method' => 'dfm/object.list',
                'param' => json_encode([
                    'primary' => $type,
                    'second' => $subType,
                    'objectID' => $itemId,
                ])
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['ret'] != 0) {
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        }

        return Response::json(0, '获取成功', $data['jData']['data']['data']['list']);
    }

    public function price()
    {
        $openid = Request::param('openid');
        $access_token = Request::param('access_token');
        $ids = Request::param('ids');
        $recent = Request::param('recent') ?? 0;
        if (empty($openid) || empty($access_token) || empty($ids)) {
            return Response::json(-1, '缺少参数');
        }
        $cookie = CookieJar::fromArray([
            'openid' => $openid,
            'access_token' => $access_token,
            'acctype' => 'qc',
            'appid' => 101491592,
        ], '.qq.com');
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 316968,
                'iSubChartId' => 316968,
                'sIdeToken' => 'KfXJwH',
                'source' => 2,
                'method' => 'dfm/object.price.latest',
                'param' => json_encode([
                    'objectID' => str_contains($ids, ',') ? array_map('intval', explode(',', $ids)) : [(int) $ids],
                ])
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['ret'] != 0) {
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        }
        $gameData = $data['jData']['data']['data']['dataMap'];

        if ($recent == 0) {
            return Response::json(0, '获取成功', $gameData);
        }
        foreach ($gameData as $key => $item) {
            $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
                'form_params' => [
                    'iChartId' => 316968,
                    'iSubChartId' => 316968,
                    'sIdeToken' => 'KfXJwH',
                    'source' => 2,
                    'method' => 'dfm/object.price.recent',
                    'param' => json_encode([
                        'objectID' => $key,
                    ])
                ],
                'cookies' => $cookie,
            ]);
            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);
            $gameData[$key]['recent'] = $data['jData']['data']['data']['objectPriceRecent']['list'];
        }

        return Response::json(0, '获取成功', $gameData);
    }

    public function assets()
    {
        $openid = Request::param('openid');
        $access_token = Request::param('access_token');
        if (empty($openid) || empty($access_token)) {
            return Response::json(-1, '缺少参数');
        }
        $cookie = CookieJar::fromArray([
            'openid' => $openid,
            'access_token' => $access_token,
            'acctype' => 'qc',
            'appid' => 101491592,
        ], '.qq.com');

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
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        } else {
            $gameData['userData'] = $data['jData']['userData'];
            $gameData['weponData'] = $data['jData']['weponData'];
            $gameData['dCData'] = $data['jData']['dCData'];
        }

        return Response::json(0, '获取成功', $gameData);
    }

    public function logs()
    {
        $openid = Request::param('openid');
        $access_token = Request::param('access_token');
        if (Request::param('type') == null || Request::param('type') == '') {
            $type = 1;
        } else {
            $type = Request::param('type');
        }

        if (empty($openid) || empty($access_token)) {
            return Response::json(-1, '缺少参数');
        }
        $cookie = CookieJar::fromArray([
            'openid' => $openid,
            'access_token' => $access_token,
            'acctype' => 'qc',
            'appid' => 101491592,
        ], '.qq.com');
        // 流水信息
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 319386,
                'iSubChartId' => 319386,
                'sIdeToken' => 'zMemOt',
                'type' => $type,
            ],
            'cookies' => $cookie,
        ]);
        $result = $response->getBody()->getContents();
        $data = json_decode($result, true);
        if ($data['ret'] != 0) {
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        }

        if ($type == 3) {
            $data['jData']['data']['totalMoney'] = $data['jData']['data'][0]['totalMoney'];
            unset($data['jData']['data'][0]);
        }
        return Response::json(0, '获取成功', $data['jData']['data']);
    }
}
