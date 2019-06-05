<?php
namespace app\api\controller;

use app\model\User;
use think\config;
use  think\Request;

class BaseController
{

    public function jsonTest()
    {
    	return $this->ajaxFail('fail', [], 1000);
    }

    public function ajaxFail($message, $data, $error_code)
    {
		$this->returnJson($message, 0, $error_code, $data);
    }

    public function ajaxSuccess($message, $data)
    {
    	$this->returnJson($message, 1, 0, $data);
    }

    public function returnJson($message='success', $code='1', $error_code='0', $data=[])
    {
    	$return = [];
    	$return['code'] = $code;
    	$return['error_code'] = $error_code;
    	$return['message'] = $message;
    	$return['data'] = $data;
    	exit(json_encode($return));
    }
}