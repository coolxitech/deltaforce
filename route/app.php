<?php

use think\facade\Route;

Route::group('qq', function () {
    Route::rule('sig', 'QQ/getQrSig');
    Route::rule('status', 'QQ/getAction');
    Route::rule('access', 'QQ/getAccessToken');
});
Route::group('wechat', function () {
    Route::rule('login', 'Wechat/login');
    Route::rule('status', 'Wechat/status');
    Route::rule('access', 'Wechat/getAccessToken');
});
