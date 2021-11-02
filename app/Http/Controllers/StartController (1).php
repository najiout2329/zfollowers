<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Service;
use App\Cat;
use App\Blog;
use App\Services\PaypalService;
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
               // echo $request->input('payment_mode');
                //$amount = $request->input('amount');
              print_r($request);exit;
                $this->testcode($amount);
                exit;
				session()->flash('error','This payment gateway in under integration.PLease standby!');
				return redirect()->back();
			}
    }
    
    public function approve(){
        $paymentPlatform=resolve(PaypalService::class);
        return $paymentPlatform->handleApproval();
    }
   
    public function cancel(){
        return redirect('/');
    }
	
	public function testcode($amount){
        $data = array(
			'amount' => '100',
			'currency' => 'USD',
			'invoice' => '111111111',
			'externalId' => '32423423423423',
			'successCallbackUrl' => 'https://zfollowers.com/payment/callback?id=12345&status=success&uid=dasd868as46dasd5ads9das',
			'failureCallbackUrl' => 'https://zfollowers.com/payment/callback?id=12345&status=failed&uid=dasd868as46dasd5ads9das',
			'successRedirectUrl' => 'https://zfollowers.com',
			'failureRedirectUrl' => 'https://zfollowers.com'
		);
       
		$payload = json_encode($data);
		$url= "https://lb.sandbox.whish.money/itel-service/api/payment/collect";
		// Prepare new cURL resource
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		// Set HTTP Header for POST request 
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($payload),
			'channel: 10188422',
			'secret: 103e638435d74b59bddba44907264623',
			'websiteurl: https://zfollowers.com',
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
}