<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Model\User;
use App\Model\PaymentMethod;
use Stripe\Error\Card;
use Cartalyst\Stripe\Stripe;
use App\Model\PaymentHistory;
use App\Model\Product;
use App\Model\RequestInfo;
use App\Model\Review;
use App\Model\Notification;
use App\Services\NotificationService;
use App\Model\Podcast;
use App\Model\PodcastUser;

class PaymentController extends Controller
{
	public function __construct()
	{

	}


	public function create(Request $request)
	{
		$token = $request->input('token');
		$cardinfo = $request->input('cardinfo');

		$user = User::where('token',$token)->first();

		if($user)
		{

			$cardinfo['creater'] = $user->id;			
			$paymentmethod = PaymentMethod::create($cardinfo);
			return array('success'=>true,'paymentmethod'=>$paymentmethod);
		}	
		else
		{
			return array('success'=>false);
		}
	}

	public function getpaymentmethod(Request $request)
	{
		$token = $request->input('token');
		$user = User::where('token',$token)->first();

		if($user)
		{
			$paymentmethods = PaymentMethod::where('creater',$user->id)->get();
			return array('success'=>true,'paymentmethod'=>$paymentmethods);
		}
		else
		{
			return array('success'=>false);
		}
	}

	public function history(Request $request)
	{
		$token = $request->input('token');
		$user = User::where('token',$token)->first();

		if($user)
		{
			$histories = PaymentHistory::where('creater',$user->id)->orderBy('created_at','DESC')->get();
			$list = array();
			foreach ($histories as $key => $history) {
				if($history->method)
				{
					array_push($list,$history);
				}
			}

			return ['success'=>true,'history'=>$list];
		}
		else
		{
			return ['success'=>false];
		}
	}

	public function payment(Request $request,NotificationService $notificationservice)
	{
		$requestinfo = $request->input('request');
		$products = $request->input('products');
		$podcasts = $request->input('podcasts');
		$subtotal = $request->input('subtotal');
		$fee = $request->input('fee');
		$token = $request->input('token');
		$stripe = new Stripe(env('STRIPE_SECRET'));
		$cardtoken = $request->input('cardtoken');
		$paymentmethod = $request->input('paymentmethod');
		$shippinginfo = $request->input('shippinginfo');
		$user = User::where('token',$token)->first();

		if($user)
		{
			try
			{
				$charge = $stripe->charges()->create([
					'card'=>$cardtoken,
					'currency'=>'USD',
					'amount'=>$subtotal + $fee,
					'description'=>$user->fullname . ' has paid for ' . ($requestinfo?'request':'card')
				]);

				if($charge['status'] == 'succeeded')
				{
					$paymentdata = array(['amount'=>$fee]);
					$type = 'unknown';
					$requestitem = null;

					if($requestinfo)
					{
						$requestinfo['customerid'] = $user->id;
						$requestitem = RequestInfo::create($requestinfo);
						$influencer = User::where('id',$requestinfo['influencer'])->first();
						$notification = Notification::create([
							'title'=>$user->fullname . ' has created request',
							'description'=>$user->fullname . ' has requested for ' . $requestinfo['type'] . ' to ' .   $influencer->fullname,
							'createdby'=>$influencer->id
						]);

						$notificationservice->sendmessage($notification->title,$notification->description,$influencer->noti_token);
						array_push($paymentdata,['id'=>$requestitem->id,'amount'=>$subtotal]);
						$type = 'request';
					}
					else if($products)
					{
						$paymentdata = $products;
						$notificationdata = array();
						foreach ($products as $product) {
							$productinfo = Product::where('id',$product['id'])->first();
							if($productinfo->createrinfo)
							{
								$notification = Notification::create([
									'title'=>$user->fullname . ' has purchased product',
									'description'=>$user->fullname . ' has purchased ' . $product['amount'] . ' of ' . $productinfo->title,
									'createdby'=>$productinfo->createrinfo->id
								]);

								$notificationservice->sendmessage($notification->title,$notification->description,$productinfo->createrinfo->noti_token);
							}
						}

						$type = 'product';
					}
					else if($podcasts)
					{
						$paymentdata = $podcasts;
						foreach ($podcasts as $podcast) {
							$podcastinfo = Podcast::where('id',$podcast['id'])->first();
							PodcastUser::create(
								[
									'userid'=>$user->id,
									'podcast_id'=>$podcast['id']
								]
							);
							if($podcastinfo->createrinfo)
							{
								$notification = Notification::create([
									'title'=>$user->fullname . ' has purchased product',
									'description'=>$user->fullname . ' has purchased ' . $podcastinfo->title,
									'createdby'=>$podcastinfo->createrinfo->id
								]);
								$notificationservice->sendmessage($notification->title,$notification->description,$podcastinfo->createrinfo->noti_token);
							}
							$type = 'podcast';
						}
					}
					else
					{
						array_push($paymentdata,['amount'=>$subtotal]);
					}

					PaymentHistory::create([
						'productinfo'=>json_encode($paymentdata),
						'price'=>$subtotal + $fee,
						'methodid'=>$paymentmethod,
						'type'=>$type,
						'creater'=>$user->id
					]);

					if($podcast)
					{
						return array('success'=>true,'message'=>'You have successfully purchase podcasts','type'=>$type);
					}


					return array('success'=>true,'message'=>$requestitem != null?'You have successfully create request for ' . $requestitem->influencerinfo->fullname:'You have successfully purchase products','type'=>$type);
				}
				else
				{
					return array('success'=>false,'message'=>$charge['status']);
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
		else
		{
			return array('success'=>false,'message'=>'You have to signin first');
		}
	}


	public function getrequest(Request $request)
	{
		$token = $request->input('token');
		$user = User::where('token',$token)->first();
		$array = array();
		if($user)
		{
			if($user->role == 'customer')
			{
				$requests = RequestInfo::where('customerid',$user->id)->orderBy('created_at','DESC')->get();
				foreach ($requests as $key => $value) {
					if($value->influencerinfo)
					{
						$value->influencerinfo->reviews = Review::where('influencerid',$value->influencer)->orderBy('created_at','DESC')->get();
						array_push($array,$value);
					}
				}	
			}
			else
			{
				$requests = RequestInfo::where('influencer',$user->id)->where('status','!=','canceled')->orderBy('created_at','DESC')->get();
				foreach ($requests as $request) {
					if($request->customerinfo)
					{
						array_push($array,$request);
					}
				}
			}

			return ['success'=>true,'data'=>$array];
		}
		else
		{
			return ['success'=>false];
		}
	}

	public function requeststatus(Request $request,NotificationService $notificationservice)
	{
		$token = $request->input('token');
		$user = User::where('token',$token)->first();
		$id = $request->input('id');
		$status = $request->input('status');
		if($user)
		{
			$requestinfo = RequestInfo::where('id',$id)->first();
			if($requestinfo)
			{
				$notificationinfo = [];
				if($status == 'completed')
				{
					$notificationinfo = [
						'title'=>$user->fullname . " has sended " . $requestinfo->type . ' for request',
						'description'=>$user->fullname . ' has sended ' . $requestinfo->type . ' for request',
						'createdby'=> $requestinfo->customerid
					];
				}
				else if($status == 'canceled')
				{
					$notificationinfo = [
						'title'=>$user->fullname . " has canceled the " . $requestinfo->type . ' request',
						'description'=>$user->fullname . ' has canceled ' . $requestinfo->type . ' request',
						'createdby'=> $requestinfo->customerid
					];	
				}

				$requestinfo->update(['status'=>$status]);

				$notification = Notification::create($notificationinfo);
				$notificationservice->sendmessage($notification->title,$notification->description,$requestinfo->customerinfo->noti_token);

				return ['success'=>true];
			}
			else
			{
				return ['success'=>false];
			}
		}
		else
		{
			return ['success'=>false];
		}
	}


}