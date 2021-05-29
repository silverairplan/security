<?php 

namespace App\Services;
use Kreait\Firebase\Messaging\CloudMessage;

class NotificationService
{
	public function __construct()
	{

	}

	public function sendmessage($title,$body,$token)
	{
		if($token)
		{
			$messaging = app('firebase.messaging');
	        $message = CloudMessage::withTarget('token',$token)->withNotification(['title'=>$title,'body'=>$body]);
	        $messaging->send($message);
		}
	}
}