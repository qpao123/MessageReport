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
		$type = $this->camelize($type);
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

	private function actionInfo($data)
	{
		$rules = ['app_package', 'action_type'];
		
		if (isset($data['user_id'])) {
			$rules = array_merge($rules, ['user_id']);
		}

		if (isset($data['product_id'])) {
			$rules = array_merge($rules, ['product_id']);
		}

		if (isset($data['device_id'])) {
			$rules = array_merge($rules, ['device_id']);
		}

		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}
			
		return $this->do($data, 'action_info');
	}

	private function deviceInfo($data)
	{
		$rules = ['app_package', 'device_id'];
		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}

		//添加设备，触发安装事件
		$res = $this->actionInfo([
			'app_package' => $data['app_package'], 
			'device_id'   => $data['device_id'], 
			'action_type' => 'app_install'
		]);
		if ($res['code'] != 0) {
			return $res;
		}
		
		return $this->do($data, 'device_info');	
	}

	private function userInfo($data)
	{
		$rules = ['app_package', 'device_id', 'user_id'];
		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}
		
		//添加用户，触发注册事件
		$res = $this->actionInfo([
			'app_package' => $data['app_package'], 
			'user_id'     => $data['user_id'], 
			'device_id'   => $data['device_id'], 
			'action_type' => 'user_register'
		]);
		if ($res['code'] != 0) {
			return $res;
		}

		return $this->do($data, 'user_info');	
	}

	private function productInfo($data)
	{
		$rules = ['app_package', 'device_id', 'user_id', 'product_id'];
		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}

		//添加产品，触发offer事件
		$res = $this->actionInfo([
			'app_package' => $data['app_package'], 
			'product_id'  => $data['product_id'],
			'device_id'   => $data['device_id'], 
			'user_id'     => $data['user_id'], 
			'action_type' => 'offer_down'
		]);
		if ($res['code'] != 0) {
			return $res;
		}		
		
		return $this->do($data, 'product_info');	
	}

	private function activeInfo($data)
	{
		$rules = ['user_id', 'active_name', 'app_package', 'device_id', 'product_id'];
		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}
		
		return $this->do($data, 'active_info');	
	}

	private function orderInfo($data)
	{
		$rules = [
				'order_no', 'user_id', 'status', 'app_package', 'product_id', 
				'device_id', 'base_push', 'exception_type'
			];

		if (isset($data['push_time'])) {
			$rules = array_merge($rules, ['push_time']);
		}	

		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}

		$action_type = '';
		if ($data['status'] == 80 and $data['base_push'] == 1) {
			$action_type = 'api_push'; //进件
		} else if ($data['status'] == 90) {
			$action_type = 'api_apply'; //申请
		} else if ($data['status'] == 170) {
			$action_type = 'api_loan'; //放款
		} else if ($data['status'] == 200) {
			$action_type = 'api_success'; //结清	
		}

		if ($action_type) {
			$res = $this->actionInfo([
				'app_package' => $data['app_package'], 
				'product_id'  => $data['product_id'],
				'user_id'     => $data['user_id'], 
				'device_id'   => $data['device_id'],
				'action_type' => $action_type,
			]);
			if ($res['code'] != 0) {
				return $res;
			}
		}

		return $this->do($data, 'order_info');
	}

	private function userActive($data)
	{
		$rules = ['user_id','device_id','app_package','app_type','page_id'];
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
		foreach ($rules as $item) {
			if (!isset($data[$item])) {
				return false;			
			}
		}

		return $data;
	}

	private function processData($data, $type)
	{
		$data['cdate'] = date('Y-m-d');
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

	private function camelize($uncamelized_words,$separator='_')
    {
        $uncamelized_words = $separator. str_replace($separator, " ", strtolower($uncamelized_words));
        
        return ltrim(str_replace(" ", "", ucwords($uncamelized_words)), $separator );
    }

}

