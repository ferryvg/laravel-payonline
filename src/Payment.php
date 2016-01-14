<?php

namespace Laravel\Payonline;

use App;
use Exception;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Redirect;
use Request;
//use Illuminate\Database\Eloquent\Model;

use GuzzleHttp\Client as HttpClient;

trait Payment {

	protected $table = 'payments';
	protected $fillable = ['order_id', 'amount', 'descr', 'paid_at', 'transaction_id', 'provider', 'card_holder', 'card_number', 'ip', 'extra', 'error', 'checked_at'];

	protected static $payEndpoint;
	protected static $merchantId;
	protected static $securityKey;

	public function getPayUrl() {
		$params = [
			'MerchantId'	=>	$this->getMerchantId(),
			'OrderId'		=>	$this->id,
			'Amount'		=>	$this->amount,
			'Currency'		=>	$this->preferredCurrency(),
		];

		// valid until
		if(isset($data['validUntil']) && $data['validUntil']) {
			if(strtotime($data['validUntil']) < time()) {
				throw new InvalidArgumentException('Invalid "validUntil" value: '.$data['validUntil'], 1);
			}

			$params['ValidUntil'] = date('Y-m-d H:i:s', strtotime($data['validUntil']));
		}

		// order description
		if(trim($this->descr)) {
			$params['OrderDescription'] = str_limit(e(trim($this->descr)), 100, '');
		}
		$params['PrivateSecurityKey'] = $this->getSecurityKey();

		// make security key
		$params_str = http_build_query($params);
		$securityKey = md5($params_str);
		

		// extra params
		if(isset($data['returnUrl']) && $data['returnUrl']) {
			$params['ReturnUrl'] = rawurlencode($data['returnUrl']);
		}
		if(isset($data['failUrl']) && $data['failUrl']) {
			$params['FailUrl'] = rawurlencode($data['failUrl']);
		}
		$params['SecurityKey'] = $securityKey;

		$this->save();

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

		if(!isset($checkParams['Amount']) || $checkParams['Amount'] != $this->amount) {
			//throw new InvalidArgumentException('Invalid "Amount" value', 1);
			$result = FALSE;
		}

		if(!isset($checkParams['PrivateSecurityKey']) || $checkParams['PrivateSecurityKey'] != $this->getSecurityKey()) {
			//throw new InvalidArgumentException('Invalid "PrivateSecurityKey" value', 1);
			$result = FALSE;
		}

		$securityKey = md5(http_build_query($checkParams));

		if($securityKey !== $inSecretKey) {
			//throw new InvalidArgumentException('Invalid "secretKey" value', 1);
			$result = FALSE;
		}

		// save data
		if($result) {
			$this->fill([
				'extra'				=>	json_encode($request),
				'payed_at'			=>	date('Y-m-d H:i:s', strtotime($request->input('DateTime'))),
				'transaction_id'	=>	$request->input('TransactionID'),
				'provider'			=>	$request->input('Provider'),
				'card_holder'		=>	$request->input('CardHolder'),
				'card_number'		=>	$request->input('CardNumber'),
				'ip'				=>	$request->input('IpAddress'),
			]);
			$this->save();
		}

		return $result;
	}

	public function checkStatus() {
		$params = [
			'MerchantId'			=>	$this->getMerchantId(),
			'OrderId'				=>	$this->id,
			'PrivateSecurityKey'	=>	$this->getSecurityKey(),
		];

		$params_str = http_build_query($params);
		$securityKey = md5($params_str);

		$params['SecurityKey'] = $securityKey;

		$client = new HttpClient();
		$response = $client->request('GET', static::getPayEndpoint('search'), $params);

		print('<pre>'.print_r($response->getStatusCode(),1).'</pre>');
		dd($response->getBody());
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
		return static::$merchantId ?: getenv('PAYONLINE_MERCHANT_ID');
	}
	public static function setMerchantId($id) {
		static::$merchantId = $id;
	}

	// securityKey
	public static function getSecurityKey() {
		return static::$securityKey ?: getenv('PAYONLINE_SECRET');
	}
	public static function setSecurityKey($id) {
		static::$securityKey = $id;
	}




	public function getDates() {
		return ['created_at', 'updated_at', 'payed_at', 'checked_at'];
	}
}

class PaymentException extends Exception {}