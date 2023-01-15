<?
namespace Mlife\Smsservices;

use Bitrix\Main\Error;
use Bitrix\MessageService\Sender\Base;
use Bitrix\MessageService\Sender\Result\SendMessage;

class Bxsms extends Base
{
	
	private $transport;
	
    public function __construct() {
        $this->transport = new Sender();
    }

    public function sendMessage(array $messageFields) {
		
		$phone = $messageFields['MESSAGE_TO'];
		$mess = $messageFields['MESSAGE_BODY'];
		$sender = false;
		if ($messageFields['MESSAGE_FROM']){
            $sender = $messageFields['MESSAGE_FROM'];
        }
		if($sender === 'default') $sender = false;
		
		$result = new SendMessage();
		$this->transport->event = 'MessageService';
		$this->transport->eventName = 'MessageService';
		$this->transport->sendSms($phone,$mess,0,$sender);
		$result->setAccepted();

        return $result;
    }

    public function getShortName() {
        return 'mlife.smsservices';
    }

    public function getId() {
        return 'mlifesmsservices';
    }

    public function getName() {
        return 'mlife.smsservices';
    }

    public function canUse() {
        return true;
    }
	
	public function isCorrectFrom($from){
		return true;
	}

    public function getFromList() {
		
        $data = $this->transport->getAllSender();
		$senders = array();
		
        if (is_array($data)) {
            foreach($data as $sender){
				if(is_object($sender) && $sender->sender) $senders[] = array('id'=>$sender->sender, 'name'=>$sender->sender);
			}
        }
		
		if(empty($senders)){
			$senders[] = array('id'=>'default', 'name'=>'default');
		}

        return $senders;
    }
}