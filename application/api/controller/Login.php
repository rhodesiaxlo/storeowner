<?php
namespace app\api\controller;

use app\model\User;
use think\config;
use  think\Request;

class Login extends BaseController
{

	/**
	 * 登录
	 */
	public function Login()
	{
		$name = Request::instance()->param('name');
		$password = Request::instance()->param("password");

		// 参数验证
		if(empty($name))
		{
			$this->ajaxFail('name field not found', [], 2000);
		}

		if(empty($password))
		{
			$this->ajaxFail('password field not found', [], 2001);
		}	
		

		// 模型登录，返回token 信息
		$is_exist = User::login($name, $password);

		// 不存在
		if($is_exist === false || $is_exist == null)
		{
			$this->ajaxFail('store info not found', [], 1000);
			return;
		}

		// 被冻结
		if($is_exist->is_active != 1)
		{
			$this->ajaxFail('store not active', [], 1001);
			return;
		}

		$tmp = [];
		$tmp[] = $is_exist;
		$this->ajaxSuccess("login success", $tmp);


	}

	 /**
     * 提示信息
     * @return [type] [description]
     */
    public function test()
    {
    	echo "is_product = ".config::get('is_product')."<br/>";
    	echo "host = ".config::get('database.hostname')."<br/>";
    }
}