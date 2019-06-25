<?php
namespace app\model;

use think\Model;
use app\model\User;
use think\Db;

class ConsumePerOrder extends Model
{
	protected $pk = 'id';
	protected $table="pos_consume_per_order";

	/**
	 * type  =1 小时
	 * type = 2 日
	 * type = 3 月
	 * @param  [type] $code    [description]
	 * @param  [type] $earlier [description]
	 * @param  [type] $later   [description]
	 * @param  [type] $tyoe    [description]
	 * @return [type]          [description]
	 */
	public static function createRecord($code, $earlier, $later, $type)
	{
		// 对所有商家的订单信息，进行采集
		$store_code_list = User::getStoreCodeList();

		if($code!== null)
		{
			$ab['store_code'] = $code;
			$store_code_list = [];
			$store_code_list[] = $ab;
		}
		//exit(json_encode($store_code_list));
		// 分批次，计算
		// 
		$count = 0;
		$list = [];
		foreach ($store_code_list as $key => $value) {
			//exit($value->store_code);


			//去重
			//Db::table("pos_general_log")
			$is_exist = self::where(['code'=>$type, 'check_date'=>$earlier,'store_code'=>$value['store_code']])->find();
			if(!is_null($is_exist))
			{
				continue;
			}

			// 计数器
			$count +=1;
			if($count >10)
				break;

			$list[] = $value['store_code'];
			unset($where);
			$where['o.store_code'] = $value['store_code'];
			$where['o.create_time'] = ['between',strval(strtotime($earlier)*1000).",".strval(strtotime($later)*1000)];
			$where['o.status'] = 1;
			$money_list = db('order')->alias("o")
					   ->join("pos_order_goods g", "o.store_code=g.store_code and o.id=g.order_id")
					   ->join("pos_goods g2", "g2.store_code=g.store_code and g2.goods_sn=g.goods_sn")
					   ->join("pos_category cat", "cat.id=g2.cat_id")
					   ->field("sum(g.subtotal_price) as revenue,count(g.order_id) as order_number, sum(goods_num) as goods_number, sum(g.goods_num*g.goods_price) as sales, sum(g.cost_price*g.goods_num) as cost_basic")
					   ->where($where)
					   ->order('revenue','desc')
					   ->select();


			$new_rec = new ConsumePerOrder();
			$new_rec->code = $type;//小时级
			$new_rec->store_code = $value['store_code'];
			$new_rec->check_date = $earlier;
			$new_rec->sales = $money_list[0]['sales']?$money_list[0]['sales']:0;
			$new_rec->revenue = $money_list[0]['revenue']?$money_list[0]['revenue']:0;
			$new_rec->order_no = $money_list[0]['order_number']?$money_list[0]['order_number']:0;
			$new_rec->goods_num = $money_list[0]['goods_number']?$money_list[0]['goods_number']:0;
			$new_rec->cost_basic = $money_list[0]['cost_basic']?$money_list[0]['cost_basic']:0;
			$ret = $new_rec->save();

			if($ret !== false)
			{
				// 记录日志
			}

			
		}
		return $list;
	}

	public static function getList($code, $start_date, $end_date, $type)
	{

		$store_code_list = User::getStoreCodeList();

		// exit("{$start_date} -- {$end_date} -- {$type}");
		// 根据类型，设置步长，遍历
		$time_list = [];
		if($type == 3)
		{
			$later = date("Y-m-1 0:0:0", strtotime($end_date));
			$later_year = date("Y", strtotime($end_date));
			$later_month = date("m", strtotime($end_date));
			$xx = intval($later_month) -1;
			$yy = intval($later_year) - 1;

			// 向前推 1个月
			if($later_month > 1)
			{
				$later_begin = date("Y-{$xx}-1 0:0:0", strtotime($end_date));	
			} else {
				$later_begin = date("{$yy}-12-1 0:0:0", strtotime($end_date));	

			}



			$time_list = [];
			while(strtotime($later_begin) >= strtotime($start_date))
			{
				$time_list[] = $later_begin;

				// 递减
				$current_year = date("Y", strtotime($later_begin));
				$current_month = date("m", strtotime($later_begin));

				$year_1 = intval($current_year) -1 ;
				$money_1 = intval($current_month) - 1;

				if($current_month > 1)
				{
					$later_begin = date("Y-{$money_1}-1 0:0:0", strtotime($later_begin));	
				} else {
					$later_begin = date("{$year_1}-12-1 0:0:0", strtotime($later_begin));	

				}

			}
		} elseif (intval($type) ==2) {
			// 按天
			$later = date("Y-m-d 0:0:0", strtotime($end_date));
			// 向前推一天
			$later = date("Y-m-d 0:0:0", strtotime($later)-60*60*24);			



			while(strtotime($later) > strtotime($start_date))
			{
				$time_list[] = $later;

				$later  = date("Y-m-d 0:0:0", strtotime($later)-60*60*24);
			}
		} elseif (intval($type) ==1) {
			$later = date("Y-m-d H:0:0", strtotime($end_date));
						// 向前推一天
			$later = date("Y-m-d H:0:0", strtotime($later)-60*60);	

			while(strtotime($later) > strtotime($start_date))
			{
				$time_list[] = $later;

				$later  = date("Y-m-d H:0:0", strtotime($later)-60*60);

			}
		}



		foreach ($time_list as $key => $value) {
			unset($mywhere);
			$mywhere['store_code'] = $code;
			$mywhere['check_date'] = $value;
			$mywhere['code'] = $type;
			$exist = self::where($mywhere)->find();

			if(is_null($exist))
			{
				if($type == 3)
				{
					$tmp_y = date("Y", strtotime($value));
					$tmp_m = date("m", strtotime($value));

					$tmp_m_p = intval($tmp_m) + 1;
					$end = $value;
					if($tmp_m < 12)
					{
						$end = date("Y-{$tmp_m_p}-d 0:0:0", strtotime($value));
					}

					self::createRecord($code, $value, $end, $type);
				} elseif ($type ==2) {
					self::createRecord($code, $value, strtotime($value) + 60*60*24, $type);
				} elseif ($type ==1) {
					self::createRecord($code, $value, strtotime($value) + 60*60, $type);
				}	
			}
			
		}

		$where['store_code'] = $code;
		$where['check_date'] = ['between', $start_date.",".$end_date];
		$where['code'] = $type;
		$count = self::where($where)->count();
		$list =  self::where($where)->order('check_date','desc')->select();
		return [$list, $count];
	}

}

?>