<?php
namespace Mlife\Smsservices\Transport;

class Iqsms{
	
	private $config;
	
	function __construct($params) {
		$this->config = $this->getConfig($params);
	}
	
	public function _getAllSender() {
		$url = 'https://api.iqsms.ru/messages/v2/senders.json';
		$params = array(
			'login'=>$this->config->login,
			'password'=>$this->config->passw
		);
		$response = $this->openHttp($url, true, json_encode($params));
		//print_r($response);die();
		if(!$response){
			$data = new \stdClass();
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$response = json_decode($response);
		
		//print_r($response);die();
		
		if($response->status == 'error'){
			$data = new \stdClass();
			$data->error = $response->description;
			$data->error_code = $this->checkErrorCode($response->code);
			return $data;
		}
		
		$arr = array();
		foreach($response->senders as $sender){
			if($sender->status == 'active'){
				$ob = new \stdClass();
				$ob->sender = $sender->name;
				$arr[] = $ob;
			}
		}
		
		return $arr;
	}
	
	private function getConfig($params) {
		
		$c = new \stdClass();
		$c->login = $params['login'];
		$c->passw = $params['passw'];
		$c->sender = $params['sender'];
		$c->charset = $params['charset'];
		$c->debug = false;
		
		return $c;
		
	}
	
	public function _getBalance() {
		
		$url = 'https://api.iqsms.ru/messages/v2/balance.json';
		
		$params = array(
			'login'=>$this->config->login,
			'password'=>$this->config->passw
		);
		$response = $this->openHttp($url, true, json_encode($params));

		if(!$response){
			$data = new \stdClass();
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		//print_r($response);die();
		$response = json_decode($response);
		if($response->status == 'error'){
			$data = new \stdClass();
			$data->error = $response->description;
			$data->error_code = $this->checkErrorCode($response->code);
			return $data;
		}
		
		$data = new \stdClass();
		$data->balance = '';
		foreach($response->balance as $row){
			
			if($row->type == 'RUB'){
				if($data->balance) $data->balance = ', ';
				$data->balance .= $row->balance. ' RUB';
			}elseif($row->type == 'SMS'){
				//if($data->balance) $data->balance = ', ';
				//$data->balance .= $row->credit. ' SMS';
			}
		}
		
		return $data;
		
	}
	
	public function _sendSms ($phones, $mess, $time=0, $sender=false) {
	
		$data = new \stdClass();

		$phones = preg_replace("/[^0-9A-Za-z]/", "", $phones);
		$charset = $this->config->charset;
		if($charset=='windows-1251') {
			$mess = $GLOBALS['APPLICATION']->ConvertCharset($mess, SITE_CHARSET, 'UTF-8');
		}
		if(!$sender) {
			$sender = $this->config->sender;
		}
		//$mess = urlencode($mess);
		
		$smsId = time().'_'.rand(111,999);
		$url = 'https://api.iqsms.ru/messages/v2/send.json';
		
		$params = array(
			'login'=>$this->config->login,
			'password'=>$this->config->passw,
			'messages'=>array(
				array(
				'sender'=>$sender,
				'text'=>$mess,
				'clientId'=>$smsId,
				'phone'=>$phones
				)
			)
		);
		
		$response = $this->openHttp($url, true, json_encode($params));
		
		if(!$response){
			$data = new \stdClass();
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$response = json_decode($response);
		
		if($response->status == 'error'){
			$data = new \stdClass();
			$data->error = $response->description;
			if(!$response->code) $response->code = $response->description;
			$data->error_code = $this->checkErrorCode($response->code);
			return $data;
		}
		
		foreach($response->messages as $row){
			if($row->status == 'accepted'){
				$data = new \stdClass();
				$data->id = $row->smscId.'__'.$row->clientId;
			}else{
				$data = new \stdClass();
				$data->error = $row->status;
				$data->error_code = $this->checkErrorCode($row->status);
			}
			break;
		}
		
		return $data;
	
	}
	
	public function _getStatusSms($smsid,$phone=false) {
		
		$data = new \stdClass();
		
		$smsIdAr = explode('__',$smsid);
		
		$url = 'http://api.iqsms.ru/messages/v2/status.json';
		$params = array(
			'login'=>$this->config->login,
			'password'=>$this->config->passw,
			'messages'=>array(
				array(
					"smscId"=> $smsIdAr[0],
					"clientId"=> $smsIdAr[1]
				)
			)
		);
		$response = $this->openHttp($url, true, json_encode($params));
		
		if(!$response){
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$response = json_decode($response);
		//print_r($response);
		$data->status = 12;
		
		if($response->status == 'ok'){
			
			foreach($response->messages as $row){
				$data->status = $this->_checkStatus($row->status);
			}
			
		}
		
		$data->last_timestamp = time();
		
		//print_r($data);
		
		return $data;
		
	}
	
	/*
	* Приводим коды статусов в 1 вид
	1 - ожидает отправки с сайта
	2 - передано на шлюз
	3 - передано оператору
	4 - доставлено
	5 - просрочено
	6 - ожидает отправки на шлюзе
	7 - невозможно доставить
	8 - неверный номер
	9 - запрещено на сервисе
	10 - недостаточно средств
	11 - недоступный номер
	12 - ошибка при отправке смс
	*/
	private function _checkStatus($code=''){
		if($code == 'delivered') return 4;
		if($code == 'queued') return 2;
		if($code == 'delivery error') return 7;
		if($code == 'smsc submit') return 2;
		if($code == 'smsc reject') return 9;
		//if($code == 'incorrect id') return 12;
		return 12;
	}
	
	private function checkErrorCode($code=''){
		if($code == 1) return 2;
		if(strpos($code, 'error authorization')!==false) return 2;
		if(strpos($code, 'error params')!==false) return 1;
		if(strpos($code, 'invalid mobile phone')!==false) return 7;
		if(strpos($code, 'text is empty')!==false) return 1;
		if(strpos($code, 'sender address')!==false) return 6;
		if(strpos($code, 'credits')!==false) return 3;
		return 9998;
	}
	
	private function openHttp($url, $method_ = false, $params = null) {
		
		if($method_ === false) {
			$httpClient = new \Bitrix\Main\Web\HttpClient();
			$result = $httpClient->get($url);
		}else{
			$httpClient = new \Bitrix\Main\Web\HttpClient(array('charset'=>'utf-8'));
			$httpClient->setHeader('Content-Type', 'application/x-www-form-urlencoded; charset=utf-8', true);
			$result = $httpClient->post($url,$params);
		}
		
		return $result;
		
	}
	
}