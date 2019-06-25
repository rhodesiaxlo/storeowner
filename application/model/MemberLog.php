<?php
namespace app\model;

use think\Model;
use think\Db;

class MemberLog extends Model
{
	protected $pk = 'uid';
	protected $table="pos_member_log";
	protected $createTime = false;

	/**
	 * 会员统计
	 * @return [type] [description]
	 */
	public static function getDynamic()
	{
		return [12, 6];
	}
}

?>