<?php

namespace app\utils;

use think\response\Json;

class Response
{
    public static function json(int $code, string $msg, array $data = [], int $statusCode = 200) : Json
    {
        return json(['code' => $code,'msg' => $msg,'data' => $data], $statusCode);
    }
}
