<?php
// 应用公共文件

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