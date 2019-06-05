<?php
namespace app\model;

use think\Model;
use think\Db;

class Goods extends Model
{
	protected $pk = 'uid';
	protected $table="pos_goods";

	public static function getAllGoodsByStorecode($code, $record_no, $page_no)
	{
		$list = self::where(['store_code'=>$code, 'deleted'=>0])->limit(($page_no-1)*$record_no, $record_no)->select();
		return $list;
	}

}

?>