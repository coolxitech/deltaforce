<?php

namespace app\controller;

use app\utils\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use think\facade\Request;
use think\response\Json;

class Game
{
    private const IDE_URL = 'https://comm.ams.game.qq.com/ide/';
    private const ROLE_INFO_URL = 'https://comm.aci.game.qq.com/main';

    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'verify' => false,
            'version' => 1.1,
        ]);
    }

    public function record(): Json
    {
        $params = $this->getRequiredParams(['openid', 'access_token']);
        if ($params === null) {
            return Response::json(-1, '缺少参数');
        }
        $cookie = $this->getAuthCookie($params);
        $gameData = [
            'gun' => [],
            'operator' => [],
        ];

        $types = [4 => 'gun', 5 => 'operator'];
        foreach ($types as $type => $key) {
            for ($i = 1; $i <= 5; $i++) { // 长时间未游戏可能需要翻页获取
                $data = $this->requestIde([
                    'iChartId' => 319386,
                    'iSubChartId' => 319386,
                    'sIdeToken' => 'zMemOt',
                    'type' => $type,
                    'page' => $i,
                ], $cookie);
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
        $params = $this->getRequiredParams(['openid', 'access_token', 'season_id']);
        if ($params === null) {
            return Response::json(-1, '缺少参数');
        }

        $cookie = $this->getAuthCookie($params);
        $gameData = [
            'player' => [],
            'game' => [],
            'coin' => 0,
            'tickets' => 0,
            'money' => 0,
        ];

        $data = $this->requestIde([
            'iChartId' => 317814,
            'iSubChartId' => 317814,
            'sIdeToken' => 'QIRBwm',
        ], $cookie);
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
            $data = $this->requestIde([
                'iChartId' => 319386,
                'iSubChartId' => 319386,
                'sIdeToken' => 'zMemOt',
                'type' => 3,
                'item' => $itemId,
            ], $cookie);
            if ($data['ret'] === 0) {
                $gameData[$key] = (int) ($data['jData']['data'][0]['totalMoney'] ?? 0);
            }
        }

        return Response::json(0, '获取成功', $gameData);
    }

    public function config(): Json
    {
        $data = $this->requestIde([
            'iChartId' => 352143,
            'iSubChartId' => 352143,
            'sIdeToken' => 'YWRywA',
            'source' => 5,
            'method' => 'dfm/config.list',
            'param' => json_encode(['configType' => 'all']),
        ]);
        $gameData = $data['ret'] === 0 ? $data['jData']['data']['data']['config'] : [];

        return Response::json(0, '获取成功', $gameData);
    }

    public function items(): Json
    {
        $params = Request::only(['type', 'sub_type', 'item_id']);
        $data = $this->requestIde([
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
        ]);
        return $data['ret'] !== 0
            ? Response::json(-1, '获取失败')
            : Response::json(0, '获取成功', $data['jData']['data']['data']['list'] ?? []);
    }

    public function price(): Json
    {
        $params = $this->getRequiredParams(['openid', 'access_token', 'ids', 'recent']);
        if ($params === null || empty($params['ids'])) {
            return Response::json(-1, '缺少参数');
        }

        $cookie = $this->getAuthCookie($params);
        $ids = str_contains($params['ids'], ',') ? array_map('intval', explode(',', $params['ids'])) : [(int) $params['ids']];
        $data = $this->requestIde([
            'iChartId' => 352143,
            'iSubChartId' => 352143,
            'sIdeToken' => 'YWRywA',
            'source' => 2,
            'method' => 'dfm/object.price.latest',
            'param' => json_encode(['objectID' => $ids]),
        ], $cookie);
        if ($data['ret'] !== 0) {
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        }

        $gameData = $data['jData']['data']['data']['dataMap'];
        if (($params['recent'] ?? 0) == 1) {
            foreach ($gameData as $key => &$item) {
                $recentData = $this->requestIde([
                    'iChartId' => 352143,
                    'iSubChartId' => 352143,
                    'sIdeToken' => 'YWRywA',
                    'source' => 2,
                    'method' => 'dfm/object.price.recent',
                    'param' => json_encode(['objectID' => $key]),
                ], $cookie);
                $item['recent'] = $recentData['jData']['data']['data']['objectPriceRecent']['list'] ?? [];
            }
            unset($item);
        }

        return Response::json(0, '获取成功', $gameData);
    }

    public function assets(): Json
    {
        $params = $this->getRequiredParams(['openid', 'access_token']);
        if ($params === null) {
            return Response::json(-1, '缺少参数');
        }

        $cookie = $this->getAuthCookie($params);
        $data = $this->requestIde([
            'iChartId' => 318948,
            'iSubChartId' => 318948,
            'sIdeToken' => 'Plaqzy',
        ], $cookie);
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
        $params = $this->getRequiredParams(['openid', 'access_token', 'type', 'page']);
        if ($params === null) {
            return Response::json(-1, '缺少参数');
        }

        $type = $params['type'] ?? 1;
        $page = (int) ($params['page'] ?? 1);
        $cookie = $this->getAuthCookie($params);
        $data = $this->requestIde([
            'iChartId' => 319386,
            'iSubChartId' => 319386,
            'sIdeToken' => 'zMemOt',
            'type' => $type,
            'page' => $page,
        ], $cookie);
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
        $params = $this->getRequiredParams(['openid', 'access_token', 'type']);
        if ($params === null) {
            return Response::json(-1, '缺少参数');
        }
        $cookie = $this->getAuthCookie($params);
        $data = $this->requestIde([
            'iChartId' => 316969,
            'iSubChartId' => 316969,
            'sIdeToken' => 'NoOapI',
            'method' => 'dfm/center.recent.detail',
            'source' => '5',
            'param' => json_encode(['resourceType' => 'sol']),
        ], $cookie);
        return $data['ret'] !== 0
            ? Response::json(-1, '获取失败,检查鉴权是否过期')
            : Response::json(0, '获取成功', $data['jData']['data']['data']['solDetail']);
    }

    public function achievement(): Json
    {
        $params = $this->getRequiredParams(['openid', 'access_token', 'type']);
        if ($params === null) {
            return Response::json(-1, '缺少参数');
        }

        $cookie = $this->getAuthCookie($params);
        $data = $this->requestIde([
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
        ], $cookie);
        return $data['ret'] !== 0
            ? Response::json(-1, '获取失败,检查鉴权是否过期')
            : Response::json(0, '获取成功', $data['jData']['data']['data']['solDetail'] ?? []);
    }

    public function password(): Json
    {
        $params = $this->getRequiredParams(['openid', 'access_token']);
        if ($params === null) {
            return Response::json(-1, '缺少参数');
        }

        $cookie = $this->getAuthCookie($params);
        $data = $this->requestIde([
            'iChartId' => 352143,
            'iSubChartId' => 352143,
            'sIdeToken' => 'YWRywA',
            'method' => 'dfm/center.day.secret',
            'source' => 2,
            'param' => json_encode([]),
        ], $cookie);
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
        $params = $this->getRequiredParams(['openid', 'access_token', 'type']);
        if ($params === null) {
            return Response::json(-1, '缺少参数');
        }

        $cookie = $this->getAuthCookie($params);
        $data = $this->requestIde([
            'iChartId' => 365589,
            'iSubChartId' => 365589,
            'sIdeToken' => 'bQaMCQ',
            'source' => 5,
        ], $cookie);
        return $data['ret'] !== 0
            ? Response::json(-1, '获取失败,检查鉴权是否过期')
            : Response::json(0, '获取成功', $data['jData']['data']['data']);
    }

    public function guns(): Json
    {
        $params = Request::only(['gunId']);
        $data = $this->requestIde([
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
        ]);
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
        unset($weaponData);

        return Response::json(0, '获取成功', $weapons);
    }

    public function bind(): Json
    {
        $params = $this->getRequiredParams(['openid', 'access_token']);
        $accessType = Request::header('acctype') ?? 'qc';
        if ($params === null) {
            return Response::json(-1, '缺少参数');
        }

        $cookie = $this->getAuthCookie($params);
        $data = $this->requestIde([
            'iChartId' => 316964,
            'iSubChartId' => 316964,
            'sIdeToken' => '95ookO',
        ], $cookie);
        if ($data['ret'] !== 0) {
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        }
        if (empty($data['jData']['bindarea'])) { // 未绑定游戏角色
            // 获取角色信息
            $response = $this->client->request('GET', self::ROLE_INFO_URL, [
                'query' => [
                    'needGopenid' => 1,
                    'sAMSAcctype' => $accessType == 'qc' ? 'qq' : 'wx',
                    'sAMSAccessToken' => $params['access_token'],
                    'sAMSAppOpenId' => $params['openid'],
                    'sAMSTargetAppId' => '1110543085',
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
            $data = $this->requestIde([
                'iChartId' => 316965,
                'iSubChartId' => 316965,
                'sIdeToken' => 'sTzZS2',
                'sArea' => 36,
                'sPlatId' => 1,
                'sPartition' => 36,
                'sCheckparam' => $data['checkparam'],
                'sRoleId' => $roleId,
                'md5str' => $data['md5str'],
            ], $cookie);
            if ($data['ret'] !== 0) {
                return Response::json(-1, '绑定失败');
            } else {
                return Response::json(1, '获取成功', $data['jData']['bindarea']);
            }
        }
        return Response::json(0, '获取成功', $data['jData']['bindarea']);

    }

    public function firearmModList()
    {
        $page = Request::get('page', 1);
        $pageSize = Request::get('page_size', 10);
        $data = $this->requestIde([
            'iChartId' => 352143,
            'iSubChartId' => 352143,
            'sIdeToken' => 'YWRywA',
            'source' => 2,
            'method' => 'dfm/solution.arms.list',
            'param' => json_encode([
                'page' => (int) $page,
                'limit' => (int) $pageSize,
                'solutionType' => 'gun',
            ]),
        ]);
        return $data['ret'] !== 0
            ? Response::json(-1, '获取失败,检查鉴权是否过期')
            : Response::json(0, '获取成功', $data['jData']['data']['data']['list']);
    }

    public function recommendation(): Json
    {
        $place = Request::get('place', 'tech');
        $data = $this->requestIde([
            'iChartId' => 352143,
            'iSubChartId' => 352143,
            'sIdeToken' => 'YWRywA',
            'source' => 2,
            'method' => 'dfm/place.list',
            'param' => json_encode([
                'type' => 'place',
                'place' => $place,
                'hasPriceData' => true,
            ]),
        ]);
        return $data['ret'] !== 0
            ? Response::json(-1, '获取失败,检查鉴权是否过期')
            : Response::json(0, '获取成功', $data['jData']['data']['data']['list']);
    }

    public function quartermaster()
    {
        $params = $this->getRequiredParams(['openid', 'access_token']);
        $type = match (Request::param('type')) {
            '', null,'secret' => 1,
            'market' => 3,
        };
        if ($params === null) {
            return Response::json(-1, '缺少参数');
        }
        $cookie = $this->getAuthCookie($params);
        $data = $this->requestIde([
            'iChartId' => 530479,
            'iSubChartId' => 530479,
            'sIdeToken' => 'SwgEeH',
            'action' => $type,
        ], $cookie);
        if ($data['ret'] !== 0) {
            return Response::json(-1, '获取失败,检查鉴权是否过期');
        }
        $data = $data['jData']['data'];
        foreach ($data as &$item) {
            $parsedItem = $this->decodeUrlEncodedItemStack($item['Item']);
            $item['Item'] = $parsedItem['Item'];
            $item['count'] = $parsedItem['count'];
            $item['Price'] = (int) $item['Price'];
            $item['Bought'] = $item['Bought'] != '0';
            $item['IsGreatEarn'] = $item['IsGreatEarn'] != '0';
            $item['ExchangedProps'] = $this->decodeUrlEncodedItemStackList($item['ExchangedProps']);
        }
        unset($item);
        return Response::json(code: 0, msg: '获取成功', data: $data);
    }

    private function getRequiredParams(array $keys): ?array
    {
        $params = Request::only($keys);
        foreach ($keys as $key) {
            if (in_array($key, ['openid', 'access_token'], true) && empty($params[$key])) {
                return null;
            }
        }

        return $params;
    }

    private function getAuthCookie(array $params): CookieJar
    {
        $accessType = Request::header('acctype');

        return $this->createCookie(
            $params['openid'],
            $params['access_token'],
            empty($accessType) || $accessType !== 'wx'
        );
    }

    private function requestIde(array $formParams, ?CookieJar $cookie = null): array
    {
        $options = ['form_params' => $formParams];
        if ($cookie !== null) {
            $options['cookies'] = $cookie;
        }

        $response = $this->client->request('POST', self::IDE_URL, $options);

        return json_decode($response->getBody()->getContents(), true);
    }

    private function decodeUrlEncodedItemStack(string $value): array
    {
        $parts = explode(',', urldecode($value));

        return [
            'Item' => (int) ($parts[0] ?? 0),
            'count' => (int) ($parts[1] ?? 0),
        ];
    }

    private function decodeUrlEncodedItemStackList(string $value): array
    {
        return array_values(array_filter(array_map(function ($item) {
            $parsedItem = $this->decodeUrlEncodedItemStack($item);
            return $parsedItem['Item'] === 0 ? null : $parsedItem;
        }, explode(';', urldecode($value)))));
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
