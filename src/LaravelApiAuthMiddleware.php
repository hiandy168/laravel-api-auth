<?php

namespace Qbhy\LaravelApiAuth;

use Closure;
use Illuminate\Http\Request;
use RuntimeException;

class LaravelApiAuthMiddleware
{

    const LACK_HEADER = 1001;   // 缺少头
    const ACCESS_KEY_ERROR = 1002;   // access_key 错误
    const SIGNATURE_ERROR = 1003;   // 签名错误
    const SIGNATURE_LAPSE = 1004;   // 签名失效
    const SIGNATURE_REPETITION = 1005;   // 签名重复

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (config('api_auth.status') === 'on') {
            $access_key = $request->header('api-access-key');
            $timestamp = $request->header('api-timestamp');
            $echostr = $request->header('api-echostr');
            $signature = $request->header('api-signature');

            /**
             * 获取 error_handler 函数
             */
            $error_handler = config('api_auth.error_handler');
            if (!is_callable($error_handler)) {
                throw new RuntimeException('config("api_auth.error_handler") is not function !');
            }

            if (empty($timestamp) || empty($access_key) || empty($echostr) || empty($signature)) {
                return call_user_func_array($error_handler, [
                    $request,
                    LaravelApiAuthMiddleware::LACK_HEADER
                ]);      // 缺少请求头
            }

            $roles = config('api_auth.roles');
            if (!isset($roles[$access_key])) {
                return call_user_func_array($error_handler, [
                    $request,
                    LaravelApiAuthMiddleware::ACCESS_KEY_ERROR
                ]);         // access_key 不存在
            }

            $encrypting = config('api_auth.encrypting');
            if (!is_callable($encrypting)) {
                throw new RuntimeException('config("api_auth.encrypting") is not function !');
            }

            $rule = config('api_auth.rule');
            if (!is_callable($rule)) {
                throw new RuntimeException('config("api_auth.rule") is not function !');
            }

            $server_signature = call_user_func_array($encrypting, [$roles[$access_key]['secret_key'], $echostr, $timestamp]);

            if (!call_user_func_array($rule, [$roles[$access_key]['secret_key'], $signature, $server_signature])) {
                return call_user_func_array($error_handler, [
                    $request,
                    LaravelApiAuthMiddleware::SIGNATURE_ERROR
                ]);  // 签名不一致
            }

            $timeout = config('api_auth.timeout', 60);
            if (time() - $timestamp > $timeout) {
                return call_user_func_array($error_handler, [
                    $request,
                    LaravelApiAuthMiddleware::SIGNATURE_LAPSE
                ]);      // 签名失效
            }

            if (!is_null(cache()->pull('api_auth:' . $signature))) {
                return call_user_func_array($error_handler, [
                    $request,
                    LaravelApiAuthMiddleware::SIGNATURE_REPETITION
                ]);      // 签名重复(已存在该签名记录)
            } else {
                cache()->put('api_auth:' . $signature, $request->getClientIp(), $timeout / 60);
            }

            /**
             * 添加 role_name 到 $request 中
             */
            if ($request->has('client_role')) {
                $request->offsetSet('_client_role', $request->get('client_role'));
            }
            $request->offsetSet('client_role', $roles[$access_key]['name']);
        }
        return $next($request);
    }

    /**
     * @param Request $request
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    public static function error_handler($request, $code = 403)
    {
        return response()->json([
            'msg' => 'Forbidden',
            'code' => $code
        ], 403);
    }

    /**
     * @param $secret_key
     * @param $echostr
     * @param $timestamp
     * @return string
     */
    public static function encrypting($secret_key, $echostr, $timestamp)
    {
        return md5($secret_key . $echostr . $timestamp);
    }

    /**
     * @param $secret_key
     * @param $signature
     * @param $server_signature
     * @return bool
     */
    public static function rule($secret_key, $signature, $server_signature)
    {
        return $signature === $server_signature;
    }
}