<?php
namespace Mlife\Smsservices\Base;

class Transport {
	
	protected function httpClient(){
		$httpClient = new \Bitrix\Main\Web\HttpClient(array('charset'=>'utf-8'));
		$httpClient->disableSslVerification();
		return $httpClient;
	}
	
	protected function emptyResponse(){
		$data = new \stdClass();
		$data->error = 'Service is not available';
		$data->error_code = '9998';
		return $data;
	}
	
	protected function unknownResponse(){
		$data = new \stdClass();
		$data->error = 'Unknown response';
		$data->error_code = '9999';
		return $data;
	}
	
	protected function siteCharsetFromUtf($mess){
		if(toLower(SITE_CHARSET) != 'utf-8'){
			if(is_array($mess)){
				$mess = $GLOBALS['APPLICATION']->ConvertCharsetArray($mess, 'UTF-8', SITE_CHARSET);
			}else{
				$mess = $GLOBALS['APPLICATION']->ConvertCharset($mess, 'UTF-8', SITE_CHARSET);
			}
		}
		return $mess;
	}
	
	protected function siteCharsetToUtf($mess){
		if(toLower(SITE_CHARSET) != 'utf-8'){
			if(is_array($mess)){
				$mess = $GLOBALS['APPLICATION']->ConvertCharsetArray($mess, SITE_CHARSET, 'UTF-8');
			}else{
				$mess = $GLOBALS['APPLICATION']->ConvertCharset($mess, SITE_CHARSET, 'UTF-8');
			}
		}
		return $mess;
	}
	
	public function httpGet($url){
		return $this->httpClient()->get($url);
	}
	
	public function httpPost($url,$data){
		return $this->httpClient()->post($url,$data);
	}
	
	public function httpPostArToJson($url,$data){
		$client = $this->httpClient();
		
		//print_r(json_encode($data));die();
		$client->setHeader('Content-Type', 'application/json; charset=utf-8', true);
		
		if($client->query($client::HTTP_POST, $url, json_encode($data)))
		{
			return $client->getResult();
		}
		return false;

	}
	
}