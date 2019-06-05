<?php
namespace app\api\controller;

use app\model\User;
use app\model\Category;
use app\model\Goods;
use app\model\Order;
use app\model\ShiftLog;

use think\config;
use  think\Request;

class Index extends BaseController
{

    private $token = "";
    private $store_code = "";
    private $record_no = "";
    private $page_no = "";

    /**
     * 检查登录状态
     * 记录  token
     *       store_code
     *       page_no
     *       record_no
     */
	public function __construct()
	{
        $token = Request::instance()->param("token");

        // 参数缺失
        if(empty($token))
        {
            // 报错
            $this->ajaxFail("token parameter can not be empty", 1000, []);
        }

        $this->token = $token;

        // 没登录，请先登录
        $ret = User::checkToken($token);
        if( $ret < 0)
        {
            // 不存在 1002
            // 查询报错 1003
            // 被冻结 1004
            $this->ajaxFail("you are not authorized, please login in", abs($ret), []);
        }

        $info = User::getStoreByToken($token);
        $this->store_code = $info->store_code;

        $this->record_no = Request::instance()->param("record_no");
        $this->page_no = Request::instance()->param("page_no");
	}

	/**
	 * 退出
	 * @return [type] [description]
	 */
	public function logout()
	{
    	$token = Request::instance()->param("token");
        $ret = User::logout($token);

        if($ret === false)
        {
            return $this->ajaxFail("failed", [], 1000);
        }

        return $this->ajaxSuccess('success', []);

	}

    public function index()
    {
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
        $this->checkPageNoRecordNo();

        $list = Order::getAllOrdersByStorecode($this->store_code, $this->record_no, $this->page_no);

        $tmp = [];
        $tmp[] = $list;
        return $this->ajaxSuccess('get order list success', $tmp);
    }

    /**
     * 交班记录
     * @return [type] [description]
     */
    public function getShiftRecord()
    {
        $this->checkPageNoRecordNo();

        $list = ShiftLog::getShiftLogByStoreCode($this->store_code, $this->record_no, $this->page_no);
    	
        $tmp = [];
        $tmp[] = $list;
        return $this->ajaxSuccess("get shift record success", $tmp);
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
        $this->checkPageNoRecordNo();

    	$list = Goods::getAllGoodsByStorecode($this->store_code, $this->record_no, $this->page_no);

        $tmp = [];
        $tmp[] = $list;
        return $this->ajaxSuccess('get goods list success', $tmp);
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
    	$list = Category::getAllCategories();

        $tmp = [];
        $tmp[] = $list;
        $this->ajaxSuccess("success", $tmp);
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
        $this->checkPageNoRecordNo();


    	$list = User::getStaffByStoken($this->token, $this->record_no, $this->page_no);

        if($list === false || $list == null)
        {
            return $this->ajaxFail("query failed", [], 1000);
        }

        $tmp = [];
        $tmp[] = $list;
        return $this->ajaxSuccess('get staff success', $tmp);
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
        $token = Request::instance()->param("token");
        $info = User::getStoreByToken($token);

        if($info === false || $info == null)
        {
            return $this->ajaxFail("store not found", [], 1002);
        }

        $tmp = [];
        $tmp[] = $info;
    	
        return $this->ajaxSuccess('success', $tmp);
    }


    /**
     * 交接班列表
     * @return [type] [description]
     */
    public function getShiftLogList()
    {

    }

    private function  checkPageNoRecordNo()
    {
        if(empty($this->record_no))
        {
            return $this->ajaxFail("record_no field can not be empty", [], 3000);
        }

        if(empty($this->page_no))
        {
            return $this->ajaxFail("page_no field can not be empty", [], 3001);
        }
    }


}
