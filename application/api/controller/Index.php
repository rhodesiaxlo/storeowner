<?php
namespace app\api\controller;

use app\model\User;
use app\model\Category;
use app\model\Goods;
use app\model\GoodsSnap;
use app\model\GoodsImport;
use app\model\GoodsRandom;
use app\model\Order;
use app\model\OrderGoods;
use app\model\ShiftLog;
use app\model\Member;
use app\model\MemberLog;
use app\model\Bank;
use app\model\Region;
use app\model\ConsumePerOrder;
use think\Db;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Common\Type;

use think\config;
use  think\Request;

class Index extends BaseController
{

    private $token = "";
    private $store_code = "";
    private $record_no = 10000000;
    private $page_no = 1;
    private $filter = [];

    // 根据请求类型构造 filter

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
            $this->ajaxFail("token parameter can not be empty", [], 1000);
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

        $this->record_no = Request::instance()->param("record_no")?Request::instance()->param("record_no"):100000;
        $this->page_no = Request::instance()->param("page_no")?Request::instance()->param("page_no"):1;
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
        list($start_date, $end_date) = $this->checkStartEndDate();
        $is_today = Request::instance()->param("is_today");
        $is_yesterday = Request::instance()->param("is_yesterday");
        $is_week = Request::instance()->param("is_week");
        $is_month = Request::instance()->param("is_month");

        if(is_null($is_today))
        {
            return $this->ajaxFail("type field can not be empty", [], 5000);
        } else {
            if(intval($is_today)>0&&2>intval($is_today))
            {
                $start_date = date("Y-m-d 0:0:0", time());
                $end_date = date("Y-m-d 23:59:59", time());    
            }
            
        }

        if(is_null($is_yesterday))
        {
            return $this->ajaxFail("is_yesterday field can not be empty", [], 5000);
        } else {
            if(intval($is_yesterday)>0&&2>intval($is_yesterday))
            {
                $start_date = date("Y-m-d 0:0:0", strtotime("-1 day"));
                $end_date = date("Y-m-d 23:59:59", strtotime("-1 day"));    
            }
        }

        $date=date('Y-m-d');  //当前日期

        $first=1; //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期

        $w=date('w',strtotime($date));  //获取当前周的第几天 周日是 0 周一到周六是 1 - 6

        $now_start=date('Y-m-d',strtotime("$date -".($w ? $w - $first : 6).' days')); //获取本周开始日期，如果$w是0，则表示周日，减去 6 天

        $now_end=date('Y-m-d',strtotime("$now_start +6 days"));  //本周结束日期

        $last_start=date('Y-m-d',strtotime("$now_start - 7 days"));  //上周开始日期

        $last_end=date('Y-m-d',strtotime("$now_start - 1 days"));  //上周结束日期


        if(is_null($is_week))
        {
            return $this->ajaxFail("is_week field can not be empty", [], 5000);
        } else {
            if(intval($is_week)>0&&2>intval($is_week))
            {
                // $end_date = date("Y-m-d 23:59:59", time());
                // $start_date = date("Y-m-d 23:59:59", strtotime("-7 day")); 

                $start_date = $now_start;
                $end_date = $now_end;   
            }
        }

        // exit("start date = {$start_date}  end date = {$end_date}");

        if(is_null($is_month))
        {
            return $this->ajaxFail("is_month field can not be empty", [], 5000);
        } else {
            if(intval($is_month)>0&&2>intval($is_month))
            {
                $end_date = date("Y-m-30 23:59:59", time()); 
                $start_date = date("Y-m-1 0:0:0", time());    
            }
        }


        // 获取filter
        $this->getCustomFilter(['start_date', 'end_date','is_today', 'is_yesterday', 'is_week', 'is_month']);


        list($finished_money, $refund_money, $refund_order_no, $non_member_order_no, $member_order_no, $discount_money_total, $discount_order_no) = Order::revenue($this->store_code, $start_date, $end_date);
        list($on_shelf_goods_no, $off_shelf_goods_no, $inven_total, $inven_warning) = GoodsSnap::getSnap($this->store_code, $end_date);

        list( $rank_by_money, $rank_by_no) = OrderGoods::getRank($this->store_code, $start_date, $end_date);

        list($old_member_no, $new_member_no, $new_member_order_no) = Member::memStatic($this->store_code, $start_date, $end_date);

        // 组装数据
        $return_data['total_money'] = $finished_money + $refund_money;
        $return_data['sales_complete'] = $finished_money;
        $return_data['refund_order_no'] = $refund_order_no;
        $return_data['refund_money'] = $refund_money;
        $return_data['order_total_no'] = $non_member_order_no+$member_order_no;
        $return_data['non_member_order_no'] = $non_member_order_no;
        $return_data['member_order_no'] = $member_order_no;
        $return_data['discount_money_total'] = $discount_money_total;
        $return_data['discount_order_no'] = $discount_order_no;
        $return_data['on_shelf_goods_no'] = $on_shelf_goods_no;
        $return_data['off_shelf_goods_no'] = $off_shelf_goods_no;
        $return_data['inven_total'] = $inven_total;
        $return_data['new_member_no'] = $new_member_no;
        $return_data['old_member_no'] = $old_member_no;
        $return_data['new_member_order_no'] = $new_member_order_no;
        $return_data['old_member_order_no'] =  $member_order_no - $new_member_order_no;
        $return_data['inven_warning'] = $inven_warning;
        $return_data['rank_by_money'] = $rank_by_money;
        $return_data['rank_by_no'] = $rank_by_no;

        // exit(json_encode($return_data));
        

        return $this->ajaxSuccess('get order list success', $this->getReturn($return_data, 1));

    }

    /**
     * 销售概况 (营业概况)
     * @return [type] [description]
     */
    public function salesOutlook()
    {
        $this->checkField(['start_date', 'end_date']);
        
        $start_date = Request::instance()->param("start_date");
        $end_date = Request::instance()->param("end_date");
        list($total_order_no, $total_revenue, $cash_revenue, $alibaba_revenue,  $wechat_revenue, $refund_money, $refund_order_no, $profit, $refund_cash, $refund_ali, $refund_wechat, $complte_profit, $complete_cost_basic) = order::getSalesOutlook($this->store_code, $start_date, $end_date);

        $tmp['total_order_no'] = $total_order_no;
        $tmp['total_revenue'] = $total_revenue;
        $tmp['cash'] = $cash_revenue;
        $tmp['ali'] = $alibaba_revenue;
        $tmp['wechat'] = $wechat_revenue;
        $tmp['refund_order_no'] = $refund_order_no;
        $tmp['refund_money'] = $refund_money;
        $tmp['profit'] = $profit;
        $tmp['refund_cash'] = $refund_cash;
        $tmp['refund_ali'] = $refund_ali;
        $tmp['refund_wechat'] = $refund_wechat;
        $tmp['complete_profit'] = $complte_profit;
        $tmp['complete_cost_basic'] = $complete_cost_basic;



    	return $this->ajaxSuccess('get order list success', $this->getReturn($tmp, 1));
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
        $this->checkField(['start_date', 'end_date']);
        $type = 0;
        
        $start_date = Request::instance()->param("start_date");
        $end_date = Request::instance()->param("end_date");

        if(strtotime($end_date) < strtotime($start_date))
        {
            return $this->ajaxFail('start_date has to be smaller than end date', [], 1000);
        }

        // 判断时间的范围
        $delta = strtotime($end_date) - strtotime($start_date);

        if(intval($delta/(60*60*24*28))>=1)
        {

            $type = 3;
        } elseif (intval($delta/(60*60*24))>=1) {
            // 按天算
            $type = 2;
        } elseif (intval($delta/(60*60))>=1) {
            $type = 1;
        } else {

        }


        if($type <1)
        {
            return $this->ajaxFail('date error', [], 1000);
        }


        $this->getCustomFilter(['start_date', 'end_date']);

        list($list, $number) = ConsumePerOrder::getList($this->store_code, $start_date, $end_date, $type);


        return $this->ajaxSuccess('get order list success', $this->getReturn($list, $number));
    }

    /**
     * 销售占比分析
     * @return [type] [description]
     */
    public function salesComposeAnalysis()
    {
        list($start_date, $end_date) = $this->checkStartEndDate();
        $type = Request::instance()->param("type");
        $is_order_by_money = Request::instance()->param("is_order_by_money");


        $is_today = Request::instance()->param("is_today");
        $is_yesterday = Request::instance()->param("is_yesterday");
        $is_week = Request::instance()->param("is_week");
        $is_month = Request::instance()->param("is_month");

                $date=date('Y-m-d');  //当前日期

        $first=1; //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期

        $w=date('w',strtotime($date));  //获取当前周的第几天 周日是 0 周一到周六是 1 - 6

        $now_start=date('Y-m-d',strtotime("$date -".($w ? $w - $first : 6).' days')); //获取本周开始日期，如果$w是0，则表示周日，减去 6 天

        $now_end=date('Y-m-d',strtotime("$now_start +6 days"));  //本周结束日期

        $last_start=date('Y-m-d',strtotime("$now_start - 7 days"));  //上周开始日期

        $last_end=date('Y-m-d',strtotime("$now_start - 1 days"));  //上周结束日期



        if(is_null($is_today))
        {
            return $this->ajaxFail("is_today field can not be empty", [], 5000);
        } else {
            if(intval($is_today)==1)
            {
                $start_date = date("Y-m-d 0:0:0", time());
                $end_date = date("Y-m-d 23:59:59", time());    
            }
            
        }

        if(is_null($is_yesterday))
        {
            return $this->ajaxFail("is_yesterday field can not be empty", [], 5000);
        } else {
            if(intval($is_yesterday)==1)
            {
                $start_date = date("Y-m-d 0:0:0", strtotime("-1 day"));
                $end_date = date("Y-m-d 23:59:59", strtotime("-1 day"));    
            }
        }


        if(is_null($is_week))
        {
            return $this->ajaxFail("is_week field can not be empty", [], 5000);
        } else {
            if(intval($is_week)==1)
            {
                // $end_date = date("Y-m-d 0:0:0", time());
                // $start_date = date("Y-m-d 23:59:59", strtotime("-7 day"));    
                
                $end_date = $now_end;
                $start_date = $now_start; 
            }
        }


        if(is_null($is_month))
        {
            return $this->ajaxFail("is_month field can not be empty", [], 5000);
        } else {
            if(intval($is_month)==1)
            {
                $month = date('m', time());
                $month_1 = $month + 1;
                $end_date = date("Y-{$month_1}-1 0:0:0");
                $end_date = date("Y-m-d H:i:s", strtotime($end_date)-1);
                $start_date = date("Y-m-1 23:59:59", time());    
            }
        }



        if(is_null($type))
        {
            return $this->ajaxFail("type1 field1 can not be empty", [], 5000);
        }


        if(is_null($is_order_by_money))
        {
            return $this->ajaxFail("is_order_by_money field can not be empty", [], 1002);
        }
        //exit("start_date {$start_date} end _date = {$end_date}");

        // get customer filter 
        $this->getCustomFilter(['type','start_date','end_date','is_today','is_yesterday','is_week','is_month','is_order_by_money']);

        $list = [];
        $total = 0;
        if(intval($type) ==1)
        {
            // 分类分
            list($list, $total_actual, $total_discount, $total_order_no, $total_goods_num, $list_no, $order_no) = OrderGoods::getOrderByCategory($this->store_code, $start_date, $end_date, $is_order_by_money);
        } elseif (intval($type) ==2) {
            // 支付分
            list($list, $total_actual, $total_discount, $total_order_no, $total_goods_num, $list_no) = OrderGoods::getOrderByPayment($this->store_code, $start_date, $end_date, $is_order_by_money);
        } elseif (intval($type) == 3) {
            // 收银分
            list($list, $total_actual, $total_discount, $total_order_no, $total_goods_num, $list_no) = OrderGoods::getOrderByCashier($this->store_code, $start_date, $end_date, $is_order_by_money);
        } elseif (intval($type) ==4) {
            //会员分
            list($list, $total_actual, $total_discount, $total_order_no, $total_goods_num, $list_no) = OrderGoods::getOrderByMembership($this->store_code, $start_date, $end_date, $is_order_by_money);
        } else {

        }



        $return['table'] = $list;
        $return['total_revenue'] = $total_actual;
        $return['total_discount'] = $total_discount;
        $return['total_goods_num'] = $total_goods_num;
        $return['total_order_no'] = $total_order_no;
        $return['list_no'] = $list_no;
    	return $this->ajaxSuccess('get order list success', $this->getReturn($return, 1));
    }

    /**
     * 销售统计
     * @return [type] [description]
     */
    public function salesReview()
    {        
        list($start_date, $end_date) = $this->checkStartEndDate();
        $goods_name = Request::instance()->param("goods_name");
        $goods_sn = Request::instance()->param("goods_sn");
        $is_order_by_money = Request::instance()->param("is_order_by_money");


        $is_today = Request::instance()->param("is_today");
        $is_yesterday = Request::instance()->param("is_yesterday");
        $is_week = Request::instance()->param("is_week");
        $is_month = Request::instance()->param("is_month");

        $date=date('Y-m-d');  //当前日期

        $first=1; //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期

        $w=date('w',strtotime($date));  //获取当前周的第几天 周日是 0 周一到周六是 1 - 6

        $now_start=date('Y-m-d',strtotime("$date -".($w ? $w - $first : 6).' days')); //获取本周开始日期，如果$w是0，则表示周日，减去 6 天

        $now_end=date('Y-m-d',strtotime("$now_start +6 days"));  //本周结束日期

        $last_start=date('Y-m-d',strtotime("$now_start - 7 days"));  //上周开始日期

        $last_end=date('Y-m-d',strtotime("$now_start - 1 days"));  //上周结束日期


        if(is_null($is_today))
        {
            return $this->ajaxFail("type field can not be empty", [], 5000);
        } else {
            if(intval($is_today)==1)
            {
                $start_date = date("Y-m-d 0:0:0", time());
                $end_date = date("Y-m-d 23:59:59", time());    
            }
            
        }

        if(is_null($is_yesterday))
        {
            return $this->ajaxFail("is_yesterday field can not be empty", [], 5000);
        } else {
            if(intval($is_yesterday)==1)
            {
                $start_date = date("Y-m-d 0:0:0", strtotime("-1 day"));
                $end_date = date("Y-m-d 23:59:59", strtotime("-1 day"));    
            }
        }


        if(is_null($is_week))
        {
            return $this->ajaxFail("is_week field can not be empty", [], 5000);
        } else {
            if(intval($is_week)==1)
            {
                // $end_date = date("Y-m-d 0:0:0", time());
                // $start_date = date("Y-m-d 23:59:59", strtotime("-7 day"));    


                $end_date = $last_end;
                $start_date = $last_start;   

            }
        }


        if(is_null($is_month))
        {
            return $this->ajaxFail("is_month field can not be empty", [], 5000);
        } else {
            if(intval($is_month)==1)
            {
                $end_date = date("Y-m-30 23:59:59", time());
                $start_date = date("Y-m-1 23:59:59", time());    
            }
        }


        if(is_null($is_order_by_money))
        {
            return $this->ajaxFail("is_order_by_money field can not be empty", [], 5000);
        }

        if(is_null($goods_name))
        {
            return $this->ajaxFail("goods_name field can not be empty", [], 5000);
        }

        if(is_null($goods_sn))
        {
            return $this->ajaxFail("goods_sn field can not be empty", [], 5000);
        }

        // get customer filter
        $this->getCustomFilter(['start_date', 'end_date', 'goods_name', 'goods_sn','is_today','is_yesterday','is_week','is_month','is_order_by_money']);

        list($list, $total_actual, $total_discount, $total_order_no, $total_goods_num, $list_no, $total_customerpay) = OrderGoods::getStatic($this->store_code, $start_date, $end_date, $goods_name, $goods_sn, $is_order_by_money);

        $return['table'] = $list;
        $return['total_revenue'] = $total_actual;
        $return['total_discount'] = $total_discount;
        $return['total_goods_num'] = $total_goods_num;
        $return['total_order_no'] = $total_order_no;
        $return['list_no'] = $list_no;
        $return['total_customerpay'] = $total_customerpay;
        $return['list_no'] = sizeof($list);
        return $this->ajaxSuccess('get order list success', $this->getReturn($return, 1));

    }

    /**
     * 笔单价分析
     * @return [type] [description]
     */
    public function perorderAnalysis()
    {

        $this->checkField(['is_current_week','is_previous_week','is_current_month','is_previous_month','start_date','end_date','min','max','num']);


        $is_current_week   = Request::instance()->param("is_current_week");
        $is_previous_week  = Request::instance()->param("is_previous_week");
        $is_current_month  = Request::instance()->param("is_current_month");
        $is_previous_month = Request::instance()->param("is_previous_month");
        $start_date        = Request::instance()->param("start_date");
        $end_date          = Request::instance()->param("end_date");
        $min               = Request::instance()->param("min");
        $max               = Request::instance()->param("max");
        $num               = Request::instance()->param("num");

        // 字段验证
        if(!is_numeric($is_current_week))
        {
            return $this->ajaxFail("is_current_week field illegal", [], 5000);
        }

        if(!is_numeric($is_previous_week))
        {
            return $this->ajaxFail("is_previous_week field illegal", [], 5000);
        }

        if(!is_numeric($is_current_month))
        {
            return $this->ajaxFail("is_current_month field illegal", [], 5000);
        }

        if(!is_numeric($is_previous_month))
        {
            return $this->ajaxFail("is_previous_month field illegal", [], 5000);
        }

        if(!is_numeric($min))
        {
            return $this->ajaxFail("min field illegal", [], 5000);
        }

        if(!is_numeric($max))
        {
            return $this->ajaxFail("max field illegal", [], 5000);
        }

        if(!is_numeric($num))
        {
            return $this->ajaxFail("num field illegal", [], 5000);
        }



        $date=date('Y-m-d');  //当前日期

        $first=1; //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期

        $w=date('w',strtotime($date));  //获取当前周的第几天 周日是 0 周一到周六是 1 - 6

        $now_start=date('Y-m-d',strtotime("$date -".($w ? $w - $first : 6).' days')); //获取本周开始日期，如果$w是0，则表示周日，减去 6 天

        $now_end=date('Y-m-d',strtotime("$now_start +6 days"));  //本周结束日期

        $last_start=date('Y-m-d',strtotime("$now_start - 7 days"));  //上周开始日期

        $last_end=date('Y-m-d',strtotime("$now_start - 1 days"));  //上周结束日期


        if(is_null($is_current_week))
        {
            return $this->ajaxFail("is_current_week field can not be empty", [], 5000);
        } else {
            if(intval($is_current_week)==1)
            {
                // $end_date = date("Y-m-d 23:59:59", time());
                // $start_date = date("Y-m-d 23:59:59", strtotime("-7 day"));    

                $end_date = $now_end;
                $start_date = $now_start; 
            }
        }


        if(is_null($is_previous_week))
        {
            return $this->ajaxFail("is_previous_week field can not be empty", [], 5000);
        } else {
            if(intval($is_previous_week)==1)
            {
                // $end_date = date("Y-m-d 23:59:59", strtotime("-7 day"));
                // $start_date = date("Y-m-d 23:59:59", strtotime("-14 day"));    
                
                $end_date = $last_end;
                $start_date = $last_start;   
            }
        }

        // exit("start date = {$start_date}  end date = {$end_date}");

        if(is_null($is_current_month))
        {
            return $this->ajaxFail("is_current_month field can not be empty", [], 5000);
        } else {
            if(intval($is_current_month)==1)
            {
                $month = date('m', time());
                $month_1 = $month + 1;
                $end_date = date("Y-{$month_1}-1 0:0:0", time()); 
                $end_date = date("Y-m-d H:i:s", strtotime($end_date)-1);
                $start_date = date("Y-m-1 0:0:0", time());    
            }
        }

        if(is_null($is_previous_month))
        {
            return $this->ajaxFail("is_previous_month field can not be empty", [], 5000);
        } else {
            if(intval($is_previous_month)==1)
            {
                $month = date('m', time());
                $month_1 = $month - 1;

                $end_date = date('Y-m-1 0:0:0',time());
                $end_date = date("Y-m-d H:i:s", strtotime($end_date)-1); 
                $start_date = date("Y-{$month_1}-1 0:0:0", time());    
            }
        }

        // exit("start_date = {$start_date}  end date = {$end_date}");




        $this->getCustomFilter(['is_current_week','is_previous_week','is_current_month','is_previous_month','start_date','end_date','min','max','num']);
        
    	list($list, $num) = Order::OrderDis($this->store_code, $start_date, $end_date, $min, $max, $num);

        return $this->ajaxSuccess(' success', $this->getReturn($list, $num));

    }

    /**
     * 获取订单列表
     * @return [type] [description]
     */
    public function getOrderList()
    {
        // 验证参数 start_date end_date order_sn cashier_id payment_way
        $this->checkPageNoRecordNo();
        $start_date = Request::instance()->param("start_date");
        $end_date = Request::instance()->param("end_date");
        $order_sn = Request::instance()->param("order_sn");
        $cashier_id = Request::instance()->param("cashier_id");
        $payment_way = Request::instance()->param("payment_way");

        if(is_null($start_date))
        {
            return $this->ajaxFail("start_date field not found!", [], 1010);
        }

        if(is_null($end_date))
        {
            return $this->ajaxFail("end_date field not found!", [], 1010);
        }

        if(is_null($order_sn))
        {
            return $this->ajaxFail("order_sn field not found!", [], 1010);
        }

        if(is_null($cashier_id))
        {
            return $this->ajaxFail("cashier_id field not found!", [], 1010);
        }

        if(is_null($payment_way))
        {
            return $this->ajaxFail("payment_way field not found!", [], 1010);
        }

        // 开始日期不能晚于结束日期
        if(strtotime($end_date) < strtotime($start_date))
        {
            return $this->ajaxFail("start date can not late than end_date!", [], 1010);   
        }

        // 构造 filter 
        $this->getCustomFilter(['start_date', 'end_date', 'order_sn', 'cashier_id', 'payment_way']);

        list($list, $total_number) = Order::getAllOrdersByStorecode($this->store_code, $this->record_no, $this->page_no, $start_date, $end_date, $order_sn, $cashier_id, $payment_way);

        return $this->ajaxSuccess('get order list success', $this->getReturn($list, $total_number));
    }

    /**
     * 交班记录
     * @return [type] [description]
     */
    public function getShiftRecord()
    {
        $this->checkPageNoRecordNo();
        $start_date = Request::instance()->param("start_date");
        $end_date = Request::instance()->param("end_date");
        $staff_id = Request::instance()->param("staff_id");

        if(is_null($staff_id))
        {
            return $this->ajaxFail("staff_id field not found!", [], 1010);
        }

        if(is_null($start_date))
        {
            return $this->ajaxFail("start_date field not found!", [], 1010);
        }

        if(is_null($end_date))
        {
            return $this->ajaxFail("end_date field not found!", [], 1010);
        }

        // 开始日期不能晚于结束日期
        if(strtotime($end_date) < strtotime($start_date))
        {
            return $this->ajaxFail("start date can not late than end_date!", [], 1010);   
        }

        $this->getCustomFilter(['start_date', 'end_date','staff_id']);

        list($list, $total_number) = ShiftLog::getShiftLogByStoreCode($this->store_code, $this->record_no, $this->page_no, $start_date, $end_date, $staff_id);

        return $this->ajaxSuccess("get shift record success", $this->getReturn($list, $total_number));
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
        // 判断参数
        $this->checkPageNoRecordNo();
        $type = Request::instance()->param("type");
        $barcode = Request::instance()->param("barcode");
        $goods_name = Request::instance()->param("goods_name");
        $is_forsale = Request::instance()->param("is_forsale");
        $cat_id = Request::instance()->param("cat_id");



        if(is_null($is_forsale))
        {
            return $this->ajaxFail("is_forsale field not found!", [], 1010);

        }

        if(is_null($type))
        {
            return $this->ajaxFail("type field not found!", [], 1010);
        }

        if(is_null($barcode))
        {
            return $this->ajaxFail("barcode field not found!", [], 1011);
        }

        if(is_null($goods_name))
        {
            return $this->ajaxFail("goods_name field not found!", [], 1012);   
        }

        // 判断 filter 
        $this->getCustomFilter(['type', 'barcode', 'goods_name','is_forsale','cat_id']);

    	list($list, $total_number) = Goods::getAllGoodsByStorecode($this->store_code, $this->record_no, $this->page_no, $cat_id, $barcode, $goods_name, $type, $is_forsale);

        return $this->ajaxSuccess('get goods list success', $this->getReturn($list, $total_number));
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
     * 获取商品库存
     * @return [type] [description]
     */
    public function getInventoryList()
    {
        // page_no  record_no cat_id goods_name goods_sn is_forsale
        $this->checkPageNoRecordNo();
        $cat_id = Request::instance()->param("cat_id");
        $goods_name = Request::instance()->param("goods_name");
        $goods_sn = Request::instance()->param("goods_sn");
        $is_forsale = Request::instance()->param("is_forsale");

        if(is_null($cat_id))
        {
            return $this->ajaxFail("cat_id field not found!", [], 1010);
        }

        if(is_null($goods_name))
        {
            return $this->ajaxFail("goods_name field not found!", [], 1010);
        }

        if(is_null($goods_sn))
        {
            return $this->ajaxFail("goods_sn field not found!", [], 1010);
        }

        if(is_null($is_forsale))
        {
            return $this->ajaxFail("is_forsale field not found!", [], 1010);
        }

        // 获取 custer filter
        $this->getCustomFilter(['cat_id', 'goods_name', 'goods_sn', 'is_forsale']);

        list($list, $total_number) = Goods::getInven($this->store_code, $this->page_no, $this->record_no, $cat_id, $goods_name, $goods_sn, $is_forsale, $is_forsale);


        return $this->ajaxSuccess('get staff success', $this->getReturn($list, $total_number));

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
    public function staff()
    {
        // 检查输入参数
        $this->checkPageNoRecordNo();
        $status = Request::instance()->param("status");
        $name = Request::instance()->param("name");
        if(is_null($status))
        {
            return $this->ajaxFail("status field not found!", [], 1010);
        }

        if(is_null($name))
        {
            return $this->ajaxFail("name field not found!", [], 1010);
        }

        // 检查 filter
        $this->getCustomFilter(['name', 'status']);
        

    	list($list, $total_number) = User::getStaffByStoken($this->token, $this->record_no, $this->page_no, $name, $status);

        if($list === false)
        {
            return $this->ajaxFail("query failed", [], 1000);
        }
        
        return $this->ajaxSuccess('get staff success', $this->getReturn($list, $total_number));
    }

    /**
     * 收银员业绩
     * @return [type] [description]
     */
    public function staffPerformance()
    {
        $this->checkPageNoRecordNo();
        $start_date = Request::instance()->param("start_date");
        $end_date = Request::instance()->param("end_date");
        $staff_id = Request::instance()->param("staff_id");

        if(is_null($staff_id))
        {
            return $this->ajaxFail("staff_id field not found!", [], 1010);
        }

        if(is_null($start_date))
        {
            return $this->ajaxFail("start_date field not found!", [], 1010);
        }

        if(is_null($end_date))
        {
            return $this->ajaxFail("end_date field not found!", [], 1010);
        }

        // 开始日期不能晚于结束日期
        if(strtotime($end_date) < strtotime($start_date))
        {
            return $this->ajaxFail("start date can not late than end_date!", [], 1010);   
        }

        $this->getCustomFilter(['start_date', 'end_date','staff_id']);

        list($list, $total_number) = Order::getShiftOrder($this->store_code, $this->record_no, $this->page_no, $start_date, $end_date, $staff_id);

        return $this->ajaxSuccess("get shift record success", $this->getReturn($list, $total_number));
    }



    /**
     * 会员概览
     * @return [type] [description]
     */
    public function memberOutlook()
    {

        list($start_date, $end_date) = $this->checkStartEndDate();

        $is_today = Request::instance()->param("is_today");
        $is_yesterday = Request::instance()->param("is_yesterday");
        $is_week = Request::instance()->param("is_week");
        $is_month = Request::instance()->param("is_month");

        $date=date('Y-m-d');  //当前日期

        $first=1; //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期

        $w=date('w',strtotime($date));  //获取当前周的第几天 周日是 0 周一到周六是 1 - 6

        $now_start=date('Y-m-d',strtotime("$date -".($w ? $w - $first : 6).' days')); //获取本周开始日期，如果$w是0，则表示周日，减去 6 天

        $now_end=date('Y-m-d',strtotime("$now_start +6 days"));  //本周结束日期

        $last_start=date('Y-m-d',strtotime("$now_start - 7 days"));  //上周开始日期

        $last_end=date('Y-m-d',strtotime("$now_start - 1 days"));  //上周结束日期



        if(is_null($is_today))
        {
            return $this->ajaxFail("type field can not be empty", [], 5000);
        } else {
            if(intval($is_today)>0&&2>intval($is_today))
            {
                $start_date = date("Y-m-d 0:0:0", time());
                $end_date = date("Y-m-d 23:59:59", time());    
            }
            
        }

        if(is_null($is_yesterday))
        {
            return $this->ajaxFail("is_yesterday field can not be empty", [], 5000);
        } else {
            if(intval($is_yesterday)>0&&2>intval($is_yesterday))
            {
                $start_date = date("Y-m-d 0:0:0", strtotime("-1 day"));
                $end_date = date("Y-m-d 23:59:59", strtotime("-1 day"));    
            }
        }


        if(is_null($is_week))
        {
            return $this->ajaxFail("is_week field can not be empty", [], 5000);
        } else {
            if(intval($is_week)>0&&2>intval($is_week))
            {
                $end_date = $now_end;
                $start_date = $now_start;    
            }
        }


        if(is_null($is_month))
        {
            return $this->ajaxFail("is_month field can not be empty", [], 5000);
        } else {
            if(intval($is_month)>0&&2>intval($is_month))
            {
                $end_date = date("Y-m-30 23:59:59", time());
                $start_date = date("Y-m-1 23:59:59", time());    
            }
        }

        //exit("start = {$start_date}  end date = {$end_date}");



    	// 静态信息
        list($new_member1, $all_member1) = Member::newMemberAndOldMember($this->store_code, $start_date, $end_date);


        list($new_member_count, $old_member_count, $new_member_order_sum, $old_member_order_sum, $new_member_order_number, $old_member_order_number) = Order::MemberRevenue($this->store_code, $start_date, $end_date);

        list($list, $total_number) = Member::rankByCompu($this->store_code, $start_date, $end_date);

        $return['new_member_number_1'] = $new_member1;
        $return['all_member_1'] = $all_member1;
        $return['new_member_count'] = $new_member_count;
        $return['old_member_count'] = $old_member_count;
        $return['new_member_order_sum'] = $new_member_order_sum;
        $return['old_member_order_sum'] = $old_member_order_sum;
        $return['new_member_order_number'] = $new_member_order_number;
        $return['old_member_order_number'] = $old_member_order_number;
        $return['table'] =  $list;
        $return['total_comption'] = $total_number;
        return $this->ajaxSuccess("get shift record success", $this->getReturn($return, 1));



    }

    public function rankByComptionNo()
    {
        list($start_date, $end_date) = $this->checkStartEndDate();

        $is_today = Request::instance()->param("is_today");
        $is_yesterday = Request::instance()->param("is_yesterday");
        $is_week = Request::instance()->param("is_week");
        $is_month = Request::instance()->param("is_month");

        list($list, $total_number) = Member::rankByCompuNo($this->store_code, $start_date, $end_date);
        return $this->ajaxSuccess("get shift record success", $this->getReturn($list, $total_number));


    }

    /**
     * 会员列表
     * @return [type] [description]
     */
    public function getMemberList()
    {
        $this->checkPageNoRecordNo();
        $name = Request::instance()->param("name");
        $mobile = Request::instance()->param("mobile");


        if(is_null($name))
        {
            return $this->ajaxFail("name field can not be empty", [], 10111);
        }

        if(is_null($mobile))
        {
            return $this->ajaxFail("mobile field can not be empty", [], 10111);
        }

        // 获取 filter
        $this->getCustomFilter(['name', 'mobile']);

        list($list, $total_number) = Member::getList($this->store_code,$this->page_no, $this->record_no, $name, $mobile);

    	return $this->ajaxSuccess('get member success', $this->getReturn($list, $total_number));
    }

    /**
     * 更新会员
     * @return [type] [description]
     */
    public function updateMember()
    {
        $this->checkUpdateMember(['id','uname','phone','idcard','pic','gender','discount','comment','birthday']);

        $id = Request::instance()->param("id");

        if(intval($id) === false || intval($id) < 1)
        {
            return $this->ajaxFail("member id ilegal", [], 1000);
        }

        $where_update['id'] = $id;
        $where_update['store_code'] = $this->store_code;

        $store_info = User::getStoreByToken($this->token);

        $update_arr = [];

        $update_arr = $this->storeUpdateData(['uname','phone','idcard','pic','gender','discount','comment','birthday']);

        $ret = Member::where($where_update)->update($update_arr);

        if($ret === false)
        {
            // failed
            return $this->ajaxFail("member id ilegal", [], 1000);
        }

        return $this->ajaxSuccess('success', []);

    }

    /**
     * 会员消费分析
     * @return [type] [description]
     */
    public function memberConsumeAnalysis()
    {

    	return $this->memCompAnaly();
    }

    /**
     * 会员客单价分析
     * @return [type] [description]
     */
    public function memberPerorderAnalysis()
    {

        $this->checkField(['one_week','two_weeks','one_month','start_date','end_date','min','max','num']);

        // 字段验证
        $one_week   = Request::instance()->param("one_week");
        $two_weeks  = Request::instance()->param("two_weeks");
        $one_month  = Request::instance()->param("one_month");
        $start_date        = Request::instance()->param("start_date");
        $end_date          = Request::instance()->param("end_date");
        $min               = Request::instance()->param("min");
        $max               = Request::instance()->param("max");
        $num               = Request::instance()->param("num");


        $date=date('Y-m-d');  //当前日期

        $first=1; //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期

        $w=date('w',strtotime($date));  //获取当前周的第几天 周日是 0 周一到周六是 1 - 6

        $now_start=date('Y-m-d',strtotime("$date -".($w ? $w - $first : 6).' days')); //获取本周开始日期，如果$w是0，则表示周日，减去 6 天

        $now_end=date('Y-m-d',strtotime("$now_start +6 days"));  //本周结束日期

        $last_start=date('Y-m-d',strtotime("$now_start - 7 days"));  //上周开始日期

        $last_end=date('Y-m-d',strtotime("$now_start - 1 days"));  //上周结束日期


        if(!is_numeric($min))
        {
            return $this->ajaxFail("min id ilegal", [], 1000);
        }

        if(!is_numeric($max))
        {
            return $this->ajaxFail("max id ilegal", [], 1001);
        }

        if(!is_numeric($num)&&intval($num)==0)
        {
            return $this->ajaxFail("num id ilegal", [], 1002);
        }


        if(is_numeric($one_week)&&intval($one_week)==1)
        {
            $tmp = strtotime($start_date);
            $start_date = date("Y-m-d 0:0:0", time() - 60*60*24*7);
            $end_date = date("Y-m-d 0:0:0", time());
            $end_date = date("Y-m-d H:i:s", strtotime($end_date)-1);
        }

        if(is_numeric($two_weeks)&&intval($two_weeks)==1)
        {
            $tmp = strtotime($start_date);
            $start_date = date("Y-m-d 0:0:0", time() - 60*60*24*7*2);   
            $end_date = date("Y-m-d 0:0:0", time());
            $end_date = date("Y-m-d H:i:s", strtotime($end_date)-1);   
        }

        if(is_numeric($one_month)&&intval($one_month)==1)
        {

            $tmp = strtotime($start_date);
            $start_date = date("Y-m-d 0:0:0", time() - 60*60*24*30);   
            $end_date = date("Y-m-d 0:0:0", time());
            $end_date = date("Y-m-d H:i:s", strtotime($end_date)-1);  

        }


        $this->getCustomFilter(['one_week','two_weeks','one_month','start_date','end_date','min','max','num']);


        list($list, $member_total) = Member::conByPerson($this->store_code, $start_date, $end_date, $min, $max, $num);

    	return $this->ajaxSuccess(" success", $this->getReturn($list, $member_total));
    }

    /**
     * 分布分析
     * @return [type] [description]
     */
    public function memberDistributionAnalysis()
    {
    	return $this->pointDis();
    }

    /**
     * 库存切片定时任务，每天晚10:00pm 后，每5分执行一次
     * @return [type] [description]
     */
    public function cronMinAfterTenPm()
    {
        $ret = GoodsSnap::cronJob();
        //
        if($ret === false)
        {

        } else {
            return $this->ajaxSuccess(" success", []);
        }

    }

    /**
     * 定时任务，每10分钟执行，计算每一个小时的订单统计信息
     * 
     * @return [type] [description]
     */
    public function cronHourlyOrderTrend()
    {
        $later = date("Y-m-d H:0:0", time());
        $hour = date("H", time());
        $hour_1 = $hour -1;
        $earlier = date("Y-m-d H:0:0", strtotime($later)-60*60);

        $result = ConsumePerOrder::createRecord(null, $earlier, $later, 1);
         return $this->ajaxSuccess("get shift record success", $this->getReturn($result, 0));
    }

    /**
     * 每小时执行一次
     * @return [type] [description]
     */
    public function cronDailyOrderTrend()
    {
        $later = date("Y-m-d 23:59:59", time());

        if(strtotime($later)> time()){
            // 取昨天的23:59:59
            $later = date("Y-m-d 0:0:0", time());
            $later = date("Y-m-d 23:59:59", strtotime($later)-5);
        } else {
            $later = $later;
        }


        $earlier = date("Y-m-d H:i:s", strtotime($later)-60*60*24);

        //exit("earlier {$earlier}  later {$later}");
        
        $result = ConsumePerOrder::createRecord(null, $earlier, $later, 2);
         return $this->ajaxSuccess("get shift record success", $this->getReturn($result, 0));
        
    }

    public function cronMonthOrderTrend()
    {
        // 获得本月月初
        $later = date("Y-m-1 0:0:0", time());
        if(!is_null(Request::instance()->param("later")))
        {
            $later = date("Y-m-1 0:0:0", strtotime(Request::instance()->param("later")));
        }
        $month = date("m", strtotime($later));
        $year = date("Y", strtotime($later));
        $earlier_month = intval($month) - 1;
        $earlier_year = intval($year) - 1;
        $earlier;
        if($month > 1)
        {
            $earlier = date("Y-{$earlier_month}-1 H:0:0", strtotime($later)-60*60*24);    
        } else {
            $earlier = date("{$earlier_year}-12-1 H:0:0", strtotime($later)-60*60*24);    
        }
        
        $result = ConsumePerOrder::createRecord(null, $earlier, $later, 3);
         return $this->ajaxSuccess("get shift record success", $this->getReturn($result, 0));
        
    }

    /**
     * 店铺信息
     */
    public function store()
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

    private function storeUpdateData($field_list)
    {
        $update_arr = [];

        foreach ($field_list as $key => $value) {
            $tmp = Request::instance()->param($value);
 
            if($value == "gender")
            {
                if(intval($tmp)!==false)
                {
                    $update_arr[$value] = $tmp;   
                }
            } else {
                if(!empty($tmp))
                {
                    $update_arr[$value] = $tmp;
                }    
            }
               
        }

        return $update_arr;

    }

    /**
     * [updateStore description]
     * @return [type] [description]
     */
    public function updateStore()
    {
        $this->checkField(['id', 'store_name', 'province_id', 'city_id', 'area_id', 'address', 'business_licence_no', 'realname', 'phone', 'bank_id', 'account_name', 'account_no']);

        $id = Request::instance()->param("id");
        if(intval($id) === false || intval($id) < 1)
        {
            return $this->ajaxFail("id illegal", [], 1000);
        }

        $update_where['store_code'] = $this->store_code;
        $update_where['local_id'] = $id;

        $update_arr = $this->storeUpdateData(['store_name', 'province_id', 'city_id', 'area_id', 'address', 'business_licence_no', 'realname', 'phone', 'bank_id', 'account_name', 'account_no']);

        $store_info = User::getStoreByToken($this->token);

        $ret = User::where($update_where)->update($update_arr);

        if($ret === false)
        {
            return $this->ajaxFail('udpate failed', [], 1000);
        }

         return $this->ajaxSuccess("bank list success", $ret);
    }

    /**
     * 导入商品数据
     * @return [type] [description]
     */
    public function importGoodsData()
    {
        if(request()->isPost()){
            // 保存文件，读取文件
            $tag = "file";
            $local_id = Request::instance()->param("local_id");
            if(empty($_FILES['file']))
            {
                return $this->ajaxFail('excel file not found', [], 1000);
            }

            $uploaddir = 'app/';
            $filename = $_FILES[$tag]['name'];
            $ext_arr = explode(".", $filename);
            if(!is_array($ext_arr)||sizeof($ext_arr)<2)
            {
                return $this->ajaxFail('no extention was found', [], 1001);
            }

            $ext = $ext_arr[sizeof($ext_arr)-1];

            if(trim($ext)!="xlsx")
            {
                return $this->ajaxFail('template extension must be of xlsx', [], 1002);
            }

            $new_file = date('Ymd').time().rand(10,99999).'.'.$ext;
            $uploadfile = './static/excel/' .$new_file;
            // 保存文件名
            if (move_uploaded_file($_FILES[$tag]['tmp_name'], $uploadfile)) {
              // return response()->json(['code'=>1, 'error_code'=>0, 'message'=>'success', 'data'=>['image_path'=>"http://{$_SERVER['HTTP_HOST']}/img/{$new_file}"]]);
                $userinfo =User::getStoreByToken($this->token);
                $this->importExcel($uploadfile, $userinfo->local_id);
            } else {
              return $this->ajaxFail('upload failed', [], 1003);
            }


            return $this->ajaxFail("no file data found", [], 1004);
        } else {
            return $this->ajaxFail("only post request is supported", [], 1004);

        }
    }


    /**
     * 导入会员数据
     * @return [type] [description]
     */
    public function importMember()
    {
        if(request()->isPost()){
            // 保存文件，读取文件
            $tag = "file";
            $local_id = Request::instance()->param("local_id");
            if(empty($_FILES['file']))
            {
                return $this->ajaxFail('excel file not found', [], 1000);
            }

            $uploaddir = 'app/';
            $filename = $_FILES[$tag]['name'];
            $ext_arr = explode(".", $filename);
            if(!is_array($ext_arr)||sizeof($ext_arr)<2)
            {
                return $this->ajaxFail('no extention was found', [], 1001);
            }

            $ext = $ext_arr[sizeof($ext_arr)-1];

            if(trim($ext)!="xlsx")
            {
                return $this->ajaxFail('member template extension must be of xlsx', [], 1002);
            }

            $new_file = date('Ymd').time().rand(10,99999).'.'.$ext;
            $uploadfile = './static/excel/' .$new_file;
            // 保存文件名
            if (move_uploaded_file($_FILES[$tag]['tmp_name'], $uploadfile)) {
              // return response()->json(['code'=>1, 'error_code'=>0, 'message'=>'success', 'data'=>['image_path'=>"http://{$_SERVER['HTTP_HOST']}/img/{$new_file}"]]);
                $userinfo =User::getStoreByToken($this->token);

                // 导入会员数据
                $this->importExcel($uploadfile, $userinfo->local_id);
            } else {
              return $this->ajaxFail('upload failed', [], 1003);
            }


            return $this->ajaxFail("no file data found", [], 1004);
        } else {
            return $this->ajaxFail("only post request is supported", [], 1004);

        }
    }

    /**
     * 商品概览数据
     * @return [type] [description]
     */
    public function goodsReview()
    {

    }





    /**
     * 导入
     * @param  [type] $path [description]
     * @return [type]       [description]
     */
    private function importExcel($path, $store_id)
    {
        $store_info = User::where(['token'=>$this->token])->find();
        if(is_null($store_info))
        {
            return $this->ajaxFail("store not found", [], 2004);
        }

        // 数据开始行
        $start_line = 3;
        // 默认插入完全成功，遇到错误 变false
        $is_success = true;

        // 总行数
        $total = 0;
        // 出错行数
        $error_num = 0;
        // 新建行数
        $create_num = 0;
        // 更新行数
        $update_num = 0;
        // 提示信息
        $message = [];
        $error_num  = 0;
        
        $reader = ReaderFactory::create(Type::XLSX); // for XLSX files
        //$reader = ReaderEntityFactory::createXLSXReader();
        $reader->open($path);


        $catlist = Category::where(1)->field('name')->select();
        $tmplist = [];
        foreach ($catlist as $key => $value) {
            $tmplist[] = $value->name;
        }

        $abc = array_flip($tmplist);

        // 事务
        
        $sheet_num = 0;
        $fields = ['商品名称','商品条码','商品分类','计价方式','商品规格', '商品单位','进货价','零售价','起始库存','库存预警','货架号','过期时间','是否上架','是否快捷'];

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheet_num +=1;
            if($sheet_num > 1)
            {
                // 返回
            }


            $cur = 0;
            foreach ($sheet->getRowIterator() as $row) {
                // 统计信息
                $total +=1;

                $cur += 1;
                if($cur < 4)
                {
                    // 前三行为模板内容
                    continue;
                }


                // 校验数据 碰到空行，跳出循环
                if(empty($row[1]) && empty($row[2]) && empty($row[3]))
                {
                    break;
                }

                $ret = $this->validateRow($row, $cur);
                if($ret!==true)
                {
                    // 组装错误信息
                    $message[] = $ret;
                    $error_num+=1;
                    // continue;
                }else {
                
                    // 写入数据
                    $is_exist = GoodsImport::where(['goods_sn'=>$row[2],'user_id'=>$store_id])->find();

                    $this->insertGoodsIfStoreGoodsEmpty($store_info->store_code, $row, $store_id, $store_info, $abc);

                    if(!is_null($is_exist))
                    {
                        
                        $is_exist->user_id           = $store_id; // usr_id
                        $is_exist->store_code        = $store_info->store_code; // store_code
                        $is_exist->goods_name        = $row[1];
                        
                        $is_exist->goods_sn          = $row[2];
                        $is_exist->cat_id            = $abc[$row[3]] + 1;   // cat_id
                        $is_exist->type              = $row[4];
                        $is_exist->goods_picture     = ""; // 商品图片
                        $is_exist->spec              = $row[5];
                        $is_exist->create_time       = time();
                        $is_exist->unit              = $row[6];
                        $is_exist->cost_price        = $row[7];
                        $is_exist->shop_price        = $row[8];
                        $is_exist->repertory         = $row[9];
                        $is_exist->repertory_caution = $row[10];
                        $is_exist->place_code        = $row[11];
                        
                        if(strtolower( gettype($row[12])) == "object")
                        {
                            $obj = json_decode(json_encode($row[12], true));
                            $date = strval($obj->date);
                            $is_exist->staleTime = strtotime($date)*1000;
                        } else {
                            if(strtolower( gettype($row[12])) == "string" && empty($row[12]))
                            {
                                $row['12'] = 0;
                            }
                            $is_exist->staleTime         = 0;
                        }
                        $is_exist->custom            = "1";
                        $is_exist->is_forsale        = $row[13];
                        $is_exist->sale_time         = time();
                        $is_exist->is_short          = $row[14];
                        $is_exist->short_time        = time();
                        $is_exist->check             = "0";
                        $is_exist->last_modified     = time();


                        // 保存，保存成功后，更新记
                        $save_rest = $is_exist->save();
                        if($save_rest !== false)
                        {
                            $update_num +=1;    
                        } else {
                            // 保存出错
                            $error_num +=1;
                        }

                    } else {
                        $new_rec = new GoodsImport();
                        $new_rec->user_id           = $store_id; // usr_id
                        $new_rec->store_code        = $store_info->store_code;  // store_code
                        $new_rec->goods_name        = $row[1];
                        
                        $new_rec->goods_sn          = $row[2];
                        $new_rec->cat_id            = $abc[$row[3]] + 1;   // cat_id
                        $new_rec->type              = $row[4];
                        $new_rec->goods_picture     = ""; // 商品图片
                        $new_rec->spec              = $row[5];
                        $new_rec->create_time       = time();
                        $new_rec->unit              = $row[6];
                        $new_rec->cost_price        = $row[7];
                        $new_rec->shop_price        = $row[8];
                        $new_rec->repertory         = $row[9];
                        $new_rec->repertory_caution = $row[10];
                        $new_rec->place_code        = $row[11];
                        if(strtolower( gettype($row[12])) == "object")
                        {
                            $obj = json_decode(json_encode($row[12], true));
                            $date = strval($obj->date);
                            $new_rec->staleTime = strtotime($date)*1000;
                        } else {
                            if(strtolower( gettype($row[12])) == "string" && empty($row[12]))
                            {
                                $row['12'] = 0;
                            }

                            $new_rec->staleTime         = 0;
                        }
                        //$new_rec->staleTime         = strtotime(date('Y-m-d',$row[12]));
                        $new_rec->custom            = "1";
                        $new_rec->is_forsale        = $row[13];
                        $new_rec->sale_time         = time();
                        $new_rec->is_short          = $row[14];
                        $new_rec->short_time        = time();
                        $new_rec->check             = "0";
                        $new_rec->last_modified     = time();

                        $create_rest = $new_rec->save();
                        if($create_rest !== false)
                        {
                            $create_num +=1;
                        } else {
                            $error_num +=1;
                        }

                        
                    }
                }
            }
        }

        $reader->close();

        $total -=3;
        if(empty($message))
        {

            return $this->ajaxSuccess("success success 共处理 {$total} 条，新增 {$create_num} 条， 更新 {$update_num} 条, 出错 {$error_num} 条", $ret);

        } else {
            return $this->ajaxFail("fail  共处理 {$total} 条，新增 {$create_num} 条， 更新 {$update_num} 条, 出错 {$error_num} 条  ", $message, 1004);
        }
        // 返回结果
    }

    /**
     * 验证数据， 正确 true ,错误返回错误提示 row_index  col_index
     * @param  [type] $row [description]
     * @return [type]      [description]
     */
    private function validateRow($row, $row_num)
    {
        $col = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q'];
        $row_num +=1;

        $message = "";
        $catlist = Category::where(1)->field('name')->select();
        $tmplist = [];
        foreach ($catlist as $key => $value) {
            $tmplist[] = $value->name;
        }

        $abc = array_flip($tmplist);


        // 商品名称，飞控
        if(empty($row[1])|| strlen($row[1])>100)
        {
            $message.="第{$row_num} 行, 第 {$col[1]} 列;";
        
        }

        // 商品条码 非空
        // 全数字  ？？？
        if(empty($row[2])|| strlen($row[2])>40)
        {
            $message.="第{$row_num} 行, 第 {$col[2]} 列;";
        }

        // 商品分类 非空
        // 且要在分类栏目范围之内
        if(empty($row[3]) || !in_array($row[3], $tmplist))
        {
            $message.="第{$row_num} 行, 第 {$col[3]} 列;";
        } 

        // 计价方式 非空 数字 0 或者 1
        if(!is_integer($row[4]) || ($row[4] != 0 && $row[4] != 1))
        {
            $message.="第{$row_num} 行, 第 {$col[4]} 列;";  
        }

        // 5 规格，无要求
        
        // 6 商品单位 非空
        if(empty($row[6])|| strlen($row[6])>20)
        {
            $message.="第{$row_num} 行, 第 {$col[6]} 列;";
        } 


        // 7 进货价 非空 浮点
        if(!is_float($row[7]) && !is_integer($row[7]))
        {
            $message.="第{$row_num} 行, 第 {$col[7]} 列;";
        } 

        // 8 零售价 浮点 非空
        if(!is_float($row[8]) && !is_integer($row[8]))
        {
            $message.="第{$row_num} 行, 第 {$col[8]} 列;";
        } 

        // 9 初始库存 非空 整数
        if(!is_integer($row[9]))
        {
            $message.="第{$row_num} 行, 第 {$col[9]} 列;";
        } 

        // 10 库存预警 非空 整数
        if(!is_integer($row[10]))
        {
            $message.="第{$row_num} 行, 第 {$col[10]} 列;";
        } 


        // 11 货架号 无要求
        
        // 12 过期日期  不能查过当前日期
        if(!empty($row[12]))
        {
            $obj = json_decode(json_encode($row[12], true));
            if(!empty($obj)&&!is_null($obj)&&is_object($obj))
            {
                $date = strval($obj->date);
                // 条码不能为中文
                if(strtotime($date)< time())
                {
                    $message.="第{$row_num} 行, 第 {$col[12]} 列 过期;";

                }   
            }   
        } else {
            // $message.="第{$row_num} 行, 第 {$col[12]} 列 ;";         
        }
        

        // 13 是否上架 非空 整数  0 或者 1
        if(!is_integer($row[13])||
          (intval($row[13]) != 0 && intval($row[13]) != 1))
        {
            $message.="第{$row_num} 行, 第 {$col[13]} 列;"; 
        }

        // 14 是否快捷 非空 整数 0 或者 1
        if(!is_integer($row[14])||
          (intval($row[14])!=0 && intval($row[14])!=1))
        {
            $message.="第{$row_num} 行, 第 {$col[14]} 列;";
        }

        
        // 过期日期不能超过当前时间
        // if(preg_match("/^[0-9]+$/", $row[2]))
        // {
        //  $message.="第{$row_num} 行, 第 {$col[2]} 列;";
        // }
    

        

        // if($row_num ==5)
        // {
  //           exit(gettype($row[12]));
        //  exit(json_encode(is_integer($row[4])).'###'. json_encode(intval($row[4])).'###'.json_encode($row[4]==0).'###'.json_encode($row[4]==0));
        //  exit(json_encode($row));
        // }  


        if($message != "")
        {
            return $message;
        } else {
            return true;

        }

        return true;


        
    }

    public function downloadExcel(Request $req)
    {
        // $file = File::get("../resources/logs/$id");
        $headers = array(
           'Content-Type: application/octet-stream',
        );
        //exit(json_encode(file_exists(dirname(dirname(dirname(dirname(dirname(__FILE__)))))."/public/exceltemplate/import.xlsx")));;
        #return Response::download($file, $id. '.' .$type, $headers); 
        return response()->download(dirname(dirname(dirname(dirname(dirname(__FILE__)))))."/public/exceltemplate/import.xlsx", 'import.xlsx', $headers);
    }

    /**
     * 当商品店铺是新建的， device_no 为空  商品店铺
     * @return [type] [description]
     */
    private function insertGoodsIfStoreGoodsEmpty($store_code, $row, $store_id, $store_info, $abc)
    {
        // 
        $is_exist = User::where(['store_code' => $store_code])->find();

        if(is_null($is_exist))
            return;


        if(!empty($is_exist->device_no))
            return;


        // 检查商品库
        // $goods_exist = Goods::where(['store_code'=>$store_code])->first();
        // if(!is_null($goods_exist))
        //  return;
        $goods_info = Goods::where(['store_code'=>$store_code, 'goods_sn' => $row[2]])->find();
        if(!is_null($goods_info))
        {
            // 存在 更新
            $goods_info->user_id           = $store_id; // usr_id
            $goods_info->store_code        = $store_info->store_code;   // store_code
            $goods_info->goods_name        = $row[1];

            $goods_info->goods_sn          = $row[2];
            $goods_info->cat_id            = $abc[$row[3]] + 1;   // cat_id
            $goods_info->type              = $row[4];
            $goods_info->goods_picture     = ""; // 商品图片
            $goods_info->spec              = $row[5];
            $goods_info->create_time       = time();
            $goods_info->unit              = $row[6];
            $goods_info->cost_price        = $row[7];
            $goods_info->shop_price        = $row[8];
            $goods_info->repertory         = $row[9];
            $goods_info->repertory_caution = $row[10];
            $goods_info->place_code        = $row[11];

            if(strtolower( gettype($row[12])) == "object")
            {
                $obj = json_decode(json_encode($row[12], true));
                $date = strval($obj->date);
                $goods_info->staleTime = strtotime($date)*1000;
            } else {
                if(strtolower( gettype($row[12])) == "string" && empty($row[12]))
                {
                    $row['12'] = 0;
                }
                $goods_info->staleTime         = 0;
            }
            $goods_info->custom            = "1";
            $goods_info->is_forsale        = $row[13];
            $goods_info->sale_time         = time();
            $goods_info->is_short          = $row[14];
            $goods_info->short_time        = time();
            $goods_info->check             = "0";
            $goods_info->last_modified     = time();


            // 保存，保存成功后，更新记
            $save_rest = $goods_info->save();
            
        } else {
            // 不存在，插入
        
            $new_rec = new Goods();
            $new_rec->user_id           = $store_id; // usr_id
            $new_rec->id                = 1000;
            $new_rec->store_code        = $store_info->store_code;  // store_code
            $new_rec->goods_name        = $row[1];
            
            $new_rec->goods_sn          = $row[2];
            $new_rec->cat_id            = $abc[$row[3]] + 1;   // cat_id
            $new_rec->type              = $row[4];
            $new_rec->goods_picture     = ""; // 商品图片
            $new_rec->spec              = $row[5];
            $new_rec->create_time       = time();
            $new_rec->unit              = $row[6];
            $new_rec->cost_price        = $row[7];
            $new_rec->shop_price        = $row[8];
            $new_rec->repertory         = $row[9];
            $new_rec->repertory_caution = $row[10];
            $new_rec->place_code        = $row[11];
            if(strtolower( gettype($row[12])) == "object")
            {
                $obj = json_decode(json_encode($row[12], true));
                $date = strval($obj->date);
                $new_rec->staleTime = strtotime($date)*1000;
            } else {
                if(strtolower( gettype($row[12])) == "string" && empty($row[12]))
                {
                    $row['12'] = 0;
                }

                $new_rec->staleTime         = 0;
            }
            //$new_rec->staleTime         = strtotime(date('Y-m-d',$row[12]));
            $new_rec->custom            = "1";
            $new_rec->is_forsale        = $row[13];
            $new_rec->sale_time         = time();
            $new_rec->is_short          = $row[14];
            $new_rec->short_time        = time();
            $new_rec->check             = "0";
            $new_rec->last_modified     = time();

            $create_rest = $new_rec->save();

            $up = Goods::where(['store_code' => $store_info->store_code, 'id'=>1000])->find();
            if(!is_null($up))
            {
                $up->id = $up->local_id;
                $up->save();
            }   

        }



    }



    /**
     * 开户行里诶包
     * @return [type] [description]
     */
    public function bank()
    {
        $list = Bank::where(1)->select();
        $tmp = ['list'=>$list];
        $ret = [];
        $ret[] = $tmp;
        return $this->ajaxSuccess("bank list success", $ret);
    }

    public function province()
    {
        $list = Region::where(['parent_id'=>1])->select();
        $result['list'] = $list;
        $ret = $result;

        return $this->ajaxSuccess(" success", $ret);
    }

    public function city()
    {

        $pro_id = Request::instance()->param("pro_id");

        if(is_null($pro_id))
        {
            return $this->ajaxFail("pro_id field can not be empty", [], 1000);
        }

        if(intval($pro_id)===false||intval($pro_id)<1)
        {
            return $this->ajaxFail("pro_id ilegal", [], 1000);
        }

        $list = Region::where(['parent_id'=>$pro_id])->select();
        $result['list'] = $list;
        $ret = $result;

        return $this->ajaxSuccess(" success", $ret);


    }

    public function area()
    {
        $city_id = Request::instance()->param("city_id");

        if(is_null($city_id))
        {
            return $this->ajaxFail("city_id field can not be empty", [], 1000);
        }

        if(intval($city_id)===false||intval($city_id)<1)
        {
            return $this->ajaxFail("city_id ilegal", [], 1000);
        }

        $list = Region::where(['parent_id'=>$city_id])->select();
        $result['list'] = $list;
        $ret = $result;

        return $this->ajaxSuccess(" success", $ret);
        
    }

    /**
     * 销量下跌商品
     */
    public function DownGoods()
    {
        $this->checkPageNoRecordNo();
        $is_order_by_money = Request::instance()->param("is_order_by_money");

        if(is_null($is_order_by_money))
        {
            return $this->ajaxFail("is_order_by_money field can not be empty", [], 1002);
        }

        list($list, $total_number) = OrderGoods::get7CycleByNum($this->store_code, $this->record_no, $this->page_no, $is_order_by_money);
        return $this->ajaxSuccess("get shift record success", $this->getReturn($list, $total_number));

    }

    /**
     * 新增商品，编辑商品
     *
     * goods_import_id_list  数组，可以编辑单个商品，也可以编辑多个商品
     */
    public function updateGoods()
    {
        $this->checkGoods();

        $goods_import_id_list   = Request::instance()->param("goods_import_id_list");
        $goods_name        = Request::instance()->param("goods_name");
        $cat_id            = Request::instance()->param("cat_id");
        $goods_sn          = Request::instance()->param("goods_sn");
        $type              = Request::instance()->param("type");
        $goods_picture     = Request::instance()->param("goods_picture");
        $spec              = Request::instance()->param("spec");
        $unit              = Request::instance()->param("unit");
        $cost_price        = Request::instance()->param("cost_price");
        $shop_price        = Request::instance()->param("shop_price");
        $repertory         = Request::instance()->param("repertory");
        $repertory_caution = Request::instance()->param("repertory_caution");
        $place_code        = Request::instance()->param("place_code");
        $is_forsale        = Request::instance()->param("is_forsale");
        $is_short          = Request::instance()->param("is_short");
        $staleTime         = Request::instance()->param("staleTime");

        $arr = [];

        if(!empty($goods_name))
        {
            $arr['goods_name'] = $goods_name;
        }
        if(!empty($goods_sn))
        {
            $arr['goods_sn'] = $goods_sn;
        }
        if(intval($cat_id)>=1)
        {
            $arr['cat_id'] = $cat_id;
        }
        if(!empty($goods_picture))
        {
            $arr['goods_picture'] = $goods_picture;
        }

        if(!empty($spec))
        {
            $arr['spec'] = $spec;
        }

        if(!empty($place_code))
        {
            $arr['place_code'] = $place_code;
        }

        if(!empty($unit))
        {
            $arr['unit'] = $unit;
        }

        // 过滤非空
        if(floatval($cost_price)!==false)
        {
            if($cost_price!="")
            {
                $arr['cost_price'] = floatval($cost_price);
            }
        }

        if(!empty($staleTime))
        {
            $arr['staleTime'] = $staleTime;
        }


        // 过滤非空
        if(floatval($shop_price)!==false)
        {
            if($shop_price!="")
            {
                $arr['shop_price'] = floatval($shop_price);
            }
        }

        // 过滤非空
        if(floatval($repertory)!==false)
        {
            if($repertory!="")
            {
                $arr['repertory'] = floatval($repertory);
            }
        }
        // 过滤非空
        if(floatval($repertory_caution)!==false)
        {
            if($repertory_caution!="")
            {
                $arr['repertory_caution'] = floatval($repertory_caution);
            }
        }
        // 过滤非空
        if($this->isZeroOrOne($type))
        {
                $arr['type'] = intval($type);
        }

        // 过滤非空
        if(intval($is_forsale)!==false)
        {
            if($is_forsale!="")
            {
                $arr['is_forsale'] = intval($is_forsale);
            }
        }

        // 过滤非空
        if(intval($is_short)!==false)
        {
            if($is_short!="")
            {
                $arr['is_short'] = intval($is_short);
            }
        }


        $id_arr = explode(",", $goods_import_id_list);
        // 开启事务
        $ret = true;
        Db::startTrans();

        $store_info = User::getStoreByToken($this->token);

        try{
            foreach ($id_arr as $key => $value) {
                if(!empty($value)&&intval($value)!==false)
                {
                    // 开始执行更新操作
                    $tmp_ret = Goods::where(['store_code'=>$store_info->store_code, 'id'=>$value])->update($arr);
                    if($tmp_ret === false)
                    {
                        throw new \Exception("Error Processing Request", 1);
                    }
                }
            }

            Db::commit();  
        } catch (\Exception $e) {
            // 回滚事务
            $ret = false;
            return $this->ajaxFail($e->getMessage(), [], 1002);
            Db::rollback();
        }

        if(!$ret)
        {
            return $this->ajaxFail("update goods failed", [], 1002);
        } 



        // 检查是否存在 id
        return $this->ajaxSuccess("goods update success", []);
        
    }

    public function createGoods()
    {
        $this->checkCreateGoods();

        $storeinfo = User::getStoreByToken($this->token);

        $goods_name        = Request::instance()->param("goods_name");
        $cat_id            = Request::instance()->param("cat_id");
        $goods_sn          = Request::instance()->param("goods_sn");
        $type              = Request::instance()->param("type");
        $goods_picture     = Request::instance()->param("goods_picture");
        $spec              = Request::instance()->param("spec");
        $unit              = Request::instance()->param("unit");
        $cost_price        = Request::instance()->param("cost_price");
        $shop_price        = Request::instance()->param("shop_price");
        $repertory         = Request::instance()->param("repertory");
        $repertory_caution = Request::instance()->param("repertory_caution");
        $place_code        = Request::instance()->param("place_code");
        $is_forsale        = Request::instance()->param("is_forsale");
        $is_short          = Request::instance()->param("is_short");
        $staleTime         = Request::instance()->param("staleTime");

        $user           = new GoodsImport();
        $user->user_id    = $storeinfo->id;
        $user->store_code = $storeinfo->store_code;
        
        $user->goods_name        = $goods_name;
        $user->cat_id            = $cat_id;
        $user->goods_sn          = $goods_sn;
        $user->type              = $type;
        $user->goods_picture     = $goods_picture;
        $user->spec              = $spec;
        $user->unit              = $unit;
        $user->cost_price        = $cost_price;
        $user->shop_price        = $shop_price;
        $user->repertory         = $repertory;
        $user->repertory_caution = $repertory_caution;
        $user->place_code        = $place_code;
        $user->is_forsale        = $is_forsale;
        $user->is_short          = $is_short;
        $user->staleTime         = $staleTime;
        
        $user->create_time       = time()*1000;
        if(intval($is_forsale) == 1)
        {
            $user->sale_time = time() * 1000;
        }

        if(intval($is_short))
        {
            $user->short_time = time() *1000;
        }

        $result = $user->save();
        if($result === false)
        {
            return $this->ajaxFail("create failed", [], 1002);   
        }

        return $this->ajaxSuccess("goods create success", []);

    }

    public function createStaff()
    {
        $this->checkCreateStaff();
        $uname = Request::instance()->param("uname");
        $password = Request::instance()->param("password");
        $realname = Request::instance()->param("realname");
        $rank = Request::instance()->param("rank");

        $store_info = User::getStoreByToken($this->token);

        $last = User::where(['store_code'=>$this->store_code])->order("id desc")->limit(1)->select();
        $max_id = $last[0]->id;

        $new_staff = new User();
        $new_staff->store_name = $store_info->store_name;
        $new_staff->id = $max_id+20;
        $new_staff->store_code = $store_info->store_code;
        $new_staff->uname = $uname;
        $new_staff->password = $password;
        $new_staff->rank= $rank;
        $new_staff->realname = $realname;
        $new_staff->create_time = time();
        $ret = $new_staff->save();
        if($ret !== false)
        {
            return $this->ajaxSuccess("goods create success", []);

        }else {
            return $this->ajaxFail("create staff failed", [], 1002);   

        }


    }

    public function resetStorePassword()
    {
        $password = Request::instance()->param("password");
        $password_confirm = Request::instance()->param("password_confirm");
        $staff_id = Request::instance()->param("staff_id");

        if(is_null($staff_id))
        {
            return $this->ajaxFail("staff_id field can not be empty", [], 1003);   
        }

        if(is_null($password))
        {
            return $this->ajaxFail("password field can not be empty", [], 1003);   
        }

        if(is_null($password_confirm))
        {
            return $this->ajaxFail("password_confirm field can not be empty", [], 1003);   
        }

        if($password_confirm !== $password)
        {
            return $this->ajaxFail("password_confirm  password has to be the same", [], 1003);   
        }

        // 判断 staff_id 
        $inf = User::getStoreByToken($this->token);
        $is_exist = $inf;


        if($is_exist == null || $is_exist==false)
        {
            return $this->ajaxFail("staff not exist", [], 1003);   
        }

        if($is_exist->is_active==0)
        {
            return $this->ajaxFail("staff frozen", [], 1004);   
        }

        $where_update = [];
        $where_update['store_code'] = $inf->store_code;
        if(!empty($staff_id))
        {
            $where_update['id'] = $staff_id;
        } 

        $result = User::where($where_update)->update(['password'=>$password,'token'=>""]);

        if($result === false)
        {
            return $this->ajaxFail("failed", [], 1005);   
        }

        return $this->ajaxSuccess("reset password success", []);
    }

    /**
     * 获取商品条形码
     * @return [type] [description]
     */
    public function getBarcode()
    {
        $ret = [];
        $goods_ran = new GoodsRandom();
        $goods_ran->name = time();
        $id = $goods_ran->save();
        if($id==false)
        {   
            return $this->ajaxFail("faild", [], 1000);
        }

        $json['barcode'] = "3".$this->store_code.sprintf("%05d", intval($goods_ran->id));
        $ret[] = $json;
        return $this->ajaxSuccess("barcode retrive success",$ret);
    }

    public function uploadImage()
    {
        if(request()->isPost()){
            // 获取表单上传的文件，例如上传了一张图片
            //exit("_FILES = ".json_encode($_FILES).'   request = '.request()->file('file').'  get '.json_encode($_GET).'  post ='.json_encode($_POST));
            $file = request()->file('file');
            if($file){
                //将传入的图片移动到框架应用根目录/public/uploads/ 目录下，ROOT_PATH是根目录下，DS是代表斜杠 / 
                $info = $file->move(ROOT_PATH . 'public' . DS . 'static'. DS .'uploads');
                if($info){
                    $path = $info->getSaveName();
                    return $this->ajaxSuccess("uplaod success", ['http://'.$_SERVER['SERVER_NAME'].'/static/uploads/'.$path]);
                }else{
                    // 上传失败获取错误信息
                    return $this->ajaxFail("upload failed", [], 1002);
                }
            }

            return $this->ajaxFail("no file data found", [], 1003);
        } else {
            return $this->ajaxFail("only post request is supported", [], 1004);

        }

    }

    public function getHost()
    {
        echo  'http://'.$_SERVER['SERVER_NAME'].':'.$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
    }



    /**
     * 删除店员
     * @return [type] [description]
     */
    public function delStaff()
    {
        $staff_id          = Request::instance()->param("staff_id");
        if(is_null($staff_id))
        {
            return $this->ajaxFail("staff_id field can not be empty", [], 1002);   
        }

        $list = explode(",", trim($staff_id));


        $inf = User::getStoreByToken($this->token);


        $ret = true;
        Db::startTrans();


        try{
            foreach ($list as $key => $value) {
                if(!empty($value)&&intval($value)!==false)
                {
                    // 开始执行更新操作
                    // 开启事务
                    // 
                    $is_exist = User::where(['store_code'=>$inf->store_code, 'id'=>$value])->find();

                    if($is_exist == null || $is_exist==false)
                    {
                        throw new \Exception("staff of {$value} not exist", 1);
                    }

                    if($is_exist->rank==0)
                    {
                        throw new \Exception("staff of {$value} is store_owner!!", 1);
                        
                    }

                    if($is_exist->is_active==0)
                    {

                    }

                    
                    $result = $is_exist->where(['store_code'=>$inf->store_code, 'id'=>$value])->update(['deleted'=>1]);

                    if($result === false)
                    {
                        throw new \Exception("staff of {$value} update failure", 1);
                        
                    }
                }
            }

            Db::commit();  
        } catch (\Exception $e) {
            // 回滚事务
            $ret = false;
            return $this->ajaxFail($e->getMessage(), [], 1005);   

            Db::rollback();
        }


        if($ret)
        {
            return $this->ajaxSuccess("delete success", []);
        } else {
            return $this->ajaxFail("failed", [], 1005);   

        }

        return $this->ajaxSuccess("delete success", []);



    }



    /**
     * 删除商品
     * @return [type] [description]
     */
    public function delGoods()
    {
        $goods_local_id          = Request::instance()->param("goods_local_id");
        if(is_null($goods_local_id))
        {
            return $this->ajaxFail("goods_local_id field can not be empty", [], 1002);   
        }

        $list = explode(",", trim($goods_local_id));


        $inf = User::getStoreByToken($this->token);


        $ret = true;
        Db::startTrans();


        try{
            foreach ($list as $key => $value) {
                if(!empty($value)&&intval($value)!==false)
                {
                    // 开始执行更新操作
                    // 开启事务
                    // 
                    $is_exist = Goods::where(['store_code'=>$inf->store_code, 'local_id'=>$value])->find();

                    if($is_exist == null || $is_exist==false)
                    {
                        throw new \Exception("Goods of local_id {$value} not exist", 1);
                    }

                    // if($is_exist->rank==0)
                    // {
                    //     throw new \Exception("staff of {$value} is store_owner!!", 1);
                        
                    // }

                    // if($is_exist->is_active==0)
                    // {

                    // }

                    
                    $result = $is_exist->where(['store_code'=>$inf->store_code, 'local_id'=>$value])->update(['deleted'=>1]);

                    if($result === false)
                    {
                        throw new \Exception("Goods of local_id {$value} update failure", 1);
                        
                    }
                }
            }

            Db::commit();  
        } catch (\Exception $e) {
            // 回滚事务
            $ret = false;
            return $this->ajaxFail($e->getMessage(), [], 1005);   

            Db::rollback();
        }


        if($ret)
        {
            return $this->ajaxSuccess("delete success", []);
        } else {
            return $this->ajaxFail("failed", [], 1005);   

        }

        return $this->ajaxSuccess("delete success", []);



    }



    /**
     * 删除店员
     * @return [type] [description]
     */
    public function delMem()
    {
        $staff_id          = Request::instance()->param("mem_id");
        if(is_null($staff_id))
        {
            return $this->ajaxFail("mem_id field can not be empty", [], 1002);   
        }

        $list = explode(",", trim($staff_id));


        $inf = User::getStoreByToken($this->token);


        $ret = true;
        Db::startTrans();


        try{
            foreach ($list as $key => $value) {
                if(!empty($value)&&intval($value)!==false)
                {
                    // 开始执行更新操作
                    // 开启事务
                    // 
                    $is_exist = Member::where(['store_code'=>$inf->store_code, 'id'=>$value])->find();

                    if($is_exist == null || $is_exist==false)
                    {
                        throw new \Exception("member of {$value} not exist", 1);
                    }

                    
                    $result = $is_exist->where(['store_code'=>$inf->store_code, 'id'=>$value])->update(['deleted'=>1]);

                    if($result === false)
                    {
                        throw new \Exception("member of {$value} update failure", 1);
                        
                    }
                }
            }

            Db::commit();  
        } catch (\Exception $e) {
            // 回滚事务
            $ret = false;
            return $this->ajaxFail($e->getMessage(), [], 1005);   

            Db::rollback();
        }


        if($ret)
        {
            return $this->ajaxSuccess("delete success", []);
        } else {
            return $this->ajaxFail("failed", [], 1005);   

        }

        return $this->ajaxSuccess("delete success", []);



    }

    /**
     * 编辑店员
     * @return [type] [description]
     */
    public function updateStaff()
    {
        $this->checkField(['rank','staff_id', 'realname']);

        $staff_id = Request::instance()->param("staff_id");

        if(intval($staff_id) === false || intval($staff_id)<1)
        {
            return $this->ajaxFail("staff id ilegal", [], 1000);
        }
        $update_arr = $this->storeUpdateData(['rank', 'realname']);
        
        $store_info = User::getStoreByToken($this->token);

        $where['store_code'] = $store_info->store_code;
        $where['id'] = $staff_id;

        $ret = User::where($where)->update($update_arr);

        if($ret === false)
        {
            return $this->ajaxFail("update failed", [], 1002);
        }

        return $this->ajaxSuccess("update success", []);
        
    }

    /**
     * 滞销商品
     */
    public function StagnateGoods()
    {
        $this->checkPageNoRecordNo();

        $is_order_by_money = Request::instance()->param("is_order_by_money");

        if(is_null($is_order_by_money))
        {
            return $this->ajaxFail("is_order_by_money field can not be empty", [], 1002);
        }

        list($list, $total_number) = OrderGoods::getStagGoods($this->store_code, $this->record_no, $this->page_no, $is_order_by_money);

        return $this->ajaxSuccess("get shift record success", $this->getReturn($list, $total_number));
    }

    public function pointDis()
    {
        $this->checkField(['min','max','num']);

        $min =  Request::instance()->param("min");
        $max =  Request::instance()->param("max");
        $num =  Request::instance()->param("num");

        if(!is_numeric($min))
        {
            return $this->ajaxFail("min field illegal", [], 1002);
        }

        if(!is_numeric($max))
        {
            return $this->ajaxFail("max field illegal", [], 1002);
        }


        if(!is_numeric($num))
        {
            return $this->ajaxFail("num field illegal", [], 1002);
        }


        if($max <= $min)
        {
            return $this->ajaxFail("max has to be greater than min", [], 1002);
        }


        $this->getCustomFilter(['min','max','num']);

        // 获取
        $obj = Member::pointSplit($this->token, $min, $max, $num);

        return $this->ajaxSuccess(" success", $this->getReturn($obj, 1));
    
    }


    /**
     * 会员消费分析
     * @return [type] [description]
     */
    public function memCompAnaly()
    {
        $this->checkField(['name', 'phone']);
        $name =  Request::instance()->param("name");
        $phone =  Request::instance()->param("phone");

        list($list, $total_number) = OrderGoods::memberComAna($this->store_code, $name, $phone);

        return $this->ajaxSuccess(" success", $this->getReturn($list, $total_number));

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
        if(is_null($this->record_no))
        {
            return $this->ajaxFail("record_no field can not be empty", [], 3000);
        }

        if(is_null($this->page_no))
        {
            return $this->ajaxFail("page_no field can not be empty", [], 3001);
        }
    }

    private function checkCreateGoods()
    {
        if(is_null(Request::instance()->param("goods_name")))
        {
            return $this->ajaxFail("goods_name field can not be empty", [], 8001);
        }

        if(is_null(Request::instance()->param("cat_id")))
        {
            return $this->ajaxFail("cat_id field can not be empty", [], 8002);
        }

        if(is_null(Request::instance()->param("goods_sn")))
        {
            return $this->ajaxFail("goods_sn field can not be empty", [], 8003);
        }

        if(is_null(Request::instance()->param("type")))
        {
            return $this->ajaxFail("type field can not be empty", [], 8004);
        }

        if(is_null(Request::instance()->param("goods_picture")))
        {
            return $this->ajaxFail("goods_picture field can not be empty", [], 8005);
        }
        
        if(is_null(Request::instance()->param("spec")))
        {
            return $this->ajaxFail("spec field can not be empty", [], 8006);
        }

        if(is_null(Request::instance()->param("unit")))
        {
            return $this->ajaxFail("unit field can not be empty", [], 8007);
        }

        if(is_null(Request::instance()->param("cost_price")))
        {
            return $this->ajaxFail("cost_price field can not be empty", [], 8008);
        }

        if(is_null(Request::instance()->param("shop_price")))
        {
            return $this->ajaxFail("shop_price field can not be empty", [], 8009);
        }

        if(is_null(Request::instance()->param("repertory")))
        {
            return $this->ajaxFail("repertory field can not be empty", [], 8010);
        }
        
        if(is_null(Request::instance()->param("repertory_caution")))
        {
            return $this->ajaxFail("repertory_caution field can not be empty", [], 8011);
        }

        if(is_null(Request::instance()->param("place_code")))
        {
            return $this->ajaxFail("place_code field can not be empty", [], 8012);
        }

        if(is_null(Request::instance()->param("is_forsale")))
        {
            return $this->ajaxFail("is_forsale field can not be empty", [], 8012);
        }

        if(is_null(Request::instance()->param("is_short")))
        {
            return $this->ajaxFail("is_short field can not be empty", [], 8013);
        }

        if(is_null(Request::instance()->param("staleTime")))
        {
            return $this->ajaxFail("staleTime field can not be empty", [], 8013);
        }
        
    }

    private function checkUpdateMember($field_list)
    {
        $this->checkField($field_list);
    }

    private function checkField($arr_field)
    {
        foreach ($arr_field as $key => $value) {
            if(is_null(Request::instance()->param($value)))
            {
                return $this->ajaxFail("{$value} field can not be empty", [], 8013);
            }    
        }


    }

    public function varTest()
    {
        $test='  ';
        $test = trim($test);

        echo "<br />0 在php中，int_max ==  ".PHP_INT_MAX; //被输出

        if(is_numeric(PHP_INT_MAX))
        {
          echo "<br />0 在php中，int_max  is numberic  ";

        } else {
          echo "<br />0 在php中，int_max  is not numberic  ";

        }
        

        if($test==""){
            echo "<br />1 在php中，{$test} == '' "; //被输出
        }else{
            echo "<br />1 在php中，{$test} != '' "; //被输出
        }

        if($test===""){
            echo "<br />2 在php中，{$test} === '' "; //不被输出
        }else {
            echo "<br />2 在php中，{$test} !== ''  "; //不被输出
        }

        if($test==NULL){
            echo "<br />3 在php中，{$test} == null  "; //被输出
        } else {
            echo "<br />3 在php中，{$test} != null  "; //被输出
        }

        if($test===NULL){
            echo "<br />4 在php中，{$test} === null "; //不被输出
        } else {
             echo "<br />4 在php中，{$test} !== null"; //不被输出
        }

        if($test==false){
            echo "<br />5 在php中，{$test} == false"; //被输出
        } else {
            echo " <br />5 在php中，{$test} == false"; //被输出
        }

        if($test===false){
            echo "<br />6 在php中，{$test} === false"; //不被输出
        } else {
            echo "<br />6 在php中，{$test} !== false"; //不被输出
        }

        if(empty($test))
        {
            echo "<br />7 在php中，{$test} == empty(var)"; //不被输出
        } else {
            echo "<br />7 在php中，{$test} != empty(var)"; //不被输出
        }

        if(is_numeric($test))
        {
            echo "<br />8 在php中，{$test} is numeric"; //不被输出
        } else {
            echo "<br />8 在php中，{$test} is not numeric"; //不被输出
        }

        if(is_bool($test))
        {
            echo "<br />10  在php中，{$test} is boolean"; //不被输出
        } else {
            echo "<br />10 在php中，{$test} is boolean"; //不被输出
        }


        // is_null 
        // is_set
        // is_empty
        // is_object
        // is_array
        // is_array
        // is_string
        // is_int
        // is_float
        

        echo "<br />9 在php中，intval {$test} === ".json_encode(intval($test)); 
    }

    private function checkCreateStaff()
    {
        if(is_null(Request::instance()->param("uname")))
        {
            return $this->ajaxFail("uname field can not be empty", [], 8001);
        }

        if(is_null(Request::instance()->param("password")))
        {
            return $this->ajaxFail("password field can not be empty", [], 8002);
        }

        if(is_null(Request::instance()->param("realname")))
        {
            return $this->ajaxFail("realname field can not be empty", [], 8003);
        }

        if(is_null(Request::instance()->param("rank")))
        {
            return $this->ajaxFail("rank field can not be empty", [], 8004);
        }

    }

    private function checkGoods()
    {

        
        if(is_null(Request::instance()->post("goods_import_id_list")))
        {
            return $this->ajaxFail("goods_import_id_list field can not be empty", [], 8088);
        }
   

        if(is_null(Request::instance()->param("goods_name")))
        {
            return $this->ajaxFail("goods_name field can not be empty", [], 8001);
        }

        if(is_null(Request::instance()->param("cat_id")))
        {
            return $this->ajaxFail("cat_id field can not be empty", [], 8002);
        }

        if(is_null(Request::instance()->param("goods_sn")))
        {
            return $this->ajaxFail("goods_sn field can not be empty", [], 8003);
        }

        if(is_null(Request::instance()->param("type")))
        {
            return $this->ajaxFail("type field can not be empty", [], 8004);
        }

        if(is_null(Request::instance()->param("goods_picture")))
        {
            return $this->ajaxFail("goods_picture field can not be empty", [], 8005);
        }
        
        if(is_null(Request::instance()->param("spec")))
        {
            return $this->ajaxFail("spec field can not be empty", [], 8006);
        }

        if(is_null(Request::instance()->param("unit")))
        {
            return $this->ajaxFail("unit field can not be empty", [], 8007);
        }

        if(is_null(Request::instance()->param("cost_price")))
        {
            return $this->ajaxFail("cost_price field can not be empty", [], 8008);
        }

        if(is_null(Request::instance()->param("shop_price")))
        {
            return $this->ajaxFail("shop_price field can not be empty", [], 8009);
        }

        // if(is_null(Request::instance()->param("repertory")))
        // {
        //     return $this->ajaxFail("repertory field can not be empty", [], 8010);
        // }
        
        if(is_null(Request::instance()->param("repertory_caution")))
        {
            return $this->ajaxFail("repertory_caution field can not be empty", [], 8011);
        }

        if(is_null(Request::instance()->param("place_code")))
        {
            return $this->ajaxFail("place_code field can not be empty", [], 8012);
        }

        if(is_null(Request::instance()->param("is_forsale")))
        {
            return $this->ajaxFail("is_forsale field can not be empty", [], 8012);
        }

        if(is_null(Request::instance()->param("is_short")))
        {
            return $this->ajaxFail("is_short field can not be empty", [], 8013);
        }

        if(is_null(Request::instance()->param("staleTime")))
        {
            return $this->ajaxFail("staleTime field can not be empty", [], 8013);
        }
            
    }

    private function checkStartEndDate()
    {
        if(is_null(Request::instance()->param("start_date")))
        {
            return $this->ajaxFail("start_date field can not be empty", [], 3000);
        }

        if(is_null(Request::instance()->param("end_date")))
        {
            return $this->ajaxFail("end_date field can not be empty", [], 3001);
        }   

        return [Request::instance()->param("start_date"), Request::instance()->param("end_date")];
    }

    private function getFilter($total_number)
    {
        $page = [];
        $page['page_no'] = $this->page_no;
        $page['record_no'] = $this->record_no;
        $page['total_number'] = $total_number;
        $ret = array_merge($this->filter, $page);

        return $ret;
    }

    private function getCustomFilter(array $querynamelist)
    {
        foreach ($querynamelist as $key => $value) {
            $this->filter[$value] = Request::instance()->param($value);
        }
    }

    /**
     * 获取返回
     * @param  [type] $list [description]
     * @return [type]       [description]
     */
    private function getReturn($list, $total_number = 0)
    {
        $tmp = [];

        $ret = [];
        $ret['list'] = $list;
        $ret['filter'] = $this->getFilter($total_number);
        $tmp[] = $ret;
        
        return $tmp;
    }

    public function getDate()
    {

        exit(json_encode(Member::rankByCompu($this->store_code,"2014/5/22 0:0:0","2019/6/22 23:36:59")));
        exit(json_encode( Member::rankByCompuNo($this->store_code,"2014/5/22 0:0:0","2019/6/22 23:36:59", $this->record_no, $this->page_no)));


        // exit(date("Y-m-d H:i:s", time()));
        // 
        //echo " today's revenue of code {$this->store_code} is ".json_encode(Order::revenue($this->store_code,"2019/5/22 0:0:0","2019/6/22 23:36:59"));
        echo "<br/>";
        //echo " goods rank info  of code {$this->store_code} is ".json_encode(OrderGoods::getRank($this->store_code,"2019/5/22 0:0:0","2019/6/22 23:36:59"));
        echo "<br/>";
        // echo "xxxxxxxxx".json_encode(OrderGoods::getStatic($this->store_code,"2019/5/22 0:0:0","2019/5/22 23:36:59"));

        exit(json_encode(OrderGoods::getStagGoods($this->store_code)));

        $now_date = date("Y-m-d 23:59:59", time());
        $now_date_7 = date("Y-m-d 23:59:59", strtotime("-7 days"));
        $now_date_14 = date("Y-m-d 23:59:59", strtotime("-14 days"));
        exit($now_date.'##'.$now_date_7);
    }


    public function getDeci()
    {
        exit(3/2);   
    }

}
