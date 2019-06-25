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

    public function isZeroOrOne($var)
    {
        if(!is_numeric($var))
        {
            return false;
        }

        if(intval($var) !== 0 && intval($var) !==1)
        {
            return false;
        }
    }

    public function isEmptyOrInt($var)
    {

    }

    public function isNullInteger($var)
    {

    }

    public function isTimeStampSecond($var)
    {
        //1970-01-01 08:00:00  0
        //2019     1561014782 second
        //         1561014782000
        //2999     32486857982
        //
        //         9223372036854775807
        if(!is_numeric($var))
            return false;

        if($var > 33000000000)
        {
            return false;
        }
        
    }

    public function isTimeStampMiniSecond($var)
    {
        //1970-01-01 08:00:00  0
        //2999     32486857982
        //
        //
        //
        if(!is_numeric($var))
            return false;

        if($var > 33000000000000)
        {
            return false;
        }
        
    }

    public function isDateString($str_dt, $str_dateformat, $str_timezone) 
    {
        $date = DateTime::createFromFormat($str_dateformat, $str_dt, new DateTimeZone($str_timezone));
        return $date && DateTime::getLastErrors()["warning_count"] == 0 && DateTime::getLastErrors()["error_count"] == 0;
    }

}