<?php
/**
 * Bitrix Framework
 * @package    Bitrix
 * @subpackage mlife.smsservices
 * @copyright  2015 Zahalski Andrew
 */

namespace Mlife\Smsservices\Transport;

class Smspby{
	
	private $config;
	
	//конструктор, получаем данные доступа к шлюзу
	function __construct($params) {
		$this->config = $this->getConfig($params);
	}
	
	private function getConfig($params) {
		
		$c = new \stdClass();
		if(strpos($params['login'],"||")!==false){
			$arPrm = explode("||",$params['login']);
		}else{
			$arPrm = array('cp.smsp.by',$params['login']);
		}
		$c->login = $arPrm[1];
		$c->server = 'https://'.$arPrm[0].'/';
		$c->passw = $params['passw'];
		$c->sender = $params['sender'];
		$c->charset = $params['charset'];
		
		return $c;
		
	}
	
	public function _getBalance () {
	
		$url = $this->config->server.'api/user_balance?user='.$this->config->login.'&apikey='.$this->config->passw;
		$response = $this->openHttp($url);

		if(!$response){
			$data = new \stdClass();
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$data_r = json_decode($response);
		//print_r($data_r);die();
		$data = new \stdClass();
		
		if($data_r->status == 'error'){
			$data->error = $this->convertCp($data_r->message);
			$data->error_code = $this->formatErrorCode($data_r->error);
		}elseif($data_r->status == 'success'){
			$data->balance = $data_r->balance;
		}else{
			$data->error = 'Service is not available';
			$data->error_code = '9998';
		}
		//print_r($data);die();
		return $data;
		
	}
	
	public function _getAllSender() {
		
		$url = $this->config->server.'api/user_names?user='.$this->config->login.'&apikey='.$this->config->passw;
		$response = $this->openHttp($url);

		if(!$response){
			$data = new \stdClass();
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$data_r = json_decode($response);
		
		$data = new \stdClass();
		
		if($data_r->status == 'error'){
			$data->error = $this->convertCp($data_r->message);
			$data->error_code = $this->formatErrorCode($data_r->error);
		}elseif($data_r->status == 'success'){
			$arr = array();
			foreach($data_r->names as $sender) {
				$ob = new \stdClass();
				$ob->sender = $sender;
				$arr[] = $ob;
			}
			$data = $arr;
		}else{
			$data->error = 'Service is not available';
			$data->error_code = '9998';
		}
		
		return $data;
		
	}
	
	public function _sendSms ($phones, $mess, $time=0, $sender=false) {
		
		$urgent = 0; //срочность, включается вручную на сервисе
		
		$phones = preg_replace("/[^0-9A-Za-z]/", "", $phones);
		$charset = $this->config->charset;
		if($charset=='windows-1251') {
			$mess = $GLOBALS['APPLICATION']->ConvertCharset($mess, SITE_CHARSET, 'UTF-8');
		}
		
		if(!$sender) {
			$sender = $this->config->sender;
		}
		
		$devKey = '1zD-0F0-whT';
		$url = $this->config->server.'api/msg_send?user='.$this->config->login.'&apikey='.$this->config->passw.'&recipients='.$phones.'&message='.urlencode($mess).'&sender='.$sender.'&urgent='.$urgent.'&devkey='.$devKey;
		$response = $this->openHttp($url);

		if(!$response){
			$data = new \stdClass();
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$data_r = json_decode($response);
		
		$data = new \stdClass();
		
		if($data_r->status == 'error'){
			$data->error = $this->convertCp($data_r->message);
			$data->error_code = $this->formatErrorCode($data_r->error);
		}elseif($data_r->status == 'success'){
			//echo'<pre>';print_r($data_r);echo'</pre>';die();
			//foreach($data_r->messages_id as $v){
				$t = $data_r->messages_id;
				$v = $data_r;
				$data->id = $t[0];
				$data->cnt = $v->count;
				$data->cost = $v->cost;
				$data->balance = $v->balance;
			//}
		}else{
			$data->error = 'Service is not available';
			$data->error_code = '9998';
		}
		
		return $data;
		
	}
	
	//msg_status
	public function _getStatusSms($smsid,$phone=false) {
		
		$url = $this->config->server.'api/msg_status?user='.$this->config->login.'&apikey='.$this->config->passw.'&messages_id='.$smsid;
		$response = $this->openHttp($url);

		if(!$response){
			$data = new \stdClass();
			$data->error = 'Service is not available';
			$data->error_code = '9998';
			return $data;
		}
		
		$data_r = json_decode($response);
		//echo '<pre>';print_r($data_r);echo'</pre>';
		
		$data = new \stdClass();
		
		if($data_r->status == 'error'){
			$data->error = $this->convertCp($data_r->message);
			$data->error_code = $this->formatErrorCode($data_r->error);
		}elseif($data_r->status == 'success'){
			foreach($data_r->messages as $v){
				$v->updated_at = $v->updated_at ? strtotime($v->updated_at) : time();
				$data->last_timestamp = $v->updated_at;
				$data->status = $this->_checkStatus($v->status);
			}
		}else{
			$data->error = 'Service is not available';
			$data->error_code = '9998';
		}
		
		return $data;
		
	}
	
	private function _checkStatus($status_code){
		$status_code = trim($status_code);
		if($status_code == 'new') return '6';
		if($status_code == 'send') return '3';
		if($status_code == 'delivered') return '4';
		if($status_code == 'notdelivered') return '7';
		if($status_code == 'blocked') return '9';
		if($status_code == 'inprogress') return '3';
		//if($status_code == 'absent') return '2';
		
		return 12;
	}
	
	private function formatErrorCode($code) {
		
		//$code = preg_replace('/([^0-9])/','',$code);

		if($code == '1') return '2';
		if($code == '2') return '2';
		if($code == '3') return '9998';
		if($code == '4') return '1';
		if($code == '5') return '1';
		if($code == '6') return '1';
		if($code == '7') return '1';
		
		if($code == '99') return '4';
		
		if($code == '10') return '1';
		if($code == '11') return '1';
		if($code == '12') return '3';
		if($code == '13') return '6';
		if($code == '15') return '6';
		
		return '9998';
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
	
	private function convertCp($mess){
		
		if($this->config->charset=='windows-1251') {
			$mess = $GLOBALS['APPLICATION']->ConvertCharset($mess, 'UTF-8', SITE_CHARSET);
		}
		
		return $mess;
		
	}
	
}