<?php

namespace app\controller;

use app\BaseController;
use app\utils\Response;

class Index extends BaseController
{
    public function index()
    {
        return Response::json(0, '欢迎使用三角洲行动API');
    }
}
