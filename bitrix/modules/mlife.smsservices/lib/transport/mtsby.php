<?php
namespace Mlife\Smsservices\Transport;

class Mtsby extends \Mlife\Smsservices\Base\Transport{

    private $config;

    //конструктор, получаем данные доступа к шлюзу
    function __construct($params) {
        $this->config = $this->getConfig($params);
    }

    public function _getBalance() {

        $data = new \stdClass();
        $data->error = 'transport does not support getting a balance';
        $data->error_code = '9999';

        return $data;

    }

    public function _getAllSender() {

        return array();

    }

    public function _sendSms ($phones, $mess, $time=0, $sender=false) {

        $arSms = array();
        $url = 'https://'.$this->config->server.'/json2/simple';
        $phones = preg_replace("/[^0-9A-Za-z]/", "", $phones);
        $text = $this->siteCharsetToUtf($mess);

        if(!$sender) {
            $sender = $this->config->sender;
        }

        $params = array(
            'phone_number'=>$phones,
            'channels'=>array('sms'),
            'channel_options'=>array(
                'sms'=>array(
                    'text'=>$text,
                    'alpha_name'=>$sender,
                    'ttl'=>86400,
                )
            )
        );

        $response = $this->openHttp($url, $params);

        if(!$response) return $this->emptyResponse();

        $responseData = json_decode($response,true);

        $data = new \stdClass();

        if($responseData['error_code']){
            $data->error = $responseData['error_code'].', '.$responseData['error_text'];
            $data->error_code = '9999';

            return $data;
        }

        if($responseData['message_id']){

            $data->id = $responseData['message_id'];
            $data->cnt = '';
            $data->cost = '';
            $data->balance = '';

            return $data;

        }

        return $this->unknownResponse();

    }

    public function _getStatusSms($smsid,$phone=false) {

        $arSms = array();
        $url = 'https://'.$this->config->server.'/dr/'.$smsid.'/simple';
        $response = $this->openHttp($url, false);
        if(!$response) return $this->emptyResponse();

        $responseData = json_decode($response,true);
        $data = new \stdClass();

        if($responseData['error_code']){
            $data->error = $responseData['error_code'].', '.$responseData['error_text'];
            $data->error_code = '9999';

            return $data;
        }

        if($responseData['status']){
            $data->last_timestamp = floor($responseData['time']/1000);
            $data->status = $this->_checkStatus($responseData['status']);
            if(!$data->status) $data->status = 12;
            return $data;
        }

        return $this->unknownResponse();

    }


    private function getConfig($params) {

        $c = new \stdClass();

        if(strpos($params['login'],"||")!==false){
            $arPrm = explode("||",$params['login']);
        }else{
            $arPrm = array('api.br.mts.by',$params['login']);
        }
        $c->login = $arPrm[1];
        $c->server = $arPrm[0];

        $c->passw = $params['passw'];
        $c->sender = $params['sender'];
        $c->charset = $params['charset'];

        return $c;

    }

    private function _checkStatus($code) {

        if($code=='6') return 3;
        if($code=='1') return 3;
        if($code=='5') return 7;
        if($code=='3') return 5;
        if($code=='2') return 4;
        if($code=='8') return 9;

        return false;

    }

    private function openHttp($url, $data){

        $client = $this->httpClient();

        $client->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $client->setAuthorization($this->config->login, $this->config->passw);
        $client->disableSslVerification();
        if(!$data){
            return $client->get($url);
        }
        elseif($client->query($client::HTTP_POST, $url, json_encode($data)))
        {
            return $client->getResult();
        }
        return false;

    }

}