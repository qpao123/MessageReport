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

		$data = $this->processData($data, 'sms_info');
		return $this->curlPost($data);
	}

	private function order($data)
	{
		$rules = [
				'order_no','user_id','status','appid','app_name','app_package',
				'offer_name','offer_package','base_push','exception_type','push_time'
			];
		if (!$data = $this->validate($data, $rules)) {
			return ['code' => '-1', 'msg' => '参数不正确！'];
		}

		$data = $this->processData($data, 'order_info');
		return $this->curlPost($data);
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
	    
	    if (!$res) {
	    	return ['code' => '-1', 'msg' => curl_error($curl)];
	    }
	    //关闭URL请求
	    curl_close($curl);

	    return json_decode($res, true);
	}


}

