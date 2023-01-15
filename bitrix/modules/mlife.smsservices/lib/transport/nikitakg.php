<?php
/**
 * Bitrix Framework
 * @package    Bitrix
 * @subpackage mlife.smsservices
 * @copyright  2015 Zahalski Andrew
 */

namespace Mlife\Smsservices\Transport;

class Nikitakg{
	
	private $config;
	
	//конструктор, получаем данные доступа к шлюзу
	function __construct($params) {
		$this->config = $this->getConfig($params);
	}
	
	public function _getAllSender() {
		
		//not avalible for this sms transport
		
		return array();
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
		
		$xml = '<?xml version="1.0" encoding="UTF-8"?> 
		<message> 
		<login>'.$this->config->login.'</login> 
		<pwd>'.$this->config->passw.'</pwd> 
		<id>'.mktime().rand(10,99).'</id> 
		<sender>'.$sender.'</sender> 
		<text>'.$mess.'</text> 
		<phones> 
		<phone>'.$phones.'</phone> 
		</phones> 
		</message>';
		
		$url = 'https://'.$this->config->server.'/api/message';
		
		$response = $this->openHttp($url, $xml);
		
		
		if(!$response){
			$data = new \stdClass();
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$count = preg_match_all('/<status>(.*)<\/status>/Ui',$response, $matches);
		if($count>0) {
			$status = $matches[1][0];
		}
		//print_r($status);die();
		if($status!='0'){
			preg_match_all('/<message>(.*)<\/message>/Ui',$response, $matches);
			$error = $matches[1][0];
			$data->error = $error.', error code:'.$status;
			$data->error_code = $this->checkSendCode($status);
			//print_r($data);die();
			return $data;
		}
		
		$count_resp = preg_match_all('/<id>(.*)<\/id>/Ui',$response, $matches);
		
		if($count_resp>0){
			$data->id = $matches[1][0];
			$data->cnt = '';
			$data->cost = '';
			$data->balance = '';
			return $data;
		}
		else{
			$data->error = $error;
			$data->error_code = $this->chechErrorCode($error);
			return $data;
		}
	
	}
	
	public function _getBalance () {
	
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?> 
		<info xmlns="http://Giper.mobi/schema/Info"> 
		<login>'.$this->config->login.'</login> 
		<pwd>'.$this->config->passw.'</pwd> 
		</info>';

		$url = 'https://'.$this->config->server.'/api/info';
		
		$response = $this->openHttp($url, $xml);
		
		$data = new \stdClass();
		
		if(!$response){
			$data = new \stdClass();
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$count = preg_match_all('/<status>(.*)<\/status>/Ui',$response, $matches);
		if($count>0) {
			$status = $matches[1][0];
		}
		
		if($status!='0'){
			//preg_match_all('/<message>(.*)<\/message>/Ui',$response, $matches);
			$error = $matches[1][0];
			$data->error = 'balance error code:'.$status;
			$data->error_code = $this->checkBalanceCode($status);
			return $data;
		}
		
		$count_resp = preg_match_all('/<account>(.*)<\/account>/Ui',$response, $matches);
		
		if($count_resp>0) {
			$data->balance = $matches[1][0];
		}else{
			$data->error = $error;
			$data->error_code = $this->chechErrorCode($error);
			return $data;
		}
		
		return $data;
		
	}
	
	public function _getStatusSms($smsid,$phone=false) {
		
		$xml = '<?xml version="1.0" encoding="UTF-8"?> 
		<dr> 
		<login>'.$this->config->login.'</login> 
		<pwd>'.$this->config->passw.'</pwd> 
		<id>'.$smsid.'</id> 
		</dr>';
		
		$url = 'https://'.$this->config->server.'/api/dr';
		
		$response = $this->openHttp($url, $xml);

		$data = new \stdClass();
		
		if(!$response){
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$count = preg_match_all('/<status>(.*)<\/status>/Ui',$response, $matches);
		if($count>0) {
			$status = $matches[1][0];
		}
		
		if($status!='0'){
			//preg_match_all('/<message>(.*)<\/message>/Ui',$response, $matches);
			$error = $matches[1][0];
			$data->error = 'report error code:'.$status;
			$data->error_code = '9998';
			return $data;
		}
		
		
		$count_resp = preg_match_all('/<report>([^<]+)<\/report>/Ui',$response, $matches);
		
		if($count_resp>0){
			//if($this->_checkStatus($matches[1][0])){
			$data->last_timestamp = time();
			$data->status = $this->_checkStatus($matches[1][0]);
			return $data;
			//}
		}
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		
	}
	
	private function getConfig($params) {
		
		$c = new \stdClass();
		if(strpos($params['login'],"||")!==false){
			$arPrm = explode("||",$params['login']);
		}else{
			$arPrm = array('smspro.nikita.kg',$params['login']);
		}
		$c->login = $arPrm[1];
		$c->server = $arPrm[0];
		$c->passw = $params['passw'];
		$c->sender = $params['sender'];
		$c->charset = $params['charset'];
		
		return $c;
		
	}
	
	private function openHttp($url, $xml) {
	
		if (!function_exists('curl_init')) {
		    die('ERROR: CURL library not found!');
		}

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-type: text/xml; charset=utf-8' ) );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_CRLF, true );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml );
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );

		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
		
	}
	
	private function checkBalanceCode($code) {
		
		if($code == 1) return 1;
		if($code == 2) return 2;
		if($code == 3) return 4;
		
		return 9999;
	}
	
	private function checkSendCode($code) {
		
		if($code == 1) return 1;
		if($code == 2) return 2;
		if($code == 3) return 4;
		if($code == 4) return 3;
		if($code == 5) return 6;
		if($code == 6) return 6;
		if($code == 7) return 7;
		if($code == 8) return 1;
		if($code == 9) return 6;
		if($code == 10) return 1;
		if($code == 11) return 8;
		
		return 9999;
	}
	
	private function _checkStatus($code) {
	
		if($code=='0') return 1;
		if($code=='1') return 2;
		if($code=='2') return 7;
		if($code=='3') return 4;
		if($code=='4') return 7;
		if($code=='5') return 10;
		if($code=='6') return 12;
		if($code=='7') return 12;
		
		return 9999;
		
	}
	
}
?>