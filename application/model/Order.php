<?php
namespace app\model;

use think\Model;
use app\model\Member;
use think\Db;

class Order extends Model
{
	protected $pk = 'uid';
	protected $table="pos_order";
	protected $createTime = false;

	public static function getAllOrdersByStorecode($code, $record_no, $page_no, $start_date, $end_date, $order_sn, $cashier_id, $payment_way)
	{
		$where = [];
		$where['store_code'] = $code;
		$where['deleted'] = 0;

		if(!empty($start_date) && !empty($end_date))
		{
			$where['create_time'] = ["between",strval(strtotime($start_date)*1000).",".strval(strtotime($end_date))*1000];
		}

		if(!empty($order_sn))
		{
			$where['order_sn'] = ["like", "%{$order_sn}%"];	
		}

		if(!is_null($payment_way)&&intval($payment_way)!==false)
		{
			// 微信
			if(intval($payment_way) == 11)
			{
				$where['pay_type'] = 2;
			}

			// 支付宝
			if(intval($payment_way) == 21)
			{
				$where['pay_type'] = 1;
			}

			// 微信
			if(intval($payment_way) == 31)
			{
				$where['pay_type'] = 3;
			}

			if(intval($payment_way) == 41)
			{
				$where['pay_type'] = 0;
			}
		}

		if(!is_null($cashier_id)&&intval($cashier_id)!==false&&is_numeric($cashier_id))
		{
			$where['uid'] = $cashier_id;
		}


		$list = self::where($where)->limit(($page_no-1)*$record_no, $record_no)->select();
		$total = self::where($where)->count();
		return [$list, $total];
	}

	/**
	 * 退货单数和金额
	 * @return [type] [description]
	 */
	public static function refundOrder()
	{
			$money_list = db('order')->alias("o")
					   ->join("pos_user u", "o.uid=u.id and o.store_code=o.store_code")
					   ->field("o.*, u.realname")
					   ->where($where)
					   ->group('cat.id')
					   // 这里有问题，不能用金额排序，加上这段就报错
					   ->order('revenue','desc')
					   ->select();
	}

	/**
	 * 获取营业收入，如果没有 start_date end_date 取 空  total_revenue  
	 * 返回  
	 * @param  [type] $start_date [description]
	 * @param  [type] $end_date   [description]
	 * @return [type]             [description]
	 */
	public static function revenue($code, $start_date=null, $end_date=null)
	{

		//exit("start date = {$start_date}  end date = {$end_date}");
		if($start_date == null && $end_date == null)
		{
			$start_date = date("Y-m-d 0:0:0", time());
			$end_date = date("Y-m-d 23:59:59", time());
		}

		// 已完成
		$where = [];
		$where['store_code'] = $code;
		$where['status'] = 1; // 已完成
		$where['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];



		// 已退款
		$where2 = [];
		$where2['store_code'] = $code;
		$where2['status'] = 3; // 已完成
		$where2['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];

		// 散客
		$where_non_member = [];
		$where_non_member['store_code'] = $code;
		$where_non_member['status'] = 1; // 已完成
		$where_non_member['mid'] = 0; // 已完成
		$where_non_member['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];

		// 会员
		$where_member = [];
		$where_member['store_code'] = $code;
		$where_member['status'] = 1; // 已完成
		$where_member['mid'] = array('gt',0); // 已完成
		$where_member['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];

		// 获取指定日期的金额
		$finished = self::where($where)->sum('receivable_price');
		$refund = self::where($where2)->sum('receivable_price');
		$refund_no = self::where($where2)->count();

		// 计算优惠金额
		$where_discount = [];
		$where_discount['store_code'] = $code;
		$where_discount['status'] = 1; // 已完成
		$where_discount['discounts_price'] = array('neq', "0"); // 已完成

		if(!empty($start_date)&&!empty($end_date)&&strtotime($end_date)>strtotime($start_date))
		{
			$where_discount['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];
		}

		$non_member_order_no = self::where($where_non_member)->count();
		$member_order_no = self::where($where_member)->count();

		$discount_total = self::where($where_discount)->sum('discounts_price');
		$discount_order_no = self::where($where_discount)->count();
		

		return [$finished, $refund, $refund_no, $non_member_order_no, $member_order_no, $discount_total, $discount_order_no];
	}

	public static function getTodayRevenue($code)
	{

	}


	/**
	 * 获取营业概况数据
	 * @param  [type] $code       [description]
	 * @param  [type] $start_date [description]
	 * @param  [type] $end_date   [description]
	 * @return [type]             [description]
	 */
	public static function getSalesOutlook($code, $start_date, $end_date)
	{
		// 获取完成订单的现金数据，微信数据，支付宝数据  订单数量和销售金额
		// 服务退款订单的订单数量和退货金额
		// 
		
		$where['store_code'] = $code;
		$where['status'] = array('in',[1,3]);

		if(!empty($start_date)&&!empty($end_date)&&strtotime($end_date)>strtotime($start_date))
		{
			$where['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];
		}

		$order_total_number = self::where($where)->count();


		$where_comple['store_code'] = $code;
		$where_comple['status'] = 1;

		if(!empty($start_date)&&!empty($end_date)&&strtotime($end_date)>strtotime($start_date))
		{
			$where_comple['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];
		}

		$order_total_number_complete = self::where($where)->count();

		$where_refund['store_code'] = $code;
		$where_refund['status'] = 3;

		if(!empty($start_date)&&!empty($end_date)&&strtotime($end_date)>strtotime($start_date))
		{
			$where_refund['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];
		}

		$order_total_number_refund = self::where($where)->count();


		$where_refund['store_code'] = $code;
		$where_refund['status'] = 3;
		if(!empty($start_date)&&!empty($end_date)&&strtotime($end_date)>strtotime($start_date))
		{
			$where_refund['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];
		}

		$order_refund_number = self::where($where_refund)->count();
		$order_refund_money = self::where($where_refund)->sum("receivable_price");


		// 微信，支付宝，现金
		$where['pay_type'] = 0; // 现金
		$order_cash_money = self::where($where)->sum("receivable_price");


		unset($where['pay_type']);
		$where['pay_type'] = 1; // 支付宝

		$order_alibaba_money = self::where($where)->sum("receivable_price");


		unset($where['pay_type']);
		$where['pay_type'] = 2; // 支付宝
		$order_wechat_money = self::where($where)->sum("receivable_price");

		//exit(" cash = {$order_cash_money}  ali = {$order_alibaba_money}  wechat = {$order_wechat_money}");
		unset($where['pay_type']);
		unset($where['status']);
		$where['status'] = 3;
		$where['pay_type'] = 0;
		$refund_cash = self::where($where)->sum("receivable_price");

		unset($where['pay_type']);
		$where['pay_type'] = 1;
		$refund_ali = self::where($where)->sum("receivable_price");


		unset($where['pay_type']);
		$where['pay_type'] = 2;
		$refund_wechat = self::where($where)->sum("receivable_price");


		$profit_where['o.store_code'] = $code;
		$profit_where['o.status'] = array('in',[3,1]);
		//$profit_where['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];
		if(!empty($start_date)&&!empty($end_date)&&strtotime($end_date)>strtotime($start_date))
		{
			$profit_where['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];
		}


		$to = Order::where(['store_code'=>$code, 'status'=>array('in',[1,3]), 'create_time'=>['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)]])->sum('receivable_price');


		// 计算利润
		$money_list = db('order')->alias("o")
					->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
					->field("sum(o.receivable_price) as revenue, sum(g.cost_price*g.goods_num) as cost_basic")
					->where($profit_where)
					->select();

		$complete_where_1['o.store_code'] = $code;
		$complete_where_1['o.status'] = 1;
		$complete_where_1['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];
		//$complete_where_1['o.store_code'] = $code;

		$money_list_com = db('order_goods')->alias("g")
					->join("pos_order o", "o.store_code=g.store_code and o.id=g.order_id")
					->field("sum(o.receivable_price) as revenue, sum(g.cost_price*g.goods_num) as cost_basic")
					->where($complete_where_1)
					->select();

		$complete_cost_basic = $money_list_com[0]['cost_basic'];


		$profit = 0;
		if(empty($money_list))
		{
			
		} else {
			$profit = number_format($order_cash_money+$order_alibaba_money+$order_wechat_money- $money_list[0]['cost_basic'], 2);
		}

		$complete_profit = $order_cash_money +$order_alibaba_money+$order_wechat_money-$order_refund_money-$complete_cost_basic;



		//exit("total order no = {$order_total_number}  refund order no = {$order_refund_number}  refund money = {$order_refund_money}  order cash = {$order_cash_money} order ali = {$order_alibaba_money}  order wechat = {$order_wechat_money}  ==".json_encode($money_list));
		
		return [$order_total_number, $order_cash_money+$order_alibaba_money+$order_wechat_money, $order_cash_money, $order_alibaba_money, $order_wechat_money, $order_refund_money, $order_refund_number, $profit, $refund_cash, $refund_ali, $refund_wechat,$complete_profit, $complete_cost_basic];


	}

	/**
	 * 收银员业绩
	 * @param  [type] $code       [description]
	 * @param  [type] $record_no  [description]
	 * @param  [type] $page_no    [description]
	 * @param  [type] $start_date [description]
	 * @param  [type] $end_date   [description]
	 * @param  [type] $staff_id   [description]
	 * @return [type]             [description]
	 */
	public static function getShiftOrder($code, $record_no, $page_no, $start_date, $end_date, $staff_id=null)
	{
		if(!empty($code))
		{
			$where['o.store_code'] = $code;
		}

		if(is_numeric($staff_id)&&intval($staff_id)>=1)
		{
			$where['o.uid'] = $staff_id;
		}

		if(!empty($start_date)&&!empty($end_date)&&strtotime($end_date)>strtotime($start_date))
		{
			$where['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];
		}


		$money_list = db('order')->alias("o")
			->join("pos_user u", "u.id=o.uid and o.store_code=u.store_code")
			->field("o.*, u.realname, u.id as u_id, 0 as wechat, 0 as alibaba, 0 as unipay, 0 as cash")
			->where($where)
			// 这里有问题，不能用金额排序，加上这段就报错
			->limit(($page_no-1)*$record_no, $record_no)
			->select();

		foreach ($money_list as $key => $value) {
			if($value['pay_type']==0)
			{
				// 现金
				$money_list[$key]['cash'] = $money_list[$key]['receivable_price'];
			}


			if($value['pay_type']==1)
			{
				// 现金
				$money_list[$key]['wechat'] = $money_list[$key]['receivable_price'];
			}

			if($value['pay_type']==2)
			{
				// 现金
				$money_list[$key]['alibaba'] = $money_list[$key]['receivable_price'];
			}

			if($value['pay_type']==3)
			{
				// 现金
				$money_list[$key]['unipay'] = $money_list[$key]['receivable_price'];
			}


		}

		$count = db('order')->alias("o")
			->join("pos_user u", "u.id=o.uid and o.store_code=u.store_code")
			->field("o.*, u.realname, u.id as u_id, 0 as wechat, 0 as alibaba, 0 as unipay, 0 as cash")
			->where($where)
			// 这里有问题，不能用金额排序，加上这段就报错
			->count();
			

		return [$money_list, $count];

	}

	public static function MemberRevenue($code, $start_date=null, $end_date=null)
	{

		if($start_date == null && $end_date == null)
		{
			$start_date = date("Y-m-d 0:0:0", time());
			$end_date = date("Y-m-d 23:59:59", time());
		}

		$where_new_member = [];
		$where_new_member['store_code'] = $code;

		$where_new_member['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];
		$new_member_id_list = Member::where($where_new_member)->field("id")->select();
		$id_list = array_column($new_member_id_list, "id");



		$s = strtotime($start_date)*1000;
		$d = strtotime($end_date)*1000;
		$sql = "select * from pos_member where store_code='{$code}' and create_time < '{$s}' and create_time > '{$d}' ";
		$old_member_id_list = Member::query($sql);

		$new_member_count = count($id_list);



		// 新会员
		$where_new_member = [];
		$where_new_member['store_code'] = $code;
		$where_new_member['status'] = 1; // 已完成
		$where_new_member['mid'] = array("in", $id_list);
		$where_new_member['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];

		$id_list[] = 0;
		// 老会员
		$where_old_member = [];
		$where_old_member['store_code'] = $code;
		$where_old_member['status'] = 1; // 已完成
		$where_old_member['mid'] = array("not in", $id_list);
		$where_old_member['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];

		// 获取指定日期的金额
		$new_member_order_sum = self::where($where_new_member)->sum('receivable_price');
		$old_member_order_sum = self::where($where_old_member)->sum('receivable_price');

		$new_member_order_number = self::where($where_new_member)->count();
		$old_member_order_number = self::where($where_old_member)->count();



		$old_member_count = sizeof($old_member_id_list);


		return [$new_member_count, $old_member_count, $new_member_order_sum, $old_member_order_sum, $new_member_order_number, $old_member_order_number];
	}


	public static function OrderDis($code, $start_date=null, $end_date=null, $min, $max, $num)
	{
		$step = ($max-$min)/$num;

		$where['store_code'] = $code;
		$where['status'] = 1;
		$where['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];
		$total_order_num = self::where($where)->count();

		$ret = [];
		$starter = $min;
		$end = $min;
		while($end < $max)
		{

			$starter = $end;
			$end += $step;
			$tmp['range'] = strval(intval($starter)).'--'.strval(intval($end));
			
			$where['receivable_price'] = ['between',strval($starter).",".strval($end-0.1)];
			$tmp['count'] = self::where($where)->count();

			$ret[] = $tmp;
		}


		$my['total'] = $total_order_num;
		$my['data_list'] = $ret;

		return [$my, 1];
	}





}

?>