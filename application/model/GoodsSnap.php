<?php
namespace app\model;

use think\Model;
use think\Db;

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
		return [1,2,3,1];
	}



}

?>