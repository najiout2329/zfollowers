<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Service;
use App\Cat;
use App\Blog;
use App\Services\PaypalService;
use App\Services\WhishService;
use App\Order;
class StartController extends Controller
{
    public function welcome(){
        return view('welcome');
    }
    public function contact(){
        return view('contact');
    }
    
    public function filter_services($cat_id){
        $services=Service::where('cat_id',$cat_id)->where('status','active')->latest()->get();
        return $services;
        
    }
   
    public function parent_service($slug){
        $parent=Cat::where('slug',$slug)->first();
        if($parent){
            return view('parent-services',['parent'=>$parent]);
        }else{
            return 'not found';
        }
    }
    
    public function cat_service($parent_slug,$cat_slug){
        $parent=Cat::where('slug',$parent_slug)->first();
        if($parent){
            $cat=Cat::with('cat')->where('slug',$cat_slug)->where('cat_id',$parent->id)->first();
            if($cat){
                return view('cat-services',['cat'=>$cat]);
            }else{
               return 'not found';
            }
        }else{
            return 'not found';
        }
    }

    public function order($service_slug,$service_id){
        $service=Service::with('cat')->with('parent')->find($service_id);
        if($service){
            if($service->discount==null){
                    $price=$service->price;
                }else{
                    $price=round($service->price-($service->price*$service->discount/100),2);
                }
                $service['price']=$price;
            return view('order',['service'=>$service]);
        }else {
            return 'not found';
        }
    }

    public function save_session1(Request $request){
        if($request->instagram_username){
            session()->put('instagram_username',$request->instagram_username);
        }elseif ($request->url) {
            session()->put('url',$request->url);
        }
        session()->put('email',$request->email);
        session()->put('service_id',$request->service['id']);
        
    }
    public function get_sessions(){
        $sessions=[];
        $sessions['instagram']=session('instagram_username');
        $sessions['url']=session('url');
        $sessions['email']=session('email');

        return $sessions;
    }
    public function get_auth(){
        return user();
    }

    public function faq(){
        
        return view('faq');
    }
    public function terms(){
        
        return view('terms');
    }
    public function blogs(){
        
        return view('blogs');
    }
    public function blog($slug){
        $blog=Blog::where('slug',$slug)->firstOrFail();
        return view('blog',['blog'=>$blog]);
    }

     public function login(){
            if(auth()->check()){
            return redirect('/checkout/order');
            }else{
                session()->put('checkout','yes');
             return view('login');
            }
    }
    public function dologin(){
    	$remember=request('remember')==1?true:false;
    	if(auth()->attempt(['email'=>request('email'),'password'=>request('password')],$remember)){

    		return redirect('/checkout/order');
    	}else{
    		session()->flash('error','Information Error, please check your login Information!');
    		return redirect()->back();
    	}
    }
    

    public function checkout(){
        
        $service=Service::findOrFail(session('service_id'));
        if(session()->has('checkout')){
            session()->forget('checkout');
        }
        return view('checkout',['service'=>$service]);
    }




    public function pay(Request $request){  
	        if($request->input('payment_mode') == "paypal")
			{
				$paymentPlatform=resolve(PaypalService::class);
				return $paymentPlatform->handlePayment($request);   
			}
			else
			{
               // print_r($request->all()); exit;
               //print_r(session()->all());exit;
               $amount = $request->input('amount');
               //$amount = '100';
               $response = $this->creditDebitPayment($amount);
			   //return $response;
			   //die;
               $decoderes =  json_decode($response, true);
               $url = $decoderes['data']['collectUrl'];
                session()->forget('email');
                session()->forget('instagram_username');
                session()->forget('url');
                session()->forget('service_id');
                session()->flash('success','Your order has been placed,Thank You For choosing '.settings()->site_name);
               return redirect($url);
			}
    }
    
    public function approve(){
        $paymentPlatform=resolve(PaypalService::class);
        return $paymentPlatform->handleApproval();
    }
    public function cancel(){
        return redirect('/');
    }

    public function callback(Request $request){
       
       $service_id = $request->input('service_id');
       //$service_id = '9';
       $status = $request->input('status');
       //$user_id = $request->input('user_id');
       $user_id = $request->input('user_id');
       //$username = $request->input('username');
       $username = $request->input('uid');
       $email = $request->input('email');
       $price = $request->input('price');
       //$price = '100';
       $url = $request->input('url');
       $instagram_username = $request->input('instagram_username');

       $data = [
        'service_id'=>$service_id,
        'user_id'=>$user_id,
        'payment_method'=>'cc_payment',
        'username'=>$username,
        'email'=>$email,
        'price'=>$price,
        'url'=>$url,
        'instagram_username'=>$instagram_username,
        'status'=>$status
       ];       

      if($status == "success")
	  {
       // creating an Order
       Order::create([
           'service_id'=>$service_id,
           'user_id'=>$user_id,
           'payment_method'=>'cc_payment',
           'username'=>$username,
           'email'=>$email,
           'price'=>$price,
           'url'=>$url,
           //'instagram_username'=>$instagram_username,
		   'instagram_username'=>$url,
           'status'=>"progress"
       ]);
	  }
	  else
	  {
		  // creating an Order
       Order::create([
           'service_id'=>$service_id,
           'user_id'=>$user_id,
           'payment_method'=>'cc_payment',
           'username'=>$username,
           'email'=>$email,
           'price'=>$price,
           'url'=>$url,
           //'instagram_username'=>$instagram_username,
		   'instagram_username'=>$url,
           'status'=>"canceled"
       ]);
	  }
	   $payloadcallback = json_encode($data);
	   mail("chandola.neeraj@gmail.com","CallBack Response",$payloadcallback);
	   mail("chandola.neeraj@gmail.com","CallBack Response Status",$status);
       return true;
    }
	
	
	
	public function creditDebitPayment_bkp($amount){

        $service_id=session('service_id');
        $user_id=auth()->check()?auth()->user()->id:'';
        $username=auth()->check()?auth()->user()->name:'';
        $email=session('email');
        $url=session('url');
        $instagram_username=session('instagram_username');

        $data = array(
			'amount' => $amount,
			'currency' => 'USD',
			'invoice' => rand(111111111,999999999),
			'externalId' => rand(111111111,999999999),
			'successCallbackUrl' => 'https://zfollowers.com/payment/callback?service_id='.$service_id.'&user_id='.$user_id.
            '&username='.$username.'&email='.$email.'&price='.$amount.'&url='.$url.'&instagram_username='.$instagram_username.'&status=success',
			'failureCallbackUrl' => 'https://zfollowers.com/payment/callback?service_id='.$service_id.'&user_id='.$user_id.
            '&username='.$username.'&email='.$email.'&price='.$amount.'&url='.$url.'&instagram_username='.$instagram_username.'&status=failure',
			'successRedirectUrl' => 'https://zfollowers.com/',
			'failureRedirectUrl' => 'https://zfollowers.com/'
		);
        //echo "<pre>";
        //print_r($data);exit;
       
		$payload = json_encode($data);

        mail("chandola.neeraj@gmail.com","Request",$payload);

	   // $url= "https://lb.sandbox.whish.money/itel-service/api/payment/collect";
		$url= "https://whish.money/itel-service/api/payment/collect";
		// Prepare new cURL resource
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		// Set HTTP Header for POST request 
		/*curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($payload),
			'channel: 10188422',
			'secret: 103e638435d74b59bddba44907264623',
			'websiteurl:zfollowers.com',
			)
		);*/
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length:' . strlen($payload),
			'channel:10292246',
			'secret:0fda2c8338004e54953c1ef64430c0ed',
			'websiteurl:zfollowers.com',
			)
		);
		// Submit the POST request
		$result = curl_exec($ch);
		//print_r($result);
        return $result;
		// Close cURL session handle
		curl_close($ch);
		//die('ffff');
    }
	
	public function creditDebitPayment($amount){

        $service_id=session('service_id');
        $user_id=auth()->check()?auth()->user()->id:'';
        $username=auth()->check()?auth()->user()->name:'';
        $email=session('email');
        $url=session('url');
        $instagram_username=session('instagram_username');

        $data = array(
			'amount' => $amount,
			'currency' => 'USD',
			'invoice' => rand(111111111,999999999),
			'externalId' => rand(111111111,999999999),
			'successCallbackUrl' => 'https://zfollowers.com/payment/callback?service_id='.$service_id.'&user_id='.$user_id.
            '&username='.$username.'&email='.$email.'&price='.$amount.'&url='.$url.'&instagram_username='.$instagram_username.'&status=success',
			'failureCallbackUrl' => 'https://zfollowers.com/payment/callback?service_id='.$service_id.'&user_id='.$user_id.
            '&username='.$username.'&email='.$email.'&price='.$amount.'&url='.$url.'&instagram_username='.$instagram_username.'&status=failure',
			'successRedirectUrl' => 'https://zfollowers.com/',
			'failureRedirectUrl' => 'https://zfollowers.com/'
		);
		$payload = json_encode($data);
        mail("chandola.neeraj@gmail.com","Request",$payload);
		$payload = json_encode($data);
		$context_options = array (
				'http' => array (
					'method' => 'POST',
					'header' => array(
						"channel: 10292246\r\n".
						"secret: 0fda2c8338004e54953c1ef64430c0ed\r\n".
						"websiteurl: zfollowers.com\r\n".
						'Content-Length:' . strlen($payload)."\r\n".
						"Content-type: application/json\r\n\r\n"
					),
					'content' => $payload,
					)
				);
		$url= "https://whish.money/itel-service/api/payment/collect";
		$context = stream_context_create($context_options); 
		$result = file_get_contents($url, false, $context);
		return $result;
    }
}