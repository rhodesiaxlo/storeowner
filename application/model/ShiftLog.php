<?php
namespace app\model;

use think\Model;
use think\Db;

class ShiftLog extends Model
{
	protected $pk = 'uid';
	protected $table="pos_shift_log";

	/**
	 * 获取交接班记录，按日期倒序排列
	 * @param  [type] $code [description]
	 * @return [type]       [description]
	 */
	public static function getShiftLogByStoreCode($code, $record_no, $page_no)
	{
		$list = db('shift_log')->alias("log")->join("pos_user u", "log.uid=u.local_id")->field("log.*,u.realname")->limit(($page_no-1)*$record_no, $record_no)->select();
		return $list;
	}

}

?>