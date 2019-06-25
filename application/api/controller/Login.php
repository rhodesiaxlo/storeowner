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

		if(gettype($is_exist) !="object")
		{
			if(intval($is_exist)!==false)
			{
				if(intval($is_exist) === 1000)
				{
					$this->ajaxFail('account error , more than one accout with the same name and password were found!!', [], 9999);
				}
			}
		}

		// 不存在
		if($is_exist === false || $is_exist == null)
		{
			$this->ajaxFail('name and password conbination incorrect', [], 1000);
			return;
		}

		// 被冻结
		if($is_exist->is_active != 1)
		{
			$this->ajaxFail('store not active', [], 1001);
			return;
		}

		$tmp = [];
		$res = [];
		$res['token'] = $is_exist->token;
		$tmp[] = $res;
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