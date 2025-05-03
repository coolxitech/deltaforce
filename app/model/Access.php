<?php

namespace app\model;

use think\model;

class Access extends model
{
    protected array $schema = [
        'qq' => 'string',
        'openid' => 'string',
        'access_token' => 'string',
        'cookie' => 'string',
    ];
}
