<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use App\Model\User;
use App\Model\Review;
use Illuminate\Support\Str;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use App\Mail\forgotpassword;
use Stripe\Error\Card;
use Cartalyst\Stripe\Stripe;

class UserController extends Controller
{
	public function __construct()
	{

	}

	public function uploadimage(Request $request)
	{
		$profile = $request->file('profile');		

		if($profile)
		{
			$profileupload = "public/profile";
			$profile->move($profileupload,$profile->getClientOriginalName());
			$url = $profileupload . '/' . $profile->getClientOriginalName();

			return array('success'=>true,'url'=>$url);
		}
		else
		{
			return array('success'=>false);
		}
	}


	public function uploadvideo(Request $request)
	{
		$video = $request->file('video');

		if($video)
		{
			$videoupload = "public/video";
			$video->move($videoupload,$video->getClientOriginalName());
			$url = $videoupload . '/' . $video->getClientOriginalName();

			return array('success'=>true,'url'=>$url);
		}
		else
		{
			return array('success'=>false);
		}
	}

	public function payment(Request $request)
	{
		$token = $request->input('token');
		$amount = $request->input('amount');
		$username = $request->input('username');
		$membership = $request->input('memebership');
		$stripe = new Stripe(env('STRIPE_SECRET'));
		try
		{
			if($token)
			{
				$charge = $stripe->charges()->create([
					'card'=>$token,
					'currency'=>'USD',
					'amount'=>$amount,
					'description'=>$username . ' has paid for ' . $memebership
				]);

				if($charge['status'] == 'succeeded')
				{
					return array('success'=>true);
				}
			}
			else
			{
				return array('success'=>false,'message'=>'Token is required');
			}
		}
		catch(Exception $e)
		{
			return array('success'=>false,'message'=>$e->getMessage());
		}
		catch(\Cartalyst\Stripe\Exception\CardErrorException $e)
		{
			return array('success'=>false,'message'=>$e->getMessage());
		}
		catch(\Cartalyst\Stripe\Exception\MissingParameterException $e)
		{
			return array('success'=>false,'message'=>$e->getMessage());
		}		
	}

	public function sendnotification(Request $request,NotificationService $notification)
	{
		$tokens = $request->input('token');
		$message = $request->input('message');	
		$title = $request->input('title');

		foreach ($tokens as $token) {
			$notification->sendmessage($title,$message,$token);
		}

		return array('success'=>true);
	}

}

?>