<?php 

namespace MessageReport;

class Report {

	private $url;

	public function __construct($url) 
	{
		$this->url = $url;
	}

	public function send($type, $data)
	{
		if (!method_exists($this, $type)) {
			return ['code' => '-1', 'msg' => '请求错误！'];
		}

		return $this->$type($data);	
	}

	private function sms($data)
	{
		$rules = ['guid', 'app_name', 'mobile', 'action', 'code', 'status', 'ctime'];
		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}

		return $this->do($data, 'sms_info');
	}

	private function action($data)
	{
		$rules = ['appid', 'type'];
		
		if (isset($data['user_id'])) {
			$rules = array_merge($rules, ['user_id']);
		}

		if (isset($data['guid'])) {
			$rules = array_merge($rules, ['guid']);
		}

		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}
			
		return $this->do($data, 'action_info');
	}

	private function device($data)
	{
		$rules = ['appid', 'guid', 'channel', 'campaign'];
		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}

		//添加设备，触发安装事件
		$this->action([
			'appid' => $data['appid'], 
			'guid'  => $data['guid'], 
			'type'  => 1
		]);
		
		return $this->do($data, 'device_info');	
	}

	private function user($data)
	{
		$rules = ['appid', 'guid', 'user_id', 'mobile', 'user_name'];
		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}
		
		//添加用户，触发注册事件
		$this->action([
			'appid'   => $data['appid'], 
			'user_id' => $data['user_id'], 
			'guid'    => $data['guid'], 
			'type'    => 2
		]);

		return $this->do($data, 'user_info');	
	}

	private function product($data)
	{
		$rules = ['appid', 'guid', 'user_id', 'pid'];
		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}

		//添加产品，触发offer事件
		$this->action([
			'appid'   => $data['appid'], 
			'user_id' => $data['user_id'], 
			'guid'    => $data['guid'], 
			'type'    => 3
		]);		
		
		return $this->do($data, 'product_info');	
	}

	private function active($data)
	{
		$rules = ['user_id', 'active_name', 'active_type'];
		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}
		
		return $this->do($data, 'active_info');	
	}

	private function order($data)
	{
		$rules = [
				'order_no', 'user_id', 'status', 'appid', 'pid', 
				'guid', 'base_push', 'exception_type'
			];

		if (isset($data['push_time'])) {
			$rules = array_merge($rules, ['push_time']);
		}	

		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}

		if ($data['status'] == 80 and $data['base_push'] == 1) {
			//添加订单，触发api进件
			$this->action([
				'appid'   => $data['appid'], 
				'user_id' => $data['user_id'], 
				'guid'    => $data['guid'],
				'type'    => 4
			]);
		}

		return $this->do($data, 'order_info');
	}

	private function userActive($data)
	{
		$rules = ['uid','guid','app_package','app_type','page_id'];
		if (!isset($data['appid']) && !isset($data['package_name'])) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}

		if (isset($data['appid'])) {
			$rules = array_merge($rules, ['appid']);
		}

		if (isset($data['package_name'])) {
			$rules = array_merge($rules, ['package_name']);
		}

		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}

		return $this->do($data, 'user_active');
	}

	private function validate($data, $rules)
	{
		$newData = [];
		foreach ($rules as $item) {
			if (!isset($data[$item])) {
				return false;			
			}
			$newData[$item] = trim($data[$item]);
		}

		return $newData;
	}

	private function processData($data, $type)
	{
		$data = [
			'event_type' => $type,
			'event_data' => json_encode($data),
			'event_time' => time(),
		];

		return $data;
	}

	private function do($data, $type)
	{
		$data = $this->processData($data, $type);
		
		return $this->curlPost($data);
	}

	private function curlPost($data)
	{
	    $curl = curl_init();
	    curl_setopt($curl, CURLOPT_URL, $this->url . '/api/datareport/receive');
	    curl_setopt($curl, CURLOPT_HEADER, 0);
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($curl, CURLOPT_POST, 1);
	    //忽略证书
	    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	    //设置超时时间
   		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	    //设置post数据
	    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
	    //执行命令
	    $res = curl_exec($curl);

	    $res = json_decode($res, true);
	    if (!$res) {
	    	$msg = curl_error($curl) ? curl_error($curl) : 'execute error';
	    	return ['code' => '-1', 'msg' => $msg];
	    }
	    //关闭URL请求
	    curl_close($curl);

	    return $res;
	}


}

