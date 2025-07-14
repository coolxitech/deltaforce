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
Route::group('qqsafe', function () {
    Route::rule('sig', 'QQSafe/getQrSig');
    Route::rule('status', 'QQSafe/getAction');
    Route::rule('access', 'QQSafe/getAccessToken');
});
Route::group('wegame', function () {
    Route::rule('sig', 'Wegame/getQrSig');
    Route::rule('status', 'Wegame/getAction');
    Route::rule('access', 'Wegame/getAccessToken');
    Route::rule('gift', 'Wegame/gift');
});
