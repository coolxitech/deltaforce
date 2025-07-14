<?php

namespace app\utils;

class Time
{
    public static function getMillisecondTimestamp(): int
    {
        list($uSec, $sec) = explode(' ', microtime());
        return (int) ((floatval($uSec) + floatval($sec)) * 1000);
    }
}