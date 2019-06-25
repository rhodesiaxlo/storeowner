<?php
namespace app\model;

use think\Model;
use think\Db;

class User extends Model
{
	protected $pk = 'local_id';
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

	/**
	 * rank = 0 店主
	 * @param  [type] $name     [description]
	 * @param  [type] $password [description]
	 * @return [type]           [description]
	 */
	public static function login($name, $password)
	{

		$is_only = self::where(['uname'=>$name, 'password'=>$password])->select();
		if(is_array($is_only)&&sizeof($is_only)>1)
		{
			// 多个用户名重复
			return 1000;
		}
		$is_exist = self::where(['uname'=>$name, 'password'=>$password ])->find();
		if($is_exist!==false&&$is_exist!=null&&empty($is_exist->token))
		{
			self::where(['uname'=>$name, 'password'=>$password, 'rank'=>0])->update(['token'=>$is_exist->store_code]);
		}
		$is_exist = self::where(['uname'=>$name, 'password'=>$password, 'rank'=>0])->find();
		return $is_exist;
	}



	/**
	 * 检查是否存在这个 token 
	 * @param  [type] $token [description]
	 * @return [type]        [description]
	 */
	public static function checkToken($token)
	{
		$is_exist = self::where(['token' => $token])->find();

		if($is_exist === null)
		{
			// 不存在
			return -1002;
		}

		if($is_exist === false)
		{
			// 查询出错
			return -1003;
		}

		if($is_exist->is_active == 0)
		{
			// 被冻结
			return -1004;
		}

		return 1;

	}

	/**
	 * 退出 token 置空
	 * @param  [type] $token [description]
	 * @return [type]        [description]
	 */
	public static function logout($token)
	{
		$ret = self::where(['token'=>$token])->update(['token'=>'']);
		return $ret;
	}

	/**
	 * 获取店面信息
	 * @param  [type] $token [description]
	 * @return [type]        [description]
	 */
	public static function getStoreByToken($token)
	{
		$info = self::where(['token'=>$token,'rank'=>0])->find();

		// 库存总数
		// 库存不足数
		// 商品数量
		$goods_num = db('goods')
		   ->where(['store_code'=>$info->store_code])
		   ->count();

		$repertory_sum = db('goods')
		   ->where(['store_code'=>$info->store_code])
		   ->sum('repertory');

		$sql="select count(*) as num from pos_goods where store_code='{$info->store_code}' and repertory<repertory_caution";
		$repertory_warning = self::query($sql);

		$info['goods_num'] = $goods_num;
		$info['repertory_sum'] = $repertory_sum;
		$info['repertory_warning'] = $repertory_warning[0]['num'];


		return $info;
	}

	/**
	 * 根据 token 获取所有电源信息
	 * @param  [type] $token [description]
	 * @return [type]        [description]
	 */
	public static function getStaffByStoken($token, $record_no, $page_no, $name, $status)
	{
		$info = self::where(['token'=>$token])->find();
		if($info == null || $info === false)
		{
			return false;
		}

		$where = [];
		$where['store_code'] = $info->store_code;
		if(!empty($name))
		{
			$where['uname'] = $name;
		}

		if(!empty($status))
		{
			if(intval($status)!==false)
			{
				$where['is_active'] = $status;
			}	
		}

		$where['deleted'] = 0;

		$list = self::where($where)->limit(($page_no-1)*$record_no, $record_no)->select();
		$total_number = self::where($where)->count();

		return [$list, $total_number];
	}

	public static function getStoreCodeList()
	{
		return self::where(['rank'=>0])->field('store_code')->select();
	}



}

?>