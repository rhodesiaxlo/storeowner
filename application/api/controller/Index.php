<?php
namespace app\api\controller;

use app\model\User;
use think\config;

class Index
{

	public function __construct()
	{
		// check if already login, if not ,return errror
		// if does, save login data into session
	}

	/**
	 * 退出
	 * @return [type] [description]
	 */
	public function logout()
	{
    	return $this->ajaxFail('not implement yet', [], 1000);
	}

    public function index()
    {
        $user = new User();
        exit(json_encode($user->getUsers()));
    }

    public function test()
    {
    	echo "is_product = ".config::get('is_product')."<br/>";
    	echo "host = ".config::get('database.hostname')."<br/>";

    }

    /**
     * 登录接口
     * @return [type] [description]
     */
    public function login()
    {
    	return $this->ajaxFail('login faile', [], 1000);
    }

    /**
     * 首页概览
     * @return [type] [description]
     */
    public function frontpage()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 销售概况
     * @return [type] [description]
     */
    public function salesOutlook()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 根据日期查询销售数据，如果没有查询条件，去上一天
     * @return [type] [description]
     */
    public function salesDataByDate()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 销售趋势分析
     * @return [type] [description]
     */
    public function salesTrendingAnalysis()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 销售占比分析
     * @return [type] [description]
     */
    public function salesComposeAnalysis()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 销售统计
     * @return [type] [description]
     */
    public function salesReview()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 笔单价分析
     * @return [type] [description]
     */
    public function perorderAnalysis()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 获取订单列表
     * @return [type] [description]
     */
    public function getOrderList()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 交班记录
     * @return [type] [description]
     */
    public function getShiftRecord()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    public function dailyRecord()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 商品概览
     * @return [type] [description]
     */
    public function goodsOutlook()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 商品列表
     * @return [type] [description]
     */
    public function getGoodsList()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 商品详情
     * @return [type] [description]
     */
    public function goodsDetailList()
    {
    	// 分页，查询
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 分类数据
     * @return [type] [description]
     */
    public function getCategoryList()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 导入商品数据
     * @return [type] [description]
     */
    public function importGoodsData()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 获取商品库存
     * @return [type] [description]
     */
    public function getInventoryList()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 商品预警列表
     * @return [type] [description]
     */
    public function getInventoryWarningList()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 变动明细
     * @return [type] [description]
     */
    public function diffLog()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 盘点历史
     * @return [type] [description]
     */
    public function goodsReviewHistory()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 收银员列表
     */
    public function staffList()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 收银员业绩
     * @return [type] [description]
     */
    public function staffPerformance()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }



    /**
     * 会员概览
     * @return [type] [description]
     */
    public function memberOutlook()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 会员列表
     * @return [type] [description]
     */
    public function getMemberList()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 会员消费分析
     * @return [type] [description]
     */
    public function memberConsumeAnalysis()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 会员客单价分析
     * @return [type] [description]
     */
    public function memberPerorderAnalysis()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 分布分析
     * @return [type] [description]
     */
    public function memberDistributionAnalysis()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }

    /**
     * 店铺信息
     */
    public function User()
    {
    	return $this->ajaxFail('not implement yet', [], 1000);
    }







    /**
     * 交接班列表
     * @return [type] [description]
     */
    public function getShiftLogList()
    {

    }

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
