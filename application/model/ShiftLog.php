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
	public static function getShiftLogByStoreCode($code, $record_no, $page_no, $start_date, $end_date, $staff_id=null)
	{
		$where = [];
		$where['log.store_code'] = $code;
		if(!empty($start_date))
		{
			$where['log.start_time'] = array("gt", strtotime($start_date)*1000);
		}

		if(!empty($end_date))
		{
			$where['log.end_time'] = array("lt", strtotime($end_date)*1000);
		}

		if(!empty($staff_id))
		{
			$where['log.uid'] = $staff_id;

		}


		$list = db('shift_log')->alias("log")
							   ->join("pos_user u", "log.uid=u.id and log.store_code = u.store_code")
							   ->field("log.*,u.realname")
							   ->where($where)
							   ->limit(($page_no-1)*$record_no, $record_no)
							   ->select();
		$total_number = db('shift_log')->alias("log")
							   ->join("pos_user u", "log.uid=u.id and log.store_code = u.store_code")
							   ->field("log.*,u.realname")
							   ->where($where)
							   ->count();
		return [$list, $total_number];
	}

}

?>