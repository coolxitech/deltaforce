<?php
// 应用公共文件

function getMicroTime(): int
{
    return round(microtime(true) * 1000);
}