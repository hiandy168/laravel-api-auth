<?php

use Qbhy\LaravelApiAuth\LaravelApiAuthMiddleware;

return [
    'status' => 'on', // 状态，on 或者 off

    'roles' => [
//        '{access_key}' => [
//            'name' => '{role_name}',        // 角色名字，例如 android
//            'secret_key' => '{secret_key}',
//        ],
    ],

    'timeout' => 60, // 签名失效时间，单位: 秒

    'encrypting' => [LaravelApiAuthMiddleware::class, 'encrypting'], // 自定义签名方法

    'rule' => [LaravelApiAuthMiddleware::class, 'rule'], // 判断签名正确的规则，默认是相等

    'error_handler' => [LaravelApiAuthMiddleware::class, 'error_handler'], // 签名错误处理方法。
];