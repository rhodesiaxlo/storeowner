<?php
namespace app\model;

use think\Model;
use think\Db;
use app\model\Goods;
use app\model\User;


class GoodsSnap extends Model
{
	protected $pk = 'uid';
	protected $table="pos_goods_snap";


	/**
	 * 预警中暑
	 * @return [type] [description]
	 */
	public static function getSnap($store_code, $end_date)
	{
		// 获取切片数据
		$where['store_code'] = $store_code;
		$timestr = strtotime($end_date);

		$check_time = date("Y-m-d 0:0:0", $timestr);

		$where['check_time'] = $check_time;

		$is_exist = self::where($where)->find();
		if(is_null($is_exist))
		{
			// 获取当前的
			
			$where1['store_code'] = $store_code;
			$where1['is_forsale'] = 1;
			$where1['goods_name'] = array('neq',"无码商品");

			$on_shelf = Goods::where($where1)->count();


			$off_where['store_code'] = $store_code;
			$off_where['is_forsale'] = 0;
			$off_where['goods_name'] = array('neq',"无码商品");


			$off_shelf = Goods::where($off_where)->count();


			$inv_where['store_code'] = $store_code;
			$inv_where['goods_name'] = array('neq',"无码商品");

			$total_inv = Goods::where($inv_where)->sum('repertory');


			$warning = Db::query('select count(*) as cc from pos_goods where repertory_caution > repertory and store_code=:code',['code'=>$store_code]);

			return [$on_shelf, $off_shelf, $total_inv, isset($warning)?$warning[0]['cc']:0];


			// return self::currentLy($store_code, date("Y-m-d 0:0:0", time()));
		} else {
			return [$is_exist->on_shelf, $is_exist->off_shelf, $is_exist->total_inven, $is_exist->warning_num];
		}

	}

	public static function currentLy($store_code, $check_time)
	{

		$where['store_code'] = $store_code;
		$where['is_forsale'] = 1;
		$where['goods_name'] = array('not like ',"%无码%");

		$on_shelf = Goods::where($where)->count();


		$off_where['store_code'] = $store_code;
		$off_where['is_forsale'] = 0;
		$off_where['goods_name'] = array('not like ',"%无码%");

		$off_shelf = Goods::where($off_where)->count();


		$inv_where['store_code'] = $store_code;
		$inv_where['goods_name'] = array('not like ',"%无码%");
		$total_inv = Goods::where($inv_where)->sum('repertory');


		$warning = Db::query('select count(*) as cc from pos_goods where repertory_caution > repertory and store_code=:code',['code'=>$store_code]);


		// 写入数据库
		$record = new GoodsSnap();
		$record->store_code  = $store_code;
		$record->check_time  = $check_time;
		$record->on_shelf    = $on_shelf;
		$record->off_shelf   = $off_shelf;
		$record->total_inven = $total_inv;
		$record->warning_num = isset($warning[0])?$warning[0]['cc']:0;
		$record->save();
		
		return [$on_shelf, $off_shelf, $total_inv, $warning];

	}

	public static function cronJob()
	{
		// 选取 50个不在 goods_snap 中的 store_code,执行查询操作，写入数据库
		// 
		$later = date("Y-m-d 0:0:0", time());

		$where['check_time'] = $later;


		$list = self::where($where)->field('store_code')->select();

		$code_list = array_column($list, 'store_code');

		$user_where['store_code'] = array('not in',  $code_list);
		$user_where['rank'] = 0;
		$tar = User::where($user_where)->field('store_code')->select();

		if(is_array($tar)&&empty($tar))
		{
			return true;
		}

		$user_list = array_column($tar, 'store_code');



		foreach ($user_list as $key => $value) {
			// 计算上架  下架 库存 预警
			self::currentLy($value, $later);
		}
		

		return true;


	}



}

?>