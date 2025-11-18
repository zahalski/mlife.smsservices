<?php
namespace Mlife\Smsservices\Transport;

class Prostosms{
	
	private $config;
	
	function __construct($params) {
		$this->config = $this->getConfig($params);
	}
	
	public function _getAllSender() {
		
		$url = 'http://api.sms-prosto.ru/?method=get_profile&format=json&email='.$this->config->login.'&password='.$this->config->passw;
		$response = $this->openHttp($url);
		
		$data = new \stdClass();
		
		if(!$response){
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$response = json_decode($response, true);
		
		if(is_countable($response) && count($response)>0){
			foreach ($response as $key=>$value) {
				if($key==0 && $value!='100'){
					$data->error_code = $this->chechErrorCode($value);
					$data->error = 'Error code '.$value;
				}
				else if($key>0 && $value){
					$ob = new \stdClass();
					$ob->sender = $value["data"]["sender_name"];
					$arr[] = $ob;
				}
			}
		}
		else{
			$data->error_code = '9999';
			$data->error = 'Error code 9999';
		}
		
		if(!$data->error) $data = $arr;
		
		return $data;
	}
	
	public function _sendSms ($phones, $mess, $time=0, $sender=false) {		
		$timeold = $time;
		if($time!=0 && $time>time()) {
			$time = '0'.$time;
		}
		else{
			$time = 0;
		}
		$phones = urlencode($phones);
		$charset = $this->config->charset;
		
		if($charset=='windows-1251') {
		$charset = 'cp1251';
			if($time!=0 && strtotime($timeold)>time()) {
				$mess = iconv("CP1251", "UTF-8", $mess);
			}
		}
		$mess = urlencode($mess);
		if(!$sender) {
			$sender = $this->config->sender;
		}
		$url =  'http://api.sms-prosto.ru/?method=push_msg&format=json&email='.$this->config->login.'&password='.$this->config->passw.'&text='.$mess.'&phone='.$phones.'&sender_name='.$sender;
		
		$response = $this->openHttp($url);
		
		$data = new \stdClass();
		$response = json_decode($response, true);

		if(!$response){
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}

		if($response["response"]["msg"]["type"] !== "error"){
			$data->id = $response["response"]["data"]["id"];
			$data->cost = $response["response"]["data"]["credits"];
			$data->cnt = $response["response"]["data"]["n_raw_sms"];
		} else {
			$data->error_code = $this->chechErrorCode($response["response"]["msg"]["err_code"]);
			$data->error = 'Код ошибки '.$response["response"]["msg"]["err_code"].' - '.$response["response"]["msg"]["text"];
		}
		return $data;
	}
	
	public function _getBalance () {
		$url = 'http://api.sms-prosto.ru/?method=get_profile&format=json&email='.$this->config->login.'&password='.$this->config->passw;
		$response = $this->openHttp($url);
		
		$data = new \stdClass();
		
		if(!$response){
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$response = json_decode($response, true);
		if(is_countable($response) && count($response)>0){
			foreach ($response as $key=>$value) {
				if($key==0 && $value!='100'){
					$data->error_code = $this->chechErrorCode($value);
					$data->error = 'Error code '.$value;
				}
				else if($key>0 && $value){
					$data->balance = $value["data"]["credits"];
				}
			}
		}
		else{
			$data->error_code = '9999';
			$data->error = 'Error code 9999';
		}
		return $data;
	}
	
	public function _getStatusSms($smsid,$phone=false) {
	
		$url = 'http://api.sms-prosto.ru/?method=get_msg_report&format=json&email='.$this->config->login.'&password='.$this->config->passw.'&id='.$smsid;
		$response = $this->openHttp($url);
		
		$data = new \stdClass();
		$response = json_decode($response, true);
		if(!$response){
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		if($response["response"]["msg"]["type"] !== "error"){
			$data->status = $this->_checkStatus($response["response"]["data"]["state"]);
			$data->last_timestamp = time();
			
		}
		else {
			$data->error_code = $this->chechErrorCode($response["response"]["msg"]["err_code"]);
			$data->error = 'Код ошибки '.$response["response"]["msg"]["err_code"].' - '.$response["response"]["msg"]["text"];
		}
			
		return $data;
		
	}
	
	private function getConfig($params) {
		
		$c = new \stdClass();
		$c->login = $params['login'];
		$c->passw = $params['passw'];
		$c->sender = $params['sender'];
		$c->charset = $params['charset'];
		return $c;
	}
	
	private function openHttp($url, $method = false, $params = null) {
		if (!function_exists('curl_init')) {
		    die('ERROR: CURL library not found!');
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, $method);
		if ($method == true && isset($params)) {
		    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		}
		curl_setopt($ch,  CURLOPT_HTTPHEADER, array(
		    'Content-Length: '.strlen($params),
		    'Cache-Control: no-store, no-cache, must-revalidate',
		    "Expires: " . date("r")
		));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}
	
	private function chechErrorCode($code) {
		/* Коды ошибок, которые возвращает параметр err_code. Параметр err_code возвращает ошибку, в случае, если запрос на получения статусов по SMS не прошел (например, если API на аккаунте отключено.)
		3		API на Вашем аккаунте отключено. Обратитесь в поддержку.
		7		Заданы не все необходимые параметры
		602		SMS не существует.
		605		Пользователь заблокирован
		625		API KEY не указан.
		626		Не удалось получить данные по API KEY.
		627		Не верный API KEY.
		699		Не удалось установить соединение.
		*/
		if($code==3) return 12;
		if($code==7) return 12;
		if($code==602) return 12;
		if($code==617) return 7;
		if($code==605) return 12;
		if($code==625) return 12;
		if($code==626) return 12;
		if($code==627) return 12;
		if($code==699) return 12;
		
		return 9999;
	}
	
	private function _checkStatus($code) {
		/*
		Коды статусов, которые возвращает параметр state (статусы SMS)
		 0 - Отправлено //Важно! Этот статус будет конечным, если он не поменялся за 25 часов с момента отправки СМС.
		 1 - Доставлено
		 2 - Не доставлено
		16 - Не доставлено в SMSC
		34 - Не доставлено (просрочено)

		*/	
		if($code==0) return 3;
		if($code==1) return 4;
		if($code==2) return 7;
		if($code==16) return 5;
		if($code==34) return 5;
	}
}
?>