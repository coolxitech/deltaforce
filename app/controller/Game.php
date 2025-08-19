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
            if ($data['ret'] === -4000) {
                return Response::json(-2, '您的账号由于腾讯内部错误无法使用这个功能');
            }
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
                    'seasonid' => [1, 2, 3, 4, 5],
                    'isAllSeason' => true,
                ]),
            ],
            'cookies' => $cookie,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        return $data['ret'] !== 0
            ? Response::json(-1, '获取失败,检查鉴权是否过期')
            : Response::json(0, '获取成功', $data['jData']['data']['data']['solDetail'] ?? []);
    }

    public function password(): Json
    {
        $params = Request::only(['openid', 'access_token']);
        $accessType = Request::header('acctype');
        if (empty($params['openid']) || empty($params['access_token'])) {
            return Response::json(-1, '缺少参数');
        }

        $cookie = $this->createCookie($params['openid'], $params['access_token'], empty($accessType) || !($accessType === 'wx'));
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 352143,
                'iSubChartId' => 352143,
                'sIdeToken' => 'YWRywA',
                'method' => 'dfm/center.day.secret',
                'source' => 2,
                'param' => json_encode([]),
            ],
            'cookies' => $cookie,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        if ($data['ret'] !== 0) {
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        }
        $rooms = [];
        foreach ($data['jData']['data']['data']['list'] as $datum) {
            $rooms[$datum['mapName']] = $datum['secret'];
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

    public function bind(): Json
    {
        $params = Request::only(['openid', 'access_token']);
        $accessType = Request::header('acctype') ?? 'qc';
        if (empty($params['openid']) || empty($params['access_token'])) {
            return Response::json(-1, '缺少参数');
        }

        $cookie = $this->createCookie($params['openid'], $params['access_token'], empty($accessType) || !($accessType === 'wx'));
        $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
            'form_params' => [
                'iChartId' => 316964,
                'iSubChartId' => 316964,
                'sIdeToken' => '95ookO',
            ],
            'cookies' => $cookie,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        if ($data['ret'] !== 0) {
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        }
        if (empty($data['jData']['bindarea'])) { // 未绑定游戏角色
            // 获取角色信息
            $response = $this->client->request('GET', 'https://comm.aci.game.qq.com/main', [
                'query' => [
                    'needGopenid' => 1,
                    'sAMSAcctype' => $accessType == 'qc' ? 'qq' : 'wx',
                    'sAMSAccessToken' => $params['access_token'],
                    'sAMSAppOpenId' => $params['openid'],
                    'sAMSSourceAppId' => '101491592',
                    'game' => 'dfm',
                    'sCloudApiName' => 'ams.gameattr.role',
                    'area' => 36,
                    'platid' => 1,
                    'partition' => 36
                ],
                'headers' => [
                    'referer' => 'https://df.qq.com/',
                ],
            ]);
            $result = $response->getBody()->getContents();
            preg_match("/\{([^}]*)}/", $result, $matches);
            preg_match_all("/(\w+):('[^']*'|-?\d+|[^,]*)/", $matches[1], $pairs, PREG_SET_ORDER);
            $data = [];
            foreach ($pairs as $pair) {
                $key = $pair[1];
                $value = trim($pair[2], "'"); // 去除单引号
                if ($key == 'msg') {
                    $data[$key] = mb_convert_encoding($value, 'UTF-8', 'GBK');
                } else {
                    $data[$key] = $value;
                }
            }
            $roleId = explode('|', $data['checkparam'])[2];
            // 开始提交绑定
            $response = $this->client->request('POST', 'https://comm.ams.game.qq.com/ide/', [
                'form_params' => [
                    'iChartId' => 316965,
                    'iSubChartId' => 316965,
                    'sIdeToken' => 'sTzZS2',
                    'sArea' => 36,
                    'sPlatId' => 1,
                    'sPartition' => 36,
                    'sCheckparam' => $data['checkparam'],
                    'sRoleId' => $roleId,
                    'md5str' => $data['md5str'],
                ],
                'cookies' => $cookie,
            ]);
            $result = $response->getBody()->getContents();
            $data = json_decode($result, true);
            if ($data['ret'] !== 0) {
                return Response::json(-1, '绑定失败');
            } else {
                return Response::json(1, '获取成功', $data['jData']['bindarea']);
            }
        }
        return Response::json(0, '获取成功', $data['jData']['bindarea']);

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
