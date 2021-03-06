<?php

namespace QuarkCMS\QuarkAdmin\Controllers\Auth;

use QuarkCMS\QuarkAdmin\Controllers\QuarkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use QuarkCMS\QuarkAdmin\Models\Admin;
use QuarkCMS\QuarkAdmin\ActionLog;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Laravel\Passport\Client;
use Illuminate\Support\Str;

class AdminLoginController extends QuarkController
{
    /**
     * 登录方法
     * @author  tangtanglove <dai_hang_love@126.com>
     */
    public function login(Request $request)
    {
        // 账号登录

        $username = $request->json('username');
        $password = $request->json('password');
        $captcha = $request->json('captcha');

        $getCaptcha = cache('adminCaptcha');
        if(empty($captcha) || (strtolower($captcha) != strtolower($getCaptcha))) {
            return error('验证码错误！');
        }

        if(empty($username)) {
            return error('用户名不能为空！');
        }

        if(empty($password)) {
            return error('密码不能为空！');
        }

        $loginResult = Auth::guard('admin')->attempt(['username' => $username, 'password' => $password]);

        if($loginResult) {

            $user = Auth::guard('admin')->user();

            if(intval($user['status']) !== 1) {
                return error('用户被禁用！');
            }

            // 更新登录信息
            $data['last_login_ip'] = $request->ip();
            $data['last_login_time'] = date('Y-m-d H:i:s');
            Admin::where('id',$user->id)->update($data);

            $result['id'] = $user->id;
            $result['username'] = $user->username;
            $result['nickname'] = $user->nickname;
            $result['token'] = Str::random(950);

            // 将认证信息写入缓存，这里用hack方法做后台api登录认证
            cache([$result['token'] => $result],60*60*3);

            return success('登录成功！','',$result);
        } else {

            // 清除验证码
            cache(['adminCaptcha'=>null],60*10);
            
            return error('用户名或密码错误！');
        }
    }

    /**
     * 用户退出方法
     * @author  tangtanglove <dai_hang_love@126.com>
     */
    public function logout(Request $request)
    {
        // 得到认证凭据
        $authorization = $request->header('Authorization');

        // 分割出token
        $token = explode(' ',$authorization);

        // 删除认证缓存
        cache([$token[1] => null]);

        // 同时退出登录
        $result = Auth::guard('admin')->logout();

        if($result !== false) {

            return success('已退出！');
        } else {
            return error('错误！');
        }
    }

    /**
     * 图形验证码
     * @param  integer
     * @return string
     */
    public function captcha()
    {
        $phrase = new PhraseBuilder;
        // 设置验证码位数
        $code = Str::random(4);
        // 生成验证码图片的Builder对象，配置相应属性
        $builder = new CaptchaBuilder($code, $phrase);
        // 设置背景颜色
        $builder->setBackgroundColor(244, 252, 255);
        $builder->setMaxAngle(0);
        $builder->setMaxBehindLines(0);
        $builder->setMaxFrontLines(0);
        // 可以设置图片宽高及字体
        $builder->build($width = 110, $height = 38, $font = null);
        cache(['adminCaptcha' => $builder->getPhrase()],60*10);
        return response($builder->output())->header('Content-type','image/jpeg');
    }
}
