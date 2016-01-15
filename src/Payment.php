<?php

namespace Laravel\Payonline;

use Event;
use Exception;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Redirect;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

use Laravel\Payonline\Events\PaymentWasPaid;

use GuzzleHttp\Client as HttpClient;

class Payment extends Model {

	public static function pay($orderId, $amount, $descr='', $extra=[]) {
		$data = [
			'order_id'		=>	$orderId,
			'amount'		=>	(float)$amount
		];
		if($descr) $data['descr'] = $descr;

		$payment = new Payment;
		$payment->fill($data);
		return $payment;
	}



	protected $table = 'payments';
	protected $fillable = ['order_id', 'amount', 'descr', 'paid_at', 'transaction_id', 'provider', 'card_holder', 'card_number', 'ip', 'extra', 'error', 'checked_at'];

	protected static $payEndpoint;

	public function getPayUrl($extra=[]) {
		$params = [
			'MerchantId'	=>	$this->getMerchantId(),
			'OrderId'		=>	NULL,
			'Amount'		=>	number_format((float)$this->amount, 2, '.', ''),
			'Currency'		=>	$this->preferredCurrency(),
		];

		// valid until
		if(isset($extra['validUntil']) && $extra['validUntil']) {
			if(strtotime($extra['validUntil']) < time()) {
				throw new InvalidArgumentException('Invalid "validUntil" value: '.$extra['validUntil'], 1);
			}

			$params['ValidUntil'] = date('Y-m-d H:i:s', strtotime($extra['validUntil']));
		}

		// order description
		if(trim($this->descr)) {
			$params['OrderDescription'] = str_limit(trim($this->descr), 100, '');
		}
		$params['PrivateSecurityKey'] = $this->getSecurityKey();

		$this->save();
		$params['OrderId'] = $this->getKey();

		// make security key
		$params_str = urldecode(http_build_query($params));
		$securityKey = md5($params_str);
		

		// extra params
		if(isset($extra['returnUrl']) && $extra['returnUrl']) {
			$params['ReturnUrl'] = $extra['returnUrl'];
		}
		if(isset($extra['failUrl']) && $extra['failUrl']) {
			$params['FailUrl'] = $extra['failUrl'];
		}

		// additional params
		if(isset($extra['extra'])) {
			foreach($extra['extra'] as $ek => $ev) {
				$ev = e(trim($ev));
				if(!isset($params[$ek]) && $ev) {
					$params[$ek] = $ev;
				}
			}
		}

		$params['SecurityKey'] = $securityKey;

		return static::getPayEndpoint().'?'.http_build_query($params);
	}

	public function validate(Request $request) {
		$inSecretKey = $request->input('SecurityKey');

		$checkParams = $request->only('DateTime', 'TransactionID', 'OrderId', 'Amount', 'Currency', 'PrivateSecurityKey');

		$result = TRUE;

		if(!isset($checkParams['TransactionID']) || !$checkParams['TransactionID']) {
			//throw new InvalidArgumentException('Invalid "TransactionID" value', 1);
			$result = FALSE;
		}

		if(!isset($checkParams['Amount']) || (float)$checkParams['Amount'] != (float)$this->amount) {
			//throw new InvalidArgumentException('Invalid "Amount" value', 1);
			$result = FALSE;
		}

		if(!isset($checkParams['PrivateSecurityKey']) || $checkParams['PrivateSecurityKey'] != $this->getSecurityKey()) {
			//throw new InvalidArgumentException('Invalid "PrivateSecurityKey" value', 1);
			$result = FALSE;
		}

		$securityKey = md5(urldecode(http_build_query($checkParams)));

		if($securityKey !== $inSecretKey) {
			//throw new InvalidArgumentException('Invalid "secretKey" value', 1);
			$result = FALSE;
		}

		// save data
		if($result) {
			if(!$this->isPayed()) {
				//$result = $this->checkStatus();
				if($result) {
					$this->fill([
						'extra'				=>	json_encode($request->all()),
						'paid_at'			=>	date('Y-m-d H:i:s'),
						'transaction_id'	=>	$request->input('TransactionID'),
						'provider'			=>	$request->input('Provider'),
						'card_holder'		=>	$request->input('CardHolder'),
						'card_number'		=>	$request->input('CardNumber'),
						'ip'				=>	$request->input('IpAddress'),
					]);
					$this->save();

					$event_result = event(new PaymentWasPaid($this));
				}
			}
		}

		return $result;
	}

	public function isPayed() {
		return $this->paid_at;
	}

	public function checkStatus() {
		$params = [
			'MerchantId'			=>	$this->getMerchantId(),
			'OrderId'				=>	$this->getKey(),
			'PrivateSecurityKey'	=>	$this->getSecurityKey(),
		];

		$params_str = http_build_query($params);
		$securityKey = md5($params_str);

		$params['SecurityKey'] = $securityKey;

		$client = new HttpClient();
		print('<pre>'.print_r($params,1).'</pre>');
		$response = $client->request('GET', static::getPayEndpoint('search').'?'.http_build_query($params));

		$result = FALSE;
		if($response->getStatusCode() == 200) {
			$result = $response->getBody()->getContents();
			if(empty($result)) $result = FALSE;
			else {
				$str = $result;
				parse_str($str, $result);
			}
		}

		return $result;
	}


	public function preferredCurrency() {
        return 'RUB';
    }



    // endpoint
	public static function getPayEndpoint($method='') {
		if(!static::$payEndpoint) {
			static::setPayEndpoint();
		}
		return static::$payEndpoint.($method ? $method.'/' : '');
	}
	public static function setPayEndpoint() {
		static::$payEndpoint = 'https://secure.payonlinesystem.com/payment/';
	}

	// merchantId
	public static function getMerchantId() {
		return config('payonline.merchantId');
	}

	// securityKey
	public static function getSecurityKey() {
		return config('payonline.secretKey');
	}




	public function getDates() {
		return ['created_at', 'updated_at', 'paid_at', 'checked_at'];
	}
}

class PaymentException extends Exception {}