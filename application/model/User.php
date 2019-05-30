<?php
namespace app\model;

use think\Model;
use think\Db;

class User extends Model
{
	protected $pk = 'uid';
	protected $table="pos_user";

	/**
	 * 获取所有用户
	 * @return [type] [description]
	 */
	public function getUsers()
	{
		$list = Db::name('user')->where('id','>',0)->select();
		return $list;
	}
}

?>