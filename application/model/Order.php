<?php
namespace app\model;

use think\Model;
use think\Db;

class Order extends Model
{
	protected $pk = 'uid';
	protected $table="pos_order";

	public static function getAllOrdersByStorecode($code, $record_no, $page_no)
	{
		$list = self::where(['store_code'=>$code, 'deleted'=>0])->limit(($page_no-1)*$record_no, $record_no)->select();
		return $list;
	}

}

?>