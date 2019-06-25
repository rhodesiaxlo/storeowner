<?php
namespace app\model;

use app\model\Order;
use app\model\OrderGoods;
use think\Model;
use think\Db;

class Member extends Model
{
	protected $pk = 'uid';
	protected $table="pos_member";
	protected $createTime = false;
	public static function getList($store_code, $page_no, $record_no, $name, $mobile)
	{
		$where['store_code'] = $store_code;
		if(!empty($name))
		{
			$where['uname'] = array("like", "%{$name}%");
		}

		if(!empty($mobile))
		{
			$where['phone'] = array("like", "%{$mobile}%");
		}

		$where['deleted'] = 0;

		$list = self::where($where)->limit(($page_no-1)*$record_no, $record_no)->select();
		$total_number = self::where($where)->count(); 
		return [$list, $total_number];
	}

	/**
	 * 会员统计
	 * @return [type] [description]
	 */
	public static function memStatic($store_code, $start_date, $end_date)
	{
		return [100,12,3];
	}

	public static function newMemberAndOldMember($code, $start_date=null, $end_date=null)
	{
		$where['store_code'] = $code;
		$where['create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];
		$new = 	self::where($where)->count();
		$all = 	self::where(['store_code'=>$code])->count();
		return [$new, $all];
	}

	/**
	 * 根据消费排名
	 * @param  [type] $code       [description]
	 * @param  [type] $start_date [description]
	 * @param  [type] $end_date   [description]
	 * @return [type]             [description]
	 */
	public static function rankByCompu($code, $start_date=null, $end_date=null)
	{
		$where = [];
		$where['o.store_code'] = $code;
		$where['o.status'] = 1; // 已完成
		$where['o.mid'] = array('neq', 0);
		$where['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];

		$list = db('order')->alias("o")
						 	->join("pos_member m", "m.id=o.mid and m.store_code=o.store_code")
						   ->field("m.uname,m.phone, count(o.order_sn) as order_number,sum(receivable_price) as total_consumption")

						   ->where($where)
						   //->group('o.mid')
						   ->order("total_consumption desc")
						   ->select();


		$total_number = db('order')->alias("o")
						   ->where($where)
						   ->sum('receivable_price');

		return [$list, $total_number];
	}

	/**
	 * 消费次数
	 * @param  [type] $code       [description]
	 * @param  [type] $start_date [description]
	 * @param  [type] $end_date   [description]
	 * @return [type]             [description]
	 */
	public static function rankByCompuNo($code, $start_date=null, $end_date=null)
	{
		$where = [];
		$where['o.store_code'] = $code;
		$where['o.status'] = 1; // 已完成
		$where['o.mid'] = array('neq', 0);
		$where['o.create_time'] = ['between',strval(strtotime($start_date)*1000).",".strval(strtotime($end_date)*1000)];

		$list = db('order')->alias("o")
						 	->join("pos_member m", "m.id=o.mid")
						   ->field("o.*, count(o.order_sn) as order_number")
						   ->where($where)
						   ->group('o.mid')
						   ->order("order_number desc")
						   //->limit(($page_no-1)*$record_no, $record_no)
						   ->select();


		$total_number =   db('order')->alias("o")
						   ->where($where)
						   ->group('o.mid')
						   ->count();

		return [$list, $total_number];

	}


	/**
	 * 积分分布
	 * @param  [type] $code     [description]
	 * @param  [type] $min      [description]
	 * @param  [type] $max      [description]
	 * @param  [type] $interval [description]
	 * @return [type]           [description]
	 */
	public static function pointSplit($code, $min=null, $max=null, $interval)
	{
		$result = [];
		$step = ($max-$min)/$interval;

		$start = $min;
		$end  = $start;
		//echo "min = {$min}  max={$max} step = {$step}  interval = {$interval}";
		$ret = [];
		while($end < $max)
		{
			$start = $end;
			$end = $end + $step;

			$result['range'] = strval($start).'--'.strval($end);

			$where['points'] = ['between', $start.",".$end];
			// 计算积分在区间 start  end 范围内的会员的 个数
			$result['count'] = self::where($where)->count();
			$ret [] = $result;
		}

	 	return $ret;
	}

	/**
	 * 根据会员的客单价分析
	 * @param  [type] $code       [description]
	 * @param  [type] $start_date [description]
	 * @param  [type] $end_date   [description]
	 * @param  [type] $min        [description]
	 * @param  [type] $max        [description]
	 * @param  [type] $num        [description]
	 * @return [type]             [description]
	 */
	public static function conByPerson($code, $start_date, $end_date, $min, $max, $num)
	{

		// 筛选出这段时间所有的会员个数和会员列表
		$order_where['store_code'] = $code;
		$order_where['status'] = 1;
		$order_where['mid'] = ['neq', 0];
		$order_where['create_time'] = ['between', strval(strtotime($start_date)*1000).','.strval(strtotime($end_date)*1000)];
		$mid_list = Order::where($order_where)->field('mid')->group('mid')->select();

		$id_list = [];
		foreach ($mid_list as $key => $value) {
			$id_list[] = $value->mid;
		}


		$total = sizeof($id_list);



		$m_con_list  = [];
		// 遍历会员列表，计算单价
		foreach ($id_list as $key => $value) {
			$cur_mid = $value;

			// 计算这段时间的用户客单价
			$tmp_where['store_code'] = $code;
			$tmp_where['status'] = 1;
			$tmp_where['mid'] = $cur_mid;
			$tmp_where['create_time'] = ['between', strval(strtotime($start_date)*1000).','.strval(strtotime($end_date)*1000)];


			$order_no = Order::where($tmp_where)->count();

			$order_total = Order::where($tmp_where)->sum('receivable_price');

			$per = $order_total/$order_no;

			$m_con_list[$cur_mid] = $per;
		}
		


		// 根据分段，计算会员个数
		 $step = ($max-$min)/$num;

		 $start = $min;
		 $end = $min;

		 $ran_list = [];
		 while($end <$max)
		 {
		 	$start = $end;
		 	$end +=$step;

		 	$tmp['low'] = $start;
		 	$tmp['high'] = $end;
		 	$tmp['num'] = 0;
		 	$tmp['range'] = strval($start).'--'.strval($end);

		 	$ran_list[] = $tmp;
		 }



		 foreach ($m_con_list as $key => $value) {
		 	foreach ($ran_list as $key1 => $value1) {
		 		// value 代表客单价
		 		
		 		if($value1['low'] < $value && $value< $value1['high'])
		 		{

		 			$ran_list[$key1]['num'] += 1;
		 		}
		 	}
		 }

		 //exit(json_encode($ran_list));
		
		return [$ran_list, $total];

	}






}

?>