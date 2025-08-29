<?php

namespace app\controller;

use app\utils\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use think\facade\Request;

class Steam
{
    public function index()
    {
        $token = Request::param('token'); // 需要Steam的指定Cookie字符串(steamLoginSecure)
        $client = new Client();
        $response = $client->request('GET', 'https://help.steampowered.com/zh-cn/wizard/VacBans', [
            'cookies' => CookieJar::fromArray([
                'steamLoginSecure' => $token,
            ], 'help.steampowered.com'),
        ]);
        $result = $response->getBody()->getContents();
        preg_match_all('/help_highlight_text">(.*?)</', $result, $matches);
        $games = $matches[1];
        array_pop($games); // 删掉多余的提示字符串
        return Response::json(0, '成功', $games);
    }
}