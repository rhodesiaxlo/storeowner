<?php
namespace app\model;

use think\Model;
use think\Db;

class Goods extends Model
{
	protected $pk = 'uid';
	protected $table="pos_goods";

	public static function getAllGoodsByStorecode($code, $record_no, $page_no, $type, $barcode, $goods_name, $type2, $is_forsale)
	{
		$where['store_code'] = $code;
		$where['deleted'] = 0;

		if(!is_null($type))
		{
			if(intval($type)!==false && intval($type)>0)
			{
				$where['cat_id'] = $type;	
			}
			
		}

		$where1 = [];
		$where2 = [];
		// 区分是 barcode 还是 name
		if(!empty($barcode))
		{
			$where['goods_sn'] = array("like", "%".$barcode."%");;
		}

		if(!empty($goods_name))
		{
			$where['goods_name'] = array("like", "%".$goods_name."%");
		}

		if(is_numeric($is_forsale))
		{
			$where['is_forsale'] = $is_forsale;
		}

		if(is_numeric($type2))
		{
			$where['type'] = $type2;
		}

		$where['deleted'] = 0;

		$list = self::where($where)->limit(($page_no-1)*$record_no, $record_no)->select();
		$total_number = self::where($where)->count();
		return [$list, $total_number];
	}

	public static function getInven($store_code, $page_no, $record_no, $cat_id, $goods_name, $goods_sn, $is_forsale)
	{
		$where = [];
		$where['store_code'] = $store_code;

		if(!is_null($cat_id)&&intval($cat_id)!=1000)
		{
			if(intval($cat_id)!==false&&intval($cat_id)>0)
			{
				$where['cat_id'] = $cat_id;
			}
		}

		if(!empty($goods_name))
		{
			$where['goods_name'] = array("like", "%".$goods_name."%");
		}

		if(!empty($goods_sn))
		{
			$where['goods_sn'] = array("like", "%".$goods_sn."%");;
		}

		if(!is_null($is_forsale)&&intval($is_forsale)!=1000)
		{
			$where['is_forsale'] = $is_forsale;;
		}


		$list =   self::where($where)->limit(($page_no-1)*$record_no, $record_no)->select();
		$number = self::where($where)->count();
		return [$list, $number];
	}


	/**
	 * 按件数排序
	 * @return [type] [description]
	 */
	public static function consumeRankByNum()
	{
		// todo
	}

	/**
	 * 按消费金额排序
	 * @return [type] [description]
	 */
	public static function consumeRankByVolumn()
	{
		// todo 
	}

	/**
	 * 消费统计数据
	 * @return [type] [description]
	 */
	public static function consumeDate()
	{
		// 
	}

	/**
	 * 上架商品种数
	 * @param  [type] $start_date [description]
	 * @return [type]             [description]
	 */
	public static function onShelf($start_date)
	{

	}

	/**
	 * 下架商品种数
	 * @return [type] [description]
	 */
	public static function offShelf()
	{

	}

	public static function inventory()
	{

	}

	/**
	 * 预警中暑
	 * @return [type] [description]
	 */
	public static function invenWarning()
	{

	}



}

?>