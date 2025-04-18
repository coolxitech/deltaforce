<?php

use think\facade\Route;

Route::group('qq', function () {
    Route::rule('sig', 'QQ/getQrSig');
    Route::rule('status', 'QQ/getAction');
    Route::rule('access', 'QQ/getAccessToken');
});

Route::group('game', function () {
    Route::rule('data', 'Game/getData');
});
