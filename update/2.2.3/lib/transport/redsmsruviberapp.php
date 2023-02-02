<?php
namespace Mlife\Smsservices\Transport;

class Redsmsruviberapp{
	
	private $config;
	private $lastHttpClient = null;
	
	//конструктор, получаем данные доступа к шлюзу
	function __construct($params) {
		$this->config = $this->getConfig($params);
	}
	
	public function _getBalance () {
    
        $url = 'https://'.$this->config->server.'/api/client/info';
		$response = $this->httpPost($url);
		if(!$response) return $this->emptyResponse();
		
		$responseData = json_decode($response,true);
		$data = new \stdClass();
		//print_r($responseData);die();
		if(!empty($responseData['error_message'])){
			$responseData = $this->siteCharsetFromUtf($responseData);
			$data->error_code = '9999';
			$data->error = $responseData['error_message'];
			return $data;
		}
		
		if($responseData['success'] && isset($responseData['info']['balance'])){
			$data->balance = $responseData['info']['balance'];
			return $data;
		}
        
		return $this->unknownResponse();
		
    }
	
	public function _getAllSender() {
		
		$url = 'https://'.$this->config->server.'/api/sender-name?type=viber';
		$response = $this->httpGet($url,array('type'=>'viber'));
		if(!$response) return $this->emptyResponse();
		
		$responseData = json_decode($response,true);
		$data = new \stdClass();
		//print_r($responseData);die();
		if(!empty($responseData['error_message'])){
			$responseData = $this->siteCharsetFromUtf($responseData);
			$data->error_code = '9999';
			$data->error = $responseData['error_message'];
			return $data;
		}
		
		if($responseData['success'] && !empty($responseData['items'])){
			$senders = array();
			foreach($responseData['items'] as $sender){
				$ob = new \stdClass();
				$ob->sender = $sender['name'];
				$senders[] = $ob;
			}
			
			return $senders;
		}
		
		return $this->unknownResponse();
	}
	
	public function _sendSms ($phones, $mess, $time=0, $sender=false) {
		
		if(!$sender) {
            $sender = $this->config->sender;
        }
		
		$arPostData = array(
			'route' => 'viber', 
            'from' => $sender,
            'text' => $this->siteCharsetToUtf($mess),
            'to' => $phones,
			'validity' => intval(\Bitrix\Main\Config\Option::get("mlife.smsservices","limittimesms",600,""))
		);
		$url =  'https://'.$this->config->server.'/api/message';
		
		$response = $this->httpPost($url,$arPostData);
		if(!$response) return $this->emptyResponse();
		
		$responseData = json_decode($response,true);

		$data = new \stdClass();
		
		if(!empty($responseData['error_message'])){
			$responseData = $this->siteCharsetFromUtf($responseData);
			$data->error_code = '9999';
			$data->error = $responseData['error_message'];
			return $data;
		}
		
		if(!empty($responseData['errors'])){
			$responseData = $this->siteCharsetFromUtf($responseData);
			$data->error_code = '9999';
			$data->error = implode("\n",$responseData['errors']);
			return $data;
		}
		
		if($responseData['success'] && !empty($responseData['items'])){
			$senders = array();
			foreach($responseData['items'] as $msg){
				$data->id = $msg['uuid'];
				$data->cnt = $responseData['count'];
				$data->cost = '';
				$data->balance = '';
				break;
			}
			return $data;
		}
		
		return $this->unknownResponse();
		
	}
	
	public function _getStatusSms($smsid, $phone = false) {
		
		$url = 'https://'.$this->config->server.'/api/message/'.$smsid;
		$response = $this->httpGet($url,array());
		if(!$response) return $this->emptyResponse();
		
		$responseData = json_decode($response,true);
		$data = new \stdClass();
		
		if(!empty($responseData['error_message'])){
			$responseData = $this->siteCharsetFromUtf($responseData);
			$data->error_code = '9999';
			$data->error = $responseData['error_message'];
			return $data;
		}
		
		if($responseData['success'] && isset($responseData['item']['status'])){
			$data->last_timestamp = $responseData['item']['status_time'];
			$data->status = $this->_checkStatus($responseData['item']['status']);
			if(!$data->status) $data->status = 12;
			return $data;
		}
		
		return $this->unknownResponse();
		
	}
	
	public function httpGet($url,$data=array()){
		return $this->httpClient($data)->get($url);
	}
	
	public function httpPost($url,$data=array()){
		return $this->httpClient($data)->post($url,$data);
	}
	
	protected function httpClient($params=array()){
		
		ksort($params);
        reset($params);
        $ts = microtime().rand(0, 10000);
		
		$this->lastHttpClient = new \Bitrix\Main\Web\HttpClient(array('charset'=>'utf-8'));
		$this->lastHttpClient->disableSslVerification();
		$this->lastHttpClient->setHeader("login", $this->config->login, true);
		$this->lastHttpClient->setHeader("ts", $ts, true);
		$this->lastHttpClient->setHeader("sig", md5(implode('', $params).$ts.$this->config->passw), true);
		return $this->lastHttpClient;
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
    private function _checkStatus($code) {
		
		$code = trim($code);
		
        if($code == 'created') return 2;
        if($code == 'moderation') return 6;
		if($code == 'reject') return 9;
		if($code == 'delivered') return 4;
		if($code == 'read') return 14;
		if($code == 'reply') return 4;
		if($code == 'undelivered') return 7;
		if($code == 'timeout') return 5;
		if($code == 'progress') return 3;
		if($code == 'no_money') return 10;
		if($code == 'doubled') return 9;
		if($code == 'bad_number') return 8;
		if($code == 'stop_list') return 9;
		if($code == 'route_closed') return 9;
		
		return 12;
		
    }
	
	private function getConfig($params) {
		
		$c = new \stdClass();
		
		if(strpos($params['login'],"||")!==false){
			$arPrm = explode("||",$params['login']);
		}else{
			$arPrm = array('cp.redsms.ru',$params['login']);
		}
		$c->login = $arPrm[1];
		$c->server = $arPrm[0];
		
		$c->passw = $params['passw'];
		$c->sender = $params['sender'];
		$c->charset = $params['charset'];
		
		return $c;
		
	}

    public function emptyResponse(){
        $data = new \stdClass();
        $data->error = 'Service is not available';
        $data->error_code = '9998';
        return $data;
    }

    public function unknownResponse(){
        $data = new \stdClass();
        $data->error = 'Unknown response';
        $data->error_code = '9999';
        return $data;
    }

    public function siteCharsetFromUtf($mess){
        if(toLower(SITE_CHARSET) != 'utf-8'){
            if(is_array($mess)){
                $mess = $GLOBALS['APPLICATION']->ConvertCharsetArray($mess, 'UTF-8', SITE_CHARSET);
            }else{
                $mess = $GLOBALS['APPLICATION']->ConvertCharset($mess, 'UTF-8', SITE_CHARSET);
            }
        }
        return $mess;
    }

    public function siteCharsetToUtf($mess){
        if(toLower(SITE_CHARSET) != 'utf-8'){
            if(is_array($mess)){
                $mess = $GLOBALS['APPLICATION']->ConvertCharsetArray($mess, SITE_CHARSET, 'UTF-8');
            }else{
                $mess = $GLOBALS['APPLICATION']->ConvertCharset($mess, SITE_CHARSET, 'UTF-8');
            }
        }
        return $mess;
    }
	
}
?>