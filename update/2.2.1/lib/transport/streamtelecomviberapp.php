<?php
/**
 * Bitrix Framework
 * @package    Bitrix
 * @subpackage mlife.smsservices
 * @copyright  2015 Zahalski Andrew
 */

namespace Mlife\Smsservices\Transport;

class Streamtelecomviberapp{
	
	private $config;
	public $session = false;
	
	//конструктор, получаем данные доступа к шлюзу
	function __construct($params) {
		$this->config = $this->getConfig($params);
	}
	
	public function _getAllSender() {
		
		$url = 'https://gateway.api.sc/xml//originator.php';
		
		$xml = '<?xml version="1.0" encoding="utf-8" ?>
		<request>
		<security>
			<login value="'.$this->config->login.'" />
			<password value="'.$this->config->passw.'" />
		</security>
		</request>';
		
		$response = $this->openHttp($url, true, $xml, true);
		//print_r($response);die();
		$data = new \stdClass();
		
		if(!$response){
			$data = new \stdClass();
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$error = $this->checkError($response);
		if($error) {
			$data->error = $error;
			$data->error_code = $this->checkErrorFromText($response,true);
			return $data;
		}
		
		$count_resp = preg_match_all('/<originator state="[A-z]+">(.*)<\/originator>/Ui',$response, $matches);
		
		if($count_resp>0 && is_array($matches[1])) {
			foreach($matches[1] as $sender) {
				$ob = new \stdClass();
				$ob->sender = $sender;
				$arr[] = $ob;
			}
			$data = $arr;
		}
		else{
			$data->error = 'Error';
			$data->error_code = '9999';
			return $data;
		}
		
		return $data;
	}
	
	public function _getBalance () {
		
		$url = 'https://gateway.api.sc/get/?user='.$this->config->login.'&pwd='.$this->config->passw.'&balance=1';
		$response = $this->openHttp($url);
		
		$data = new \stdClass();
		
		if(!$response){
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$error = $this->checkErrorFromText($response);
		
		if($error){
			$data->error_code = $error;
			$data->error = $response;
			return $data;
		}
		
		//$response = '100.01 RUR';
		$response_ = explode(" ",$response);
		
		if(count($response_)>0 && strlen($response)<30){
			$data->balance = preg_replace("/([^0-9,.])/is",'',$response_[0]);
			$data->balance = str_replace(',','.',$data->balance);
		}
		else{
			$data->error_code = '9999';
			$data->error = 'Error code 9999';
		}
		
		return $data;
		
	}
	
	public function _sendSms ($phones, $mess, $time=0, $sender=false) {
		
		$data = new \stdClass();
		
		$charset = $this->config->charset;

		if($charset=='windows-1251') {
			$mess = $GLOBALS['APPLICATION']->ConvertCharset($mess, $charset, 'UTF-8');
		}
		
		if(!$sender) {
			$sender = $this->config->sender;
		}
		
		$params = array(
			'login'=>$this->config->login,
			'pass'=>$this->config->passw,
			'sourceAddressIM'=>$sender,
			'textIM'=>$mess,
			'phone'=>$phones,
			'validityPeriod'=>intval(\Bitrix\Main\Config\Option::get("mlife.smsservices","limittimesms",600,"")),
		);
		
		$url = 'https://gateway.api.sc/rest/Send/SendIM/ViberOne/';
		
		$httpClient = new \Bitrix\Main\Web\HttpClient();
		$httpClient->disableSslVerification();
		$response = $httpClient->post($url,$params);

		if(!$response){
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		//echo'<pre>';print_r($response);echo'</pre>';
		if(strpos($response,'{')!==false){
			try{
				$error = \Bitrix\Main\Web\Json::decode($response);
				//echo'<pre>';print_r($error);echo'</pre>';die();
				if($error['Desc']){
					$data->error = 'Error: '.$error['Desc'];
					$data->error_code = $this->chechErrorCode($error['Code']);
				}else{
					$data->error = 'Unknown';
					$data->error_code = '9998';
				}
			}
			catch(\Exception $ex){
				$data->error = 'Unknown';
				$data->error_code = '9998';
			}
			return $data;
		}elseif($id = preg_replace('/([^0-9])/is','',$response)){
			$data->id = $id;
		}else{
			$data->error = 'Unknown';
			$data->error_code = '9998';
			return $data;
		}
		
		return $data;
	}
	
	public function _getStatusSms($smsid,$phone=false) {
		
		$data = new \stdClass();
		
		$url = 'https://gateway.api.sc/rest/State/Viber/';
		
		$params = array(
			'login'=>$this->config->login,
			'pass'=>$this->config->passw,
			'messageId'=>$smsid
		);
		
		$httpClient = new \Bitrix\Main\Web\HttpClient();
		$httpClient->disableSslVerification();
		$response = $httpClient->post($url,$params);

		if(!$response){
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		try{
			$state = \Bitrix\Main\Web\Json::decode($response);
			
			//echo'<pre>';print_r($state);echo'</pre>';die();
			
			$data->resp = $state;
			$data->status = 12;
			
			if(isset($state[$smsid]['viber']) && !empty($state[$smsid]['viber'])){
				if(isset($state[$smsid]['viber']['state'])){
					$data->status = $this->_checkStatus($state[$smsid]['viber']['state'], $state[$smsid]['viber']['state_error']);
					if(isset($state[$smsid]['viber']['state_time'])){
						$data->last_timestamp = strtotime($state[$smsid]['viber']['state_time']);
					}
				}
			}
			
		}
		catch(\Exception $ex){
			$data->status = 12;
		}
		
		
		//if(!$data->last_timestamp) {
			$data->last_timestamp = time();
		//}
			
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
	
	private function openHttp($url, $method_ = false, $params = null, $xml=false) {
		
		if($method_ === false) {
			$httpClient = new \Bitrix\Main\Web\HttpClient();
			$result = $httpClient->get($url);
		}else{
			$httpClient = new \Bitrix\Main\Web\HttpClient(array('charset'=>'utf-8'));
			if(!$xml) {
				$httpClient->setHeader('Content-Type', 'application/x-www-form-urlencoded; charset=utf-8', true);
			}else{
				$httpClient->setHeader('Content-Type', 'text/xml; charset=utf-8', true);
			}
			$result = $httpClient->post($url,$params);
		}
		
		return $result;
		
	}
	
	private function chechErrorCode($code){
		if($code == 1) return 1;
		if($code == 2) return 1;
		if($code == 3) return 1;
		if($code == 4) return 2;
		if($code == 5) return 10;
		if($code == 6) return 1;
		if($code == 7) return 9999;
		if($code == 8) return 9999;
		if($code == 9) return 9999;
		if($code == 'user-blocked') return 8;
		if($code == 'not-viber-user') return 8;
		if($code == 'filtered') return 6;
		return 9999;
	}
	
	private function checkError($resp) {
		$count = preg_match_all('/<error>(.*)<\/error>/Ui',$resp, $matches);
		if($count>0) {
			if($this->config->charset=='windows-1251') {
				$error = $GLOBALS['APPLICATION']->ConvertCharset($matches[1][0], 'UTF-8', SITE_CHARSET);
			}else{
				$error = $matches[1][0];
			}
			return $error;
		}
		return false;
	}
	
	private function checkErrorFromText($text='',$err=false){
		if(strpos($text,'Неправильный логин или пароль')!==false) return 2;
		if(strpos($text,'Ваш аккаунт заблокирован')!==false) return 4;
		if(strpos($text,'Данное направление закрыто для вас')!==false) return 8;
		if(strpos($text,'Нет отправителя')!==false) return 1;
		if(strpos($text,'Нет текста сообщения')!==false) return 1;
		if(strpos($text,'Такого отправителя нет')!==false) return 1;
		if(strpos($text,'Укажите номер телефона')!==false) return 7;
		if(strpos($text,'Flood SMS')!==false) return 9;
		
		if(strpos($text,'Error_Destination_Address_Blocked')!==false) return 8;
		if(strpos($text,'Error_Invalid_Login')!==false) return 2;
		if(strpos($text,'Error_Invalid_POST_Data')!==false) return 1;
		if(strpos($text,'Error_Invalid_Source_Address')!==false) return 1;
		if(strpos($text,'Error_Invalid_XML')!==false) return 1;
		if(strpos($text,'Error_No_Destination_Address')!==false) return 1;
		if(strpos($text,'Error_No_Source_Address')!==false) return 1;
		if(strpos($text,'Error_No_Text')!==false) return 1;
		if(strpos($text,'Error_No_URL')!==false) return 1;
		if(strpos($text,'Error_No_Vcard')!==false) return 1;
		if(strpos($text,'Error_Not_Enough_Credits')!==false) return 3;
		if(strpos($text,'Error_Out_of_Service')!==false) return 9999;
		if(strpos($text,'Error_SMS_Declined')!==false) return 6;
		if(strpos($text,'Error_SMS_User_Disabled')!==false) return 4;
		if(strpos($text,'Error_Source_Address_Declined')!==false) return 8;
		if(strpos($text,'Error_User_Destination_Blocked')!==false) return 8;
		if(strpos($text,'Error_Flood_SMS')!==false) return 9;
		
		if($err) return 9999;
		return false;
	}
	
	private function _checkStatus($code, $error=false) {
		
		if($error){
			if($code == 'user-blocked') return 9;
			if($code == 'not-viber-user') return 11;
			if($code == 'filtered') return 9;
		}
		
		if($code=='sent') return 3;
		if($code=='undelivered') return 7;
		if($code=='delivered') return 4;
		if($code=='read') return 14;
		return false;
		
	}
	
}
?>