<?php
namespace Mlife\Smsservices\Transport;

class P1smsru extends \Mlife\Smsservices\Base\Transport{

	private $config;
	
	//конструктор, получаем данные доступа к шлюзу
	function __construct($params) {
		$this->config = $this->getConfig($params);
	}
	
	public function _getBalance () {
		
		$url = 'https://'.$this->config->server.'/apiUsers/getUserBalanceInfo?apiKey='.$this->config->passw;
		$response = $this->httpGet($url);
		if(!$response) return $this->emptyResponse();
		
		$responseData = json_decode($response);
		//echo'<pre>';print_r($responseData);echo'</pre>';
		$data = new \stdClass();
		
		if($responseData->status == 'success'){
			$data->balance = $responseData->data;
			return $data;
		}elseif(($responseData->status == 'error') && $responseData->data->message){
			
			$data->error = $responseData->data->exception.', '.$this->siteCharsetFromUtf($responseData->data->message);
			$data->error_code = '9999';
			
			return $data;
		}
		
		return $this->unknownResponse();
		
	}
	
	public function _getAllSender() {
		
		$url = 'https://'.$this->config->server.'/apiUsers/getUserSenders?apiKey='.$this->config->passw;
		$response = $this->httpGet($url);
		if(!$response) return $this->emptyResponse();
		
		$responseData = json_decode($response);

		$data = new \stdClass();
		
		if($responseData->status == 'success'){
			
			$senders = array();
			foreach($responseData->data as $sender){
				$ob = new \stdClass();
				$ob->sender = $sender->senderName;
				$senders[] = $ob;
			}
			
			return $senders;
			
		}elseif(($responseData->status == 'error') && $responseData->data->message){
			
			$data->error = $responseData->data->exception.', '.$this->siteCharsetFromUtf($responseData->data->message);
			$data->error_code = '9999';
			
			return $data;
		}
		
		return $this->unknownResponse();
		
	}
	
	public function _sendSms ($phones, $mess, $time=0, $sender=false) {
		
		
		$arSms = array();
		$url = 'https://'.$this->config->server.'/apiSms/create';
		$phones = preg_replace("/[^0-9A-Za-z]/", "", $phones);
		$arSms['channel'] = 'digit';
		$arSms['phone'] = $phones;
		$arSms['text'] = $this->siteCharsetToUtf($mess);
		
		if(!$sender) {
			$sender = $this->config->sender;
		}
		if($sender){
			$arSms['sender'] = $sender;
			$arSms['channel'] = 'char';
		}
		
		$arPostData = array('apiKey'=>$this->config->passw,'sms'=>array($arSms));
		
		$response = $this->httpPostArToJson($url,$arPostData);
		if(!$response) return $this->emptyResponse();
		
		$responseData = json_decode($response);

		$data = new \stdClass();
		
		if($responseData->status == 'success'){
			
			$smsResult = $responseData->data[0];
			
			if(!$smsResult->id) {
				return $this->unknownResponse();
			}
			
			$data->id = $smsResult->id;
			$data->cnt = $smsResult->smsCount;
			$data->cost = $smsResult->cost;
			$data->balance = '';
			
			return $data;
			
		}elseif(($responseData->status == 'error') && $responseData->data->message){
			
			$data->error = $responseData->data->exception.', '.$this->siteCharsetFromUtf($responseData->data->message);
			$data->error_code = '9999';
			
			return $data;
		}
		
		return $this->unknownResponse();
	
	}
	
	public function _getStatusSms($smsid,$phone=false) {
		
		$arSms = array();
		$url = 'https://'.$this->config->server.'/apiSms/getSmsStatus?apiKey='.$this->config->passw.'&smsId[0]='.$smsid;
		$response = $this->httpGet($url);
		if(!$response) return $this->emptyResponse();
		
		$responseData = json_decode($response);
		//echo'<pre>';print_r($responseData);echo'</pre>';die();
		$data = new \stdClass();
		
		if(($responseData->status == 'error') && $responseData->data->message){
			
			$data->error = $responseData->data->exception.', '.$this->siteCharsetFromUtf($responseData->data->message);
			$data->error_code = '9999';
			
			return $data;
		}elseif(!empty($responseData)){
			
			$resp = $responseData[0];
			//echo '<pre>';print_r($resp);echo'</pre>';
			if($resp->sms_id && $resp->sms_status){
				$data->last_timestamp = time();
				$data->status = $this->_checkStatus($resp->sms_status);
				if(!$data->status) $data->status = 12;
				return $data;
			}
		}
		
		return $this->unknownResponse();
		
	}
	
	
	private function getConfig($params) {
		
		$c = new \stdClass();
		
		if(strpos($params['login'],"||")!==false){
			$arPrm = explode("||",$params['login']);
		}else{
			$arPrm = array('admin.p1sms.ru',$params['login']);
		}
		$c->login = $arPrm[1];
		$c->server = $arPrm[0];
		
		$c->passw = $params['passw'];
		$c->sender = $params['sender'];
		$c->charset = $params['charset'];
		
		return $c;
		
	}
	
	private function _checkStatus($code) {
	
		if($code=='send') return 3;
		if($code=='sent') return 3;
		if($code=='created') return 2;
		if($code=='planned') return 2;
		if($code=='moderation') return 2;
		if($code=='not_deliver') return 7;
		if($code=='not_delivered') return 7;
		if($code=='expired') return 5;
		if($code=='deliver') return 4;
		if($code=='delivered') return 4;
		if($code=='read') return 4;
		if($code=='low_balance') return 10;
		if($code=='low_partner_balance') return 10;
		if($code=='rejected') return 9;
		if($code=='partly_deliver') return false;
		if($code=='partly_delivered') return false;
		if($code=='error') return false;
		return false;
		
	}
	
}