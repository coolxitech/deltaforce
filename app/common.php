<?php
// 应用公共文件

use GuzzleHttp\Cookie\CookieJar;

function getMicroTime(): int
{
    return round(microtime(true) * 1000);
}

function getQrToken(string $qrSig): int
{
    $len = strlen($qrSig);
    $hash = 0;
    for ($i = 0; $i < $len; $i++) {
        $hash += (($hash << 5) & 2147483647) + ord($qrSig[$i]) & 2147483647;
        $hash &= 2147483647;
    }
    return $hash & 2147483647;
}

function getGTK(string $sKey): int
{
    $hash = 5381;
    $len = strlen($sKey);

    for ($i = 0; $i < $len; $i++) {
        // Using ord() to get ASCII value similar to charCodeAt()
        // Left shift and addition operations are the same
        $hash += ($hash << 5) + ord($sKey[$i]);
        // Ensure 32-bit integer precision by applying bitwise AND with 0x7fffffff
        $hash = $hash & 0x7fffffff;
    }

    return $hash & 0x7fffffff;
}

function createCookie(string $openId, string $accessToken, string $gsCode): CookieJar
{
    return CookieJar::fromArray([
        'openid' => $openId,
        'access_token' => $accessToken,
        'gs_id' => $openId,
        'gs_code' => $gsCode,
    ], '.qq.com');
}