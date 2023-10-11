<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;
use function Amp\async;
use function Amp\delay;
$future = [];
$array_bans = [];
$messages_id = [];
$telegram = new Telegram($bot_api_key, $bot_username);
$telegram->useGetUpdatesWithoutDatabase();
function newchatmember($from,$to){
	global $telegram,$array_bans,$messages_id;
	$num_chars=6;//number of characters for captcha image
    $characters=array_merge(range(0,9),range('A','Z'),range('a','z'));//creating combination of numbers & alphabets
    shuffle($characters);//shuffling the characters
    $captcha_text="";
    for($i=0;$i<$num_chars;$i++)
    {
        $captcha_text.=$characters[rand(0,count($characters)-1)];
    }
    // captcha generator
	$captcha_image=imagecreatetruecolor(200,200);
	$captcha_background=imagecolorallocate($captcha_image,0,0,0);//setting captcha background colour
	$captcha_text_colour=imagecolorallocate($captcha_image,255,255,255);//setting cpatcha text colour
	imagefilledrectangle($captcha_image,0,0,120,29,$captcha_background);//creating the rectangle
	$font='arial.ttf';//setting the font path
	imagettftext($captcha_image,20,0,50,100,$captcha_text_colour,$font,$captcha_text);
	imagepng($captcha_image,__DIR__ . '/captcha/'.$captcha_text.'.png');

	$result = Request::sendPhoto([
	    'chat_id' => $to,
	    'photo'   => Request::encodeFile(__DIR__ . '/captcha/'.$captcha_text.'.png'),
	]);
    // end captcha generator
    
	$msg_id = $result->getResult()->getMessageId();
    $ban_user = array();
    $ban_user['id'] = $to;
    $ban_user['user_id'] = $from;
    $ban_user['text'] = $captcha_text;
    $ban_user['time'] = time();
    $array_bans[$from] = $ban_user;

    $msg_gen['time'] = time();
    $msg_gen['channel'] = $to;
    $msg_gen['id'] = $msg_id;
    $msg_gen['text'] = $captcha_text;
    $messages_id[] = $msg_gen;
}
function check($from,$text,$msges_id){
	global $telegram,$array_bans,$messages_id;
	if (isset($array_bans[$from]) and $array_bans[$from]['text']==$text) {
	    unset($array_bans[$from]);
		foreach ($messages_id as $key => $msg_gen) {
	        if ($msg_gen['text']==$text) {
	   			unset($messages_id[$key]);
	        	Request::deleteMessage([
	                'chat_id'    => $msg_gen['channel'],
	                'message_id' => $msg_gen['id'],
	            ]);
	            unlink(__DIR__ . '/captcha/'.$msg_gen['text'].'.png');
				delete($msg_gen['channel'],$msges_id);
	        }
	    }
	    var_dump(true);
	}else{
	    var_dump(false);
	}
}
function delete($chat_id,$msg_id){
	$req = Request::deleteMessage([
        'chat_id'    => $chat_id,
        'message_id' => $msg_id,
    ]);
    var_dump($req);
}
while (true) {
	try {
		delay(5);
	    $server_response = $telegram->handleGetUpdates();
	    if ($server_response->isOk()) {
	        $update_count = count($server_response->getResult());
	    	if ($update_count>0) {
	    		foreach ($server_response->getResult() as $result) {
	    			$type = $result->getUpdateContent()->getType();
	    			$from = $result->getUpdateContent()->getFrom()->getId();
	    			$bot = $result->getUpdateContent()->getFrom()->getIsBot();
	    			$chat = $result->getUpdateContent()->getChat()->getId();
	    			if ($result->getUpdateContent()->getSenderChat()) {
	    				$chat_type = $result->getUpdateContent()->getSenderChat()->getId();
	    			}
	    			if ($type=='new_chat_members') {
						newchatmember($from,$chat);
	    			}

	    			if ($type=='left_chat_member') {
		            	delete($chat,$result->getUpdateContent()->getMessageId());
	    			}
	    			if ($type=='command') {
	    				$perm = false;
	    				$text = $result->getUpdateContent()->getText();
    					$reply = $result->getUpdateContent()->getReplyToMessage();
    					$ban_user_id=null;
    					if ($reply) {
    						$ban_user_id = $reply->getFrom()->getId();
    					}
						$response =	Request::getChatAdministrators([
							'chat_id'=>$chat
						]);
						foreach ($response->getResult() as $admin) {
							if ($admin->getUser()->getId()==$from) {
								$perm=true;
							}
						}
	    				if (($text=='/unban' and $perm and $ban_user_id) or ($text=='/unban' and $chat_type==$chat)) {
	    					var_dump('asdfasdfasdfasdfd');
							$response =	Request::unbanChatMember([
								'chat_id'=>$chat,
								'user_id'=>$ban_user_id
							]);
							var_dump($response);
							$perm=false;
	    				}
	    				if (($text=='/ban' and $perm and $ban_user_id) or ($text=='/ban' and $chat_type==$chat)) {
							$response =	Request::kickChatMember([
								'chat_id'=>$chat,
								'user_id'=>$ban_user_id
							]);
							$perm=false;
	    				}if ((strpos($text, '/mute')===0 and $perm and $ban_user_id) or (strpos($text, '/mute')===0 and $chat_type==$chat)) {
				            $timed= explode(' ', $text);
				            $type = 'h';
				            $time_count = $timed[1];
							$perm=false;
				            if (count($timed)>2) {
				                $type = $timed[2];
				            }
				            switch ($type) {
				                case 's':
				                        if ($time_count<30) {
				                            $time_count = 30;
				                        }
				                    break;
				                case 'h':
				                        if ($time_count>148) {
				                            $time_count = 148;
				                        }
				                        $time_count = $time_count*60*60;
				                    break;
				                case 'd':
				                        if ($time_count>7) {
				                            $time_count = 7;
				                        }
				                            $time_count = $time_count*24*60*60;
				                    break;
				            }
            				$time_count = time()+$time_count;
							$response =	Request::kickChatMember([
								'chat_id'=>$chat,
								'user_id'=>$ban_user_id,
								'until_date'=>$time_count
							]);
							$perm=false;
	    				}
		            	delete($chat,$result->getUpdateContent()->getMessageId());
	    			}
	    			if ($type=='text') {
	    				$text = $result->getUpdateContent()->getText();
	    				check($from,$text,$result->getUpdateContent()->getMessageId());
	    			}
	    		}
	    	}
			foreach ($array_bans as $ban) {
		        if ((time()-$ban['time'])>=60) {
					$response =	Request::kickChatMember([
						'chat_id'=>$ban['id'],
						'user_id'=>$ban['user_id']
					]);
	            	unlink(__DIR__ . '/captcha/'.$ban['text'].'.png');
		        }
			}
		    foreach ($messages_id as $msg_gen) {
		        if ((time()-$msg_gen['time'])>=60) {
		            delete($msg_gen['channel'],$msg_gen['id']);
		        }
		    }
	    } else {
	        echo $server_response->printError();
	    }
	} catch (Longman\TelegramBot\Exception\TelegramException $e) {
	    // log telegram errors
	     echo $e->getMessage();
	}
}

