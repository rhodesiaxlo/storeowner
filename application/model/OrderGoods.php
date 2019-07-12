<?php
namespace app\model;

use think\Model;
use think\Db;
use app\model\Order;
use app\model\Category;

class OrderGoods extends Model
{
	protected $pk = 'uid';
	protected $table="pos_order_goods";

	public static function getRank($code, $start_date=null, $end_date=null)
	{

		if($start_date == null && $end_date == null)
		{
			$start_date = date("Y-m-d 0:0:0", time());
			$end_date = date("Y-m-d 23:59:59", time());
		}

		// 已完成
		$where = [];
		$where['g.store_code'] = $code;
		$where['o.status'] = 1; // 已完成
		$where['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];



		$money_list = db('order_goods')->alias("g")
						   ->join("pos_order o", "o.store_code=g.store_code and o.id=g.order_id")
						   ->field("g.goods_name,g.order_id,o.id as o_id,0 as rate, o.store_code as o_store_code, o.status,o.create_time,sum(g.subtotal_price) as goods_total_price")
						   ->where($where)
						   ->group('g.goods_sn')
						 ->order("goods_total_price", "desc")
						   ->select();



		$mon_total = 0;
		foreach ($money_list as $key => $value) {
			$mon_total +=$value['goods_total_price'];
		}

		foreach ($money_list as $key => $value) {
			$money_list[$key]['rate'] = number_format( $value['goods_total_price']/$mon_total, 2)*100;
		}




		$no_list = db('order_goods')->alias("g")
				   ->join("pos_order o", "o.store_code=g.store_code and o.id=g.order_id")
				   ->field("g.goods_name,g.order_id,o.id as o_id,0 as rate, o.store_code as o_store_code, o.status,o.create_time,g.goods_num, sum(g.goods_num) as goods_total_no")
				   ->where($where)
				   ->group('g.goods_sn')
				   ->order("goods_total_no", "desc")
				   ->select();



		$no_total = 0;
		foreach ($no_list as $key => $value) {
			$no_total +=$value['goods_total_no'];
		}

		foreach ($no_list as $key => $value) {
			$no_list[$key]['rate'] = number_format( $value['goods_total_no']/$no_total, 2)*100;
		}

		
		
		return [$money_list,  $no_list];
	}

	public static function getOrderByCategory($code, $start_date, $end_date,$is_order_by_money)
	{
		$where = [];
		$where['g.store_code'] = $code;
		$where['o.status'] = 1; // 已完成
		$where['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];


		$order_no = Order::where(['store_code'=>$code,'status'=>1, 'create_time'=>['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)]])->count();
		
		// $allcat = Category::where(1)->select();
		// $cat_ar = [];
		// foreach ($allcat as $key => $value) {
		// 	$cat_ar[$value->id] = $order_no = Order::where(['store_code'=>$code,'status'=>1, 'create_time'=>['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)],''])->count();
		// }

		if(intval($is_order_by_money)>0)
		{
			$money_list = db('order_goods')->alias("g")
					   ->join("pos_order o", "o.store_code=g.store_code and o.id=g.order_id")
					   ->join("pos_goods g2", "g2.store_code=g.store_code and g2.goods_sn=g.goods_sn")
					   ->join("pos_category cat", "cat.id=g2.cat_id")
					   //->field("g2.cat_id,g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time")
					   ->field("cat.id as cat_id, cat.name,sum(g.subtotal_price) as revenue,{$order_no} as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic")
					   ->where($where)
					   ->group('cat.id')
					   // 这里有问题，不能用金额排序，加上这段就报错
					   ->order('revenue','desc')
					   ->select();
			// $order_no = self::where(['store_code'=>$code,'status'=>1, 'create_time'=>['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)]])->count();

		} else {
			$money_list = db('order_goods')->alias("g")
					   ->join("pos_order o", "o.store_code=g.store_code and o.id=g.order_id")
					   ->join("pos_goods g2", "g2.store_code=g.store_code and g2.goods_sn=g.goods_sn")
					   ->join("pos_category cat", "cat.id=g2.cat_id")
					   //->field("g2.cat_id,g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time")
					   ->field("cat.id as cat_id, cat.name,sum(g.subtotal_price) as revenue,{$order_no} as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic")
					   ->where($where)
					   ->group('cat.id')
					   ->order("goods_number", "desc")
					   ->select();

		}


		// 不包含五码商品
		$total_actual = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id and g.goods_sn!='无码商品'")
				   ->where($where)
				      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->sum('g.subtotal_price');

		$total_discount = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id and g.goods_sn!='无码商品'")
				   ->where($where)
				      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->sum('g.discounts_price');

		$total_order_no = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id and g.goods_sn!='无码商品'")
				   ->where($where)
				      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->count("o.id");

		$total_goods_num = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id and g.goods_sn!='无码商品'")
				   ->where($where)
				      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->sum("g.goods_num");

		$list_no = sizeof($money_list);

		// 记录数 总销量 总应收 总优惠  总实收
		return [$money_list, $total_actual, $total_discount, $total_order_no, $total_goods_num, $list_no, $order_no];
	}


	public static function getOrderByPayment($code, $start_date, $end_date, $is_order_by_money)
	{
		$where = [];
		$where['g.store_code'] = $code;
		$where['o.status'] = 1; // 已完成
		$where['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];

		if(intval($is_order_by_money) > 0 )
		{
			$money_list = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->field("o.pay_type,sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic")
				   ->where($where)
				   ->group('o.pay_type')
				   ->order('revenue', 'desc')
				   ->select();

		} else {
			$money_list = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->field("o.pay_type,sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic")
				   ->where($where)
				   ->group('o.pay_type')
				   ->order('goods_number', 'desc')
				   ->select();

		}

		// 不包含五码商品
		$total_actual = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->where($where)
				      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->sum('g.subtotal_price');

		$total_discount = db('order')->alias("o")
		   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
		   ->where($where)
		      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
		   ->sum('g.discounts_price');

		$total_order_no = db('order')->alias("o")
		   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
		   ->where($where)
		      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
		   ->count("o.id");

		$total_goods_num = db('order')->alias("o")
		   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
		   ->where($where)
		      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
		   ->sum("g.goods_num");

		$list_no = sizeof($money_list);

		return [$money_list, $total_actual, $total_discount, $total_order_no, $total_goods_num, $list_no];
	}


	public static function getOrderByMembership($code, $start_date, $end_date, $is_order_by_money)
	{
		$where = [];
		$where['g.store_code'] = $code;
		$where['o.status'] = 1; // 已完成
		$where['o.mid'] = 0; // 已完成
		$where['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];

		$member_total = Order::where(['store_code'=>$code, 'status'=>1, 'mid'=>0,'create_time'=>['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)]])->count();

		$money_list = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->field("o.pay_type,sum(g.subtotal_price) as revenue,{$member_total} as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic,0 as mid")
				   ->where($where)
				   ->select();

		$where2 = [];
		$where2['g.store_code'] = $code;
		$where2['o.status'] = 1; // 已完成
		$where2['o.mid'] = array('neq',0); // 已完成
		$where2['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];


		$non_member_total = Order::where(['store_code'=>$code, 'status'=>1, 'mid'=>array('neq',0),'create_time'=>['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)]])->count();
		$money_list2 = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->field("o.pay_type,sum(g.subtotal_price) as revenue,{$non_member_total} as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic,1 as mid")
				   ->where($where2)
				   ->select();

		$money_list3 = array_merge($money_list, $money_list2);
		// 不包含五码商品
		$where3 = [];
		$where3['g.store_code'] = $code;
		$where3['o.status'] = 1; // 已完成
		$where3['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];


		$total_actual = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->where($where3)
				    ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->sum('g.subtotal_price');



		$total_discount = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->where($where3)
				    ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->sum('g.discounts_price');


		$total_order_no = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->where($where3)
				    ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->count("o.id");


		$total_goods_num = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->where($where3)
				    ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->sum("g.goods_num");

		$list_no = sizeof($money_list3);
		return [$money_list3, $total_actual, $total_discount, $total_order_no, $total_goods_num, $list_no];
	}


	public static function getOrderByCashier($code, $start_date, $end_date, $is_order_by_money)
	{
		$where = [];
		$where['g.store_code'] = $code;
		$where['o.status'] = 1; // 已完成
		$where['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];

		$order_number = Order::where(['store_code'=>$code, 'status'=>1, 'create_time'=>['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)]])->count();

		if(intval($is_order_by_money) > 0)
		{
			$money_list = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->join("pos_user u","u.store_code=o.store_code and u.id=o.uid")
				   ->field("u.realname,o.pay_type,sum(g.subtotal_price) as revenue,{$order_number} as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic")
				   ->where($where)
				   ->group('o.uid')
				   ->order('revenue', 'desc')
				   ->select();

		} else {
			$money_list = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->join("pos_user u","u.store_code=o.store_code and u.id=o.uid")
				   ->field("u.realname,o.pay_type,sum(g.subtotal_price) as revenue,{$order_number} as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic")
				   ->where($where)
				   ->group('o.uid')
				   ->order('goods_number', 'desc')
				   ->select();

		}
		// $money_list = db('order')->alias("o")
		// 		   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
		// 		   ->join("pos_user u","u.store_code=o.store_code and u.id=o.uid")
		// 		   ->field("u.realname,o.pay_type,sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic")
		// 		   ->where($where)
		// 		   ->group('o.uid')
		// 		   ->select();



		// 不包含五码商品
		$total_actual = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->where($where)
				      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->sum('g.subtotal_price');

		$total_discount = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->where($where)
				      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->sum('g.discounts_price');

		$total_order_no = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->where($where)
				      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->count('o.id');

		$total_goods_num = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->where($where)
				      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->sum('g.goods_num');

		$list_no = sizeof($money_list);

		return [$money_list, $total_actual, $total_discount, $total_order_no, $total_goods_num, $list_no];
	}

	public static function getStatic($code, $start_date, $end_date, $goods_name=null, $goods_sn=null, $is_order_by_money=0)
	{
		$where = [];
		$where['g.store_code'] = $code;
		$where['o.status'] = 1; // 已完成
		$where['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];

		if(!empty($goods_name))
		{
			$where['g.goods_name'] = array("like","%".$goods_name."%");
		}

		if(!empty($goods_sn))
		{
			$where['g.goods_sn'] = array("like","%".$goods_sn."%");
		}

		if(intval($is_order_by_money)>0)
		{
			$money_list = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->join("pos_goods g2", "g2.store_code=g.store_code and g2.goods_sn=g.goods_sn")
				   ->join("pos_category cat", "cat.id=g2.cat_id")
				   ->field("cat.name as cat_name, g2.goods_name,g2.goods_sn,g2.spec,g2.unit,g2.repertory,g2.create_time as goods_create_time,sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic")
				   ->where($where)
				   ->group('g.goods_sn')
				   ->order('revenue', 'desc')
				   ->select();
		} else {
			$money_list = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
				   ->join("pos_goods g2", "g2.store_code=g.store_code and g2.goods_sn=g.goods_sn")
				   ->join("pos_category cat", "cat.id=g2.cat_id")
				   ->field("cat.name as cat_name, g2.goods_name,g2.goods_sn,g2.spec,g2.unit,g2.repertory,g2.create_time as goods_create_time,sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic")
				   ->where($where)
				   ->group('g.goods_sn')
				   ->order('goods_number', 'desc')
				   ->select();
		}
		// $money_list = db('order')->alias("o")
		// 		   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
		// 		   ->join("pos_goods g2", "g2.store_code=g.store_code and g2.goods_sn=g.goods_sn")
		// 		   ->join("pos_category cat", "cat.id=g2.cat_id")
		// 		   ->field("cat.name as cat_name, g2.goods_name,g2.goods_sn,g2.spec,g2.unit,g2.repertory,g2.create_time as goods_create_time,sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic")
		// 		   ->where($where)
		// 		   ->group('g.goods_sn')
		// 		   ->select();
		// 不包含五码商品
		$total_actual = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id and g.goods_sn!='无码商品'")
				   ->where($where)
				      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->sum('g.subtotal_price');

		$total_discount = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id and g.goods_sn!='无码商品'")
				   ->where($where)
				      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->sum('g.discounts_price');

		$total_order_no = db('order')->alias("o")
			   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id and g.goods_sn!='无码商品'")
			   ->where($where)
			      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
			   ->count('o.id');

		$total_goods_num = db('order')->alias("o")
				   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id and g.goods_sn!='无码商品'")
				   ->where($where)
				      ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
				   ->sum('g.goods_num');

		// $total_customerpay = db('order')->alias("o")
		//    ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id and g.goods_sn!='无码商品'")
		//    ->where($where)
		//       ->field("g.goods_name,g.order_id,o.id as o_id, o.store_code as o_store_code, o.status,o.create_time,g.goods_num")
		//    ->sum('o.practical_price');
		//    
		$total_customerpay = 0;
		foreach ($money_list as $key => $value) {
			$total_customerpay +=$value['revenue'];
		}

		$list_no = sizeof($money_list);

		return [$money_list, $total_actual, $total_discount, $total_order_no, $total_goods_num, $list_no, $total_customerpay];
		// return [$money_list, $total_money];
	}

	public static function get7CycleByNum($code, $record_no, $page_no, $is_order_by_money=0)
	{
		$now_date = date("Y-m-d 23:59:59", time());
        $now_date_7 = date("Y-m-d 23:59:59", strtotime("-7 days"));
        $now_date_14 = date("Y-m-d 23:59:59", strtotime("-14 days"));

		$where = [];
		$where['g.store_code'] = $code;
		$where['o.status'] = 1; // 已完成
		$where['o.create_time'] = ['between',strval(strtotime($now_date_7)*1000).",".strval(strtotime($now_date)*1000)];

		$money_list_7 = db('order')->alias("o")
		   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
		   ->field("g.goods_name,sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic,0 as mid,0 as previous_sales,0 as previous_goods_number")
		   ->where($where)
  		   ->group('g.goods_sn')
		   ->select();



		$columns_7 = array_column($money_list_7, "goods_name");

		$new = [];
		foreach ($money_list_7 as $key => $value) {
			$new[$columns_7[$key]] = $value;
		}


		$where_14 = [];
		$where_14['g.store_code'] = $code;
		$where_14['o.status'] = 1; // 已完成
		$where_14['o.create_time'] = ['between',strval(strtotime($now_date_14)*1000).",".strval(strtotime($now_date_7)*1000)]; 

		$money_list_14 = [];
		if(intval($is_order_by_money)>1)
		{
			$money_list_14 = db('order')->alias("o")
			   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
			   ->field("g.goods_name,sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic,0 as mid,0 as future_sales,0 as future_goods_number")
			   ->where($where_14)
	  		   ->group('g.goods_sn')
	  		   ->order('revenue','desc')
			   ->select();

		} else {
			$money_list_14 = db('order')->alias("o")
			   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
			   ->field("g.goods_name,sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic,0 as mid,0 as future_sales,0 as future_goods_number")
			   ->where($where_14)
	  		   ->group('g.goods_sn')
	  		   ->order('goods_num','desc')
			   ->select();
		}


		// $money_list_14 = db('order')->alias("o")
		//    ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
		//    ->field("g.goods_name,sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic,0 as mid,0 as future_sales,0 as future_goods_number")
		//    ->where($where_14)
  // 		   ->group('g.goods_sn')
		//    ->select();


		$columns_14 = array_column($money_list_14, "goods_name");
		$new_14 = [];
		foreach ($money_list_14 as $key2 => $value2) {
			$new_14[$columns_14[$key2]] = $value2;
		}


		 // 遍历 _7 查找
		 foreach ($new_14 as $key3 => $value3) {

		 	if(!array_key_exists($key3, $new))
		 	{
		 		// 上一周的的商品不存在本周起，所以销量有所下跌
		 		$new_14[$key3]['future_goods_number'] = 0;
		 	} else {
		 		// 存在，比销量
		 		if($new[$key3]['goods_number'] < $new_14[$key3]['goods_number'])
		 		{
		 			$new_14[$key3]['future_goods_number'] = $new[$key3]['goods_number'];

		 		} else {
		 			// 大于或者等于，这条记录没有意思，去掉
					$new_14[$key3]['future_goods_number'] = 10000;		 			
		 		}
		 	}
		 }


		 $last_ret = [];
		foreach ($new_14 as $key => $value) {
			if($value['future_goods_number']!=10000)
			{
				$last_ret[] = $value;
			}
		}



		$total_number = sizeof($last_ret);
		$indic = 0;
		$return = [];
		foreach ($last_ret as $key4 => $value4) {
			$indic +=1;
			if(($indic > ($page_no-1)*$record_no) && ($indic <= ($page_no)*$record_no))
			{
				// 输出所有记录
				$return[] = $value4;
			}
		}


		 return [$return, $total_number];

	}


	public static function get7CycleByMoney($code)
	{
		$now_date = date("Y-m-d 23:59:59", time());
        $now_date_7 = date("Y-m-d 23:59:59", strtotime("-7 days"));
        $now_date_14 = date("Y-m-d 23:59:59", strtotime("-14 days"));

		$where = [];
		$where['g.store_code'] = $code;
		$where['o.status'] = 1; // 已完成
		$where['o.create_time'] = ['between',strval(strtotime($now_date_7)*1000).",".strval(strtotime($now_date)*1000)];

		$money_list_7 = db('order')->alias("o")
		   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
		   ->field("g.goods_name,sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic,0 as mid,0 as previous_sales,0 as previous_goods_number")
		   ->where($where)
  		   ->group('g.goods_sn')
		   ->select();



		$columns_7 = array_column($money_list_7, "goods_name");

		$new = [];
		foreach ($money_list_7 as $key => $value) {
			$new[$columns_7[$key]] = $value;
		}


		$where_14 = [];
		$where_14['g.store_code'] = $code;
		$where_14['o.status'] = 1; // 已完成
		$where_14['o.create_time'] = ['between',strval(strtotime($now_date_14)*1000).",".strval(strtotime($now_date_7)*1000)]; 
		$money_list_14 = db('order')->alias("o")
		   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
		   ->field("g.goods_name,sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic,0 as mid,0 as future_sales,0 as future_goods_number")
		   ->where($where_14)
  		   ->group('g.goods_sn')
		   ->select();


		$columns_14 = array_column($money_list_14, "goods_name");
		$new_14 = [];
		foreach ($money_list_14 as $key2 => $value2) {
			$new_14[$columns_14[$key2]] = $value2;
		}


		 // 遍历 _7 查找
		 foreach ($new_14 as $key3 => $value3) {

		 	if(!array_key_exists($key3, $new))
		 	{
		 		// 上一周的的商品不存在本周起，所以销量有所下跌
		 		$new_14[$key3]['future_sales'] = 0;
		 	} else {
		 		// 存在，比销量
		 		if($new[$key3]['goods_number'] < $new_14[$key3]['goods_number'])
		 		{
		 			$new_14[$key3]['future_sales'] = $new[$key3]['future_sales'];

		 		} else {
		 			// 大于或者等于，这条记录没有意思，去掉
					$new_14[$key3]['future_sales'] = 10000;		 			
		 		}
		 	}
		 }


		 return $new_14;
	}


	/**
	 * 7天内无任何销量的商品
	 * @param  [type] $code [description]
	 * @return [type]       [description]
	 */
	public static function getStagGoods($code, $record_no, $page_no,$is_order_by_money=0)
	{

		$now_date = date("Y-m-d 23:59:59", time());
        $now_date_7 = date("Y-m-d 23:59:59", strtotime("-7 days"));
        $now_date_14 = date("Y-m-d 23:59:59", strtotime("-90 days"));

		$where = [];
		$where['g.store_code'] = $code;
		$where['o.status'] = 1; // 已完成
		$where['o.create_time'] = ['between',strval(strtotime($now_date_7)*1000).",".strval(strtotime($now_date)*1000)];

		$order_total_7 = Order::where(['store_code'=>$code,'status'=>1, 'create_time'=>['between',strval(strtotime($now_date_7)*1000).",".strval(strtotime($now_date)*1000)]])->count();

		$money_list_7 = db('order')->alias("o")
		   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
		   ->field("g.goods_name,sum(g.subtotal_price) as revenue,{$order_total_7} as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic,0 as mid")
		   ->where($where)
  		   ->group('g.goods_sn')
		   ->select();



		$columns_7 = array_column($money_list_7, "goods_name");

		$new = [];
		foreach ($money_list_7 as $key => $value) {
			$new[$columns_7[$key]] = $value;
		}


		$where_14 = [];
		$where_14['g.store_code'] = $code;
		$where_14['o.status'] = 1; // 已完成
		$where_14['o.create_time'] = ['between',strval(strtotime($now_date_14)*1000).",".strval(strtotime($now_date)*1000)]; 


		// $money_list_14 = db('order')->alias("o")
		//    ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
		//    ->field("g.goods_name,sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic,0 as mid,0 as future_goods_number_7")
		//    ->where($where_14)
  // 		   ->group('g.goods_sn')
		//    ->select();

		$money_list_14 = [];
		$order_number_total = Order::where(['store_code'=>$code, 'status'=>1, 'create_time'=>['between',strval(strtotime($now_date_14)*1000).",".strval(strtotime($now_date)*1000)]])->count();
		if(intval($is_order_by_money)>1)
		{
			$money_list_14 = db('order')->alias("o")
			   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
			   ->field("g.goods_name,sum(g.subtotal_price) as revenue,{$order_number_total} as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic,0 as mid,0 as future_goods_number_7")
			   ->where($where_14)
	  		   ->group('g.goods_sn')
	  		   ->order("revenue", "desc")
			   ->select();
		} else {
			$money_list_14 = db('order')->alias("o")
			   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
			   ->field("g.goods_name,sum(g.subtotal_price) as revenue,{$order_number_total} as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic,0 as mid,0 as future_goods_number_7")
			   ->where($where_14)
	  		   ->group('g.goods_sn')
	  		   ->order("goods_number", "desc")
			   ->select();
		}



		$columns_14 = array_column($money_list_14, "goods_name");
		$new_14 = [];
		foreach ($money_list_14 as $key2 => $value2) {
			$new_14[$columns_14[$key2]] = $value2;
		}



		 // 遍历 _7 查找
		 foreach ($new_14 as $key3 => $value3) {

		 	if(!array_key_exists($key3, $new))
		 	{
		 		// 上一周的的商品不存在本周起，所以销量有所下跌
		 		$new_14[$key3]['future_goods_number_7'] = 0;
		 	} else {
		 		// 存在，比销量
		 		$new_14[$key3]['future_goods_number_7'] = $new[$key3]['goods_number'];
		 	}
		 }


		
		$indic = 0;
		$return = [];
		foreach ($new_14 as $key4 => $value4) {

			if($new_14[$key4]['goods_number']>$new_14[$key4]['future_goods_number_7']&&$new_14[$key4]['future_goods_number_7']==0)
			{


				$indic +=1;
				if(($indic > ($page_no-1)*$record_no) && ($indic <= ($page_no)*$record_no))
				{
					// 输出所有记录
					$return[] = $value4;
				}

			}
		}

		// 排序
		$total_number = sizeof($return);

		 return [$return, $total_number];
	}

	public static function memberComAna($code, $name, $phone)
	{

		$where = [];
		$where['o.store_code'] = $code;
		$where['o.status'] = 1;

		$where['o.mid'] = array('neq', 0);
		if(!empty($name))
		{
			$where['m.uname'] = array("like","%".$name."%");
		}

		if(!empty($phone))
		{
			$where['m.phone'] = array("like","%".$phone."%");
		}


		$money_list = db('order')->alias("o")
			->join("pos_member m", "m.store_code=o.store_code and m.id=o.mid")
			->field("o.mid,m.uname, m.phone, m.idcard, m.points, count(o.local_id) as order_num, sum(o.receivable_price) as total_revenue")
			->where($where)
			->group('o.mid')
			->select();

		$mid = [];
		$new_list = [];
		foreach ($money_list as $key => $value) {
			$mid[] = $value['mid'];
			$new_list[$value['mid']] = $value;
		}


		foreach ($mid as $key => $value) {

			$tmp = Db::table("pos_order")->where(['status'=>1,'store_code'=>$code, 'mid'=>$value])->field('create_time')->order('create_time', 'desc')->find();
			//exit(json_encode($new_list[$value]));
			//exit($tmp['create_time']);
			$new_list[$value]['create_time'] = $tmp['create_time'];
			$new_list[$value]['delta'] = intval((time() - $tmp['create_time']/1000)/86400);
		}


		$count = sizeof($new_list);
		$tmp = [];
		foreach ($new_list as $key => $value) {
			$tmp[] = $value;
		}

		return [$tmp, $count];
	}

	





}

?>