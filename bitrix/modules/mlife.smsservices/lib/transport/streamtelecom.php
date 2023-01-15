<?php
/**
 * Bitrix Framework
 * @package    Bitrix
 * @subpackage mlife.smsservices
 * @copyright  2015 Zahalski Andrew
 */

namespace Mlife\Smsservices\Transport;

class Streamtelecom{

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

		$phones = preg_replace("/[^0-9A-Za-z]/", "", $phones);
		$charset = $this->config->charset;
		if($charset=='windows-1251') {
			$mess = $GLOBALS['APPLICATION']->ConvertCharset($mess, SITE_CHARSET, 'UTF-8');
		}
		if(!$sender) {
			$sender = $this->config->sender;
		}
		
		$xml = '<?xml version="1.0" encoding="utf-8" ?>
		<request>
		<security>
			<login value="'.$this->config->login.'" />
			<password value="'.$this->config->passw.'" />
		</security>
		<message type="sms">
			<sender>'.$sender.'</sender>
			<text>'.$mess.'</text>
			<abonent phone="'.$phones.'"/>
		</message>
		</request>';
		
		$url = 'https://gateway.api.sc/xml/';
		
		$response = $this->openHttp($url, true, $xml, true);
		
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
		
		$count_resp_err = preg_match_all('/<information number_sms="">(.*)<\/information>/Ui',$response, $matches_err);
		if($count_resp_err>0) {
			$err = $GLOBALS['APPLICATION']->ConvertCharset($matches_err[1][0], 'UTF-8', SITE_CHARSET);
			$data->error = $err;
			$data->error_code = $this->checkErrorFromText($matches_err[1][0]);
			return $data;
		}
		
		$count_resp = preg_match_all('/<information.*id_sms="(.*)".*parts="(.*)">(.*)<\/information>/Ui',$response, $matches);
		
		if($count_resp>0){
			$data->id = $matches[1][0];
			$data->cnt = $matches[2][0];
			$data->cost = '';
			$data->balance = '';
			return $data;
		}
		else{
			$data->error = $error;
			$data->error_code = $this->checkErrorFromText($error,true);
			return $data;
		}
		
	}
	
	public function _getStatusSms($smsid,$phone=false) {
		
		$xml = '<?xml version="1.0" encoding="utf-8" ?>
		<request>
		<security>
			<login value="'.$this->config->login.'" />
			<password value="'.$this->config->passw.'" />
		</security>
		<get_state>
			<id_sms>'.$smsid.'</id_sms>
		</get_state>
		</request>';
		
		$url = 'https://gateway.api.sc/xml/state.php';
		
		$response = $this->openHttp($url, true, $xml, true);

		$data = new \stdClass();
		
		if(!$response){
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$error = $this->checkError($response);
		
		if($error) {
			$data->error = $error;
			$data->error_code = $this->checkErrorFromText($error,true);
			return $data;
		}
		
		$count_resp = preg_match_all('/<state.*time="(.*)".*>(.*)<\/state>/Ui',$response, $matches);
		
		if($count_resp>0){
			if($this->_checkStatus($matches[2][0])){
			$data->last_timestamp = strtotime($matches[1][0]);
			$data->status = $this->_checkStatus($matches[2][0]);
			return $data;
			}
		}
			$data->error = 'Service is not available';
			$data->error_code = '9998';
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
	
	private function _checkStatus($code) {
	
		if($code=='send') return 3;
		if($code=='not_deliver') return 7;
		if($code=='expired') return 5;
		if($code=='deliver') return 4;
		if($code=='partly_deliver') return false;
		return false;
		
	}
	
}