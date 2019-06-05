<?php
namespace app\model;

use think\Model;
use think\Db;

class  Category extends Model
{
	protected $pk = 'uid';
	protected $table="pos_category";

	/**
	 * 获取所有用户
	 * @return [type] [description]
	 */
	public static function getAllCategories()
	{
		$list = self::where(1)->select();
		return $list;
	}
}

?>