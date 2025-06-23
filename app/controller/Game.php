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

    public function record(): Json
    {
        $params = Request::only(['openid', 'access_token']);
        $accessType = Request::header('acctype');
        if (empty($params['openid']) || empty($params['access_token'])) {
            return Response::json(-1, '缺少参数');
        }
        $cookie = $this->createCookie($params['openid'], $params['access_token'], empty($accessType) || !($accessType === 'wx'));
        $gameData = [
            'gun' => [],
            'operator' => [],
        ];

        $types = [4 => 'gun', 5 => 'operator'];
        foreach ($types as $type => $key) {
            for ($i = 1; $i <= 5; $i++) { // 长时间未游戏可能需要翻页获取
                $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
                    'form_params' => [
                        'iChartId' => 319386,
                        'iSubChartId' => 319386,
                        'sIdeToken' => 'zMemOt',
                        'type' => $type,
                        'page' => $i,
                    ],
                    'cookies' => $cookie,
                ]);
                $data = json_decode($response->getBody()->getContents(), true);
                if ($data['ret'] === 0 && !empty($data['jData']['data'])) {
                    $gameData[$key] = array_merge($gameData[$key], $data['jData']['data']);
                }
            }
            if ($data['ret'] != 0) {
                return Response::json(-1, '获取失败');
            }
        }

        return Response::json(0, '获取成功', $gameData);
    }

    public function player(): Json
    {
        $params = Request::only(['openid', 'access_token', 'season_id']);
        $accessType = Request::header('acctype');
        if (empty($params['openid']) || empty($params['access_token'])) {
            return Response::json(-1, '缺少参数');
        }

        $seasonId = $params['season_id'] ?? 0;
        $cookie = $this->createCookie($params['openid'], $params['access_token'], empty($accessType) || !($accessType === 'wx'));
        $gameData = [
            'player' => [],
            'game' => [],
            'coin' => 0,
            'tickets' => 0,
            'money' => 0,
        ];

        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 317814,
                'iSubChartId' => 317814,
                'sIdeToken' => 'QIRBwm',
                'seasonid' => $seasonId,
            ],
            'cookies' => $cookie,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        if ($data['ret'] === 0) {
            $gameData['player'] = array_merge($data['jData']['userData'], [
                'charac_name' => urldecode($data['jData']['userData']['charac_name']),
            ]);
            $gameData['game'] = $data['jData']['careerData'];
        }

        $currencyItems = [
            'coin' => 17888808888,
            'tickets' => 17888808889,
            'money' => 17020000010,
        ];
        foreach ($currencyItems as $key => $itemId) {
            $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
                'form_params' => [
                    'iChartId' => 319386,
                    'iSubChartId' => 319386,
                    'sIdeToken' => 'zMemOt',
                    'type' => 3,
                    'item' => $itemId,
                ],
                'cookies' => $cookie,
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            if ($data['ret'] === 0) {
                $gameData[$key] = (int) ($data['jData']['data'][0]['totalMoney'] ?? 0);
            }
        }

        return Response::json(0, '获取成功', $gameData);
    }

    public function config(): Json
    {
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 352143,
                'iSubChartId' => 352143,
                'sIdeToken' => 'YWRywA',
                'source' => 5,
                'method' => 'dfm/config.list',
                'param' => json_encode(['configType' => 'all']),
            ],
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        $gameData = $data['ret'] === 0 ? $data['jData']['data']['data']['config'] : [];

        return Response::json(0, '获取成功', $gameData);
    }

    public function items(): Json
    {
        $params = Request::only(['type', 'sub_type', 'item_id']);
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 352143,
                'iSubChartId' => 352143,
                'sIdeToken' => 'YWRywA',
                'source' => 2,
                'method' => 'dfm/object.list',
                'param' => json_encode([
                    'primary' => $params['type'] ?? '',
                    'second' => $params['sub_type'] ?? '',
                    'objectID' => $params['item_id'] ?? '',
                ]),
            ],
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        return $data['ret'] !== 0
            ? Response::json(-1, '获取失败')
            : Response::json(0, '获取成功', $data['jData']['data']['data']['list'] ?? []);
    }

    public function price(): Json
    {
        $params = Request::only(['openid', 'access_token', 'ids', 'recent']);
        $accessType = Request::header('acctype');
        if (empty($params['openid']) || empty($params['access_token']) || empty($params['ids'])) {
            return Response::json(-1, '缺少参数');
        }

        $cookie = $this->createCookie($params['openid'], $params['access_token'], empty($accessType) || !($accessType === 'wx'));
        $ids = str_contains($params['ids'], ',') ? array_map('intval', explode(',', $params['ids'])) : [(int) $params['ids']];
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 352143,
                'iSubChartId' => 352143,
                'sIdeToken' => 'YWRywA',
                'source' => 2,
                'method' => 'dfm/object.price.latest',
                'param' => json_encode(['objectID' => $ids]),
            ],
            'cookies' => $cookie,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        if ($data['ret'] !== 0) {
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        }

        $gameData = $data['jData']['data']['data']['dataMap'];
        if (($params['recent'] ?? 0) == 1) {
            foreach ($gameData as $key => &$item) {
                $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
                    'form_params' => [
                        'iChartId' => 352143,
                        'iSubChartId' => 352143,
                        'sIdeToken' => 'YWRywA',
                        'source' => 2,
                        'method' => 'dfm/object.price.recent',
                        'param' => json_encode(['objectID' => $key]),
                    ],
                    'cookies' => $cookie,
                ]);
                $recentData = json_decode($response->getBody()->getContents(), true);
                $item['recent'] = $recentData['jData']['data']['data']['objectPriceRecent']['list'] ?? [];
            }
        }

        return Response::json(0, '获取成功', $gameData);
    }

    public function assets(): Json
    {
        $params = Request::only(['openid', 'access_token']);
        $accessType = Request::header('acctype');
        if (empty($params['openid']) || empty($params['access_token'])) {
            return Response::json(-1, '缺少参数');
        }

        $cookie = $this->createCookie($params['openid'], $params['access_token'], empty($accessType) || !($accessType === 'wx'));
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 318948,
                'iSubChartId' => 318948,
                'sIdeToken' => 'Plaqzy',
            ],
            'cookies' => $cookie,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        if ($data['ret'] !== 0) {
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        }

        return Response::json(0, '获取成功', [
            'userData' => $data['jData']['userData'],
            'weponData' => $data['jData']['weponData'],
            'dCData' => $data['jData']['dCData'],
        ]);
    }

    public function logs(): Json
    {
        $params = Request::only(['openid', 'access_token', 'type']);
        $accessType = Request::header('acctype');
        if (empty($params['openid']) || empty($params['access_token'])) {
            return Response::json(-1, '缺少参数');
        }

        $type = $params['type'] ?? 1;
        $cookie = $this->createCookie($params['openid'], $params['access_token'], empty($accessType) || !($accessType === 'wx'));
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 319386,
                'iSubChartId' => 319386,
                'sIdeToken' => 'zMemOt',
                'type' => $type,
            ],
            'cookies' => $cookie,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        if ($data['ret'] !== 0) {
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        }

        if ($type == 3) {
            $data['jData']['data'] = ['totalMoney' => $data['jData']['data'][0]['totalMoney']];
        }
        return Response::json(0, '获取成功', $data['jData']['data']);
    }

    public function recent(): Json
    {
        $params = Request::only(['openid', 'access_token', 'type']);
        $accessType = Request::header('acctype');
        if (empty($params['openid']) || empty($params['access_token'])) {
            return Response::json(-1, '缺少参数');
        }
        $cookie = $this->createCookie($params['openid'], $params['access_token'], empty($accessType) || !($accessType === 'wx'));
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 316969,
                'iSubChartId' => 316969,
                'sIdeToken' => 'NoOapI',
                'method' => 'dfm/center.recent.detail',
                'source' => '5',
                'param' => json_encode(['resourceType' => 'sol']),
            ],
            'cookies' => $cookie,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        return $data['ret'] !== 0
            ? Response::json(-1, '获取失败,检查鉴权是否过期')
            : Response::json(0, '获取成功', $data['jData']['data']['data']['solDetail']);
    }

    public function achievement(): Json
    {
        $params = Request::only(['openid', 'access_token', 'type']);
        $accessType = Request::header('acctype');
        if (empty($params['openid']) || empty($params['access_token'])) {
            return Response::json(-1, '缺少参数');
        }

        $type = $params['type'] ?? 1;
        $cookie = $this->createCookie($params['openid'], $params['access_token'], empty($accessType) || !($accessType === 'wx'));
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 316969,
                'iSubChartId' => 316969,
                'sIdeToken' => 'NoOapI',
                'method' => 'dfm/center.person.resource',
                'source' => '5',
                'param' => json_encode([
                    'resourceType' => 'sol',
                    'seasonid' => [1, 2, 3, 4],
                    'isAllSeason' => true,
                ]),
            ],
            'cookies' => $cookie,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        return $data['ret'] !== 0
            ? Response::json(-1, '获取失败,检查鉴权是否过期')
            : Response::json(0, '获取成功', $data['jData']['data']['data']['solDetail']);
    }

    public function password(): Json
    {
        $params = Request::only(['openid', 'access_token', 'type']);
        $accessType = Request::header('acctype');
        if (empty($params['openid']) || empty($params['access_token'])) {
            return Response::json(-1, '缺少参数');
        }

        $type = $params['type'] ?? 1;
        $cookie = $this->createCookie($params['openid'], $params['access_token'], empty($accessType) || !($accessType === 'wx'));
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 384918,
                'iSubChartId' => 384918,
                'sIdeToken' => 'mbq5GZ',
                'method' => 'dist.contents',
                'source' => 5,
                'param' => json_encode([
                    'distType' => 'bannerManage',
                    'contentType' => 'secretDay',
                ]),
            ],
            'cookies' => $cookie,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        if ($data['ret'] !== 0) {
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        }

        $rooms = [];
        foreach (explode(";\n", $data['jData']['data']['data']['content']['secretDay']['data'][0]['desc']) as $value) {
            if (str_contains($value, ':')) {
                [$key, $val] = explode(':', $value, 2);
                $rooms[trim($key)] = (int) trim($val);
            }
        }
        return Response::json(0, '获取成功', $rooms);
    }

    public function manufacture(): Json
    {
        $params = Request::only(['openid', 'access_token', 'type']);
        $accessType = Request::header('acctype');
        if (empty($params['openid']) || empty($params['access_token'])) {
            return Response::json(-1, '缺少参数');
        }

        $type = $params['type'] ?? 1;
        $cookie = $this->createCookie($params['openid'], $params['access_token'], empty($accessType) || !($accessType === 'wx'));
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 365589,
                'iSubChartId' => 365589,
                'sIdeToken' => 'bQaMCQ',
                'source' => 5,
            ],
            'cookies' => $cookie,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        return $data['ret'] !== 0
            ? Response::json(-1, '获取失败,检查鉴权是否过期')
            : Response::json(0, '获取成功', $data['jData']['data']['data']);
    }

    public function guns(): Json
    {
        $params = Request::only(['gunId']);
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 352143,
                'iSubChartId' => 352143,
                'sIdeToken' => 'YWRywA',
                'source' => 2,
                'method' => 'dfm/object.list',
                'param' => json_encode([
                    'primary' => 'gun',
                    'second' => 'gunRifle',
                    'objectID' => $params['gunId'] ?? '',
                ]),
            ],
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        if ($data['ret'] !== 0) {
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        }

        $weapons = $data['jData']['data']['data']['list'] ?? [];
        $ammoConfig = config('ammo');
        $accessoryConfig = config('accessory');
        foreach ($weapons as &$weaponData) {
            $caliber = str_contains($weaponData['gunDetail']['caliber'], 'ammo')
                ? $weaponData['gunDetail']['caliber']
                : $this->normalizeCaliberCode($weaponData['gunDetail']['caliber']);
            $weaponData['gunDetail']['caliber'] = $caliber;
            $currentAmmoConfig = $ammoConfig[$caliber] ?? [];

            $weaponData['gunDetail']['ammo'] = array_map(function ($ammo, $key) use ($currentAmmoConfig) {
                return [
                    'objectID' => $ammo['objectID'],
                    'name' => $currentAmmoConfig[$key]['name'] ?? '',
                    'grade' => $currentAmmoConfig[$key]['grade'] ?? '',
                ];
            }, $weaponData['gunDetail']['ammo'], array_keys($weaponData['gunDetail']['ammo']));


            $mapAccessory = fn ($item) => [
                'slotID' => $item['slotID'],
                'name' => $accessoryConfig[$item['slotID']] ?? '',
            ];
            $weaponData['gunDetail']['accessory'] = array_map($mapAccessory, $weaponData['gunDetail']['accessory']);
            $weaponData['gunDetail']['allAccessory'] = array_map($mapAccessory, $weaponData['gunDetail']['allAccessory']);
        }

        return Response::json(0, '获取成功', $weapons);
    }

    private function normalizeCaliberCode(string $code): string
    {
        return preg_match('/\d+\.\d+x\d+/', $code, $matches) ? 'ammo' . $matches[0] : $code;
    }

    private function createCookie(string $openId, string $accessToken, bool $isQQ = true): CookieJar
    {
        return CookieJar::fromArray([
            'openid' => $openId,
            'access_token' => $accessToken,
            'acctype' => $isQQ ? 'qc' : 'wx',
            'appid' => 101491592,
        ], '.qq.com');
    }
}
