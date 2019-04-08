<?php

/**
 * Sberbank acquiring
 *
 * Simple Sberbank acquiring library
 * Based on https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:start
 *
 * @link   https://github.com/kenvel/laravel-sberbank
 * @version 1.0
 * @author Dmitry Kenvel <dimult@yahoo.com>
 */

namespace Kenvel;

class Sberbank {
    private $acquiring_url;
    private $access_token;

    private $url_init;
    private $url_cancel;
    private $url_get_state;

    protected $error;
    protected $response;

    protected $payment_id;
    protected $payment_url;
    protected $payment_status;

    /**
     * Inicialize Sberbank class
     * 
     * @param string $acquiring_url like https://securepayments.sberbank.ru
     * @param string $access_token  secure token for sberbank
     */
     public function __construct(string $acquiring_url, string $access_token) {
	$this->acquiring_url = $acquiring_url;
	$this->access_token  = $access_token;
        $this->setupUrls();
     }

    /**
     * Generate Sberbank payment URL
     *
     * -------------------------------------------------
     * For generate url need to send $payment array and array of $items
     * All keys for correct checking in paymentArrayChecked()
     *
     * Sberbank does't recieve description longer that $description_max_lenght
     * $amount_multiplicator - need for convert price to cents
     * 
     * @param  array  $data array of payment data
     * @return array of data
     */
    public function paymentURL(array $data):array {        
        if ( !$this->paymentArrayChecked($data) ) {
            $this->error = 'Incomplete payment data';
            return [
                'success'        => FALSE,
                'error'          => $this->error,
                'response'       => $this->response,
                'payment_id'     => $this->payment_id,
                'payment_url'    => $this->payment_url,
                'payment_status' => $this->payment_status,
            ];
        }

        $description_max_lenght = 24;
        $amount_multiplicator   = 100;

        $data['currency'] = $this->getCurrency();
        $data['amount'] = $data['amount'] * $amount_multiplicator;        
        $data['description'] = mb_strimwidth($data['description'], 0, $description_max_lenght - 1, '');        

        return [
            'success'        => $this->sendRequest($this->url_init, $data),
            'error'          => $this->error,
            'response'       => $this->response,
            'payment_id'     => $this->payment_id,
            'payment_url'    => $this->payment_url,
            'payment_status' => $this->payment_status,
        ];
    }

    /**
     * Check payment status
     * 
     * @param  [string] Sberbank payment id
     * @return array of data
     */
    public function getState(string $payment_id):array {
        $params = [ 'orderId' => $payment_id ];

        return [
            'success'        => $this->sendRequest($this->url_get_state, $params),
            'error'          => $this->error,
            'response'       => $this->response,
            'payment_id'     => $this->payment_id,
            'payment_url'    => $this->payment_url,
            'payment_status' => $this->payment_status,
        ];
    }

    /**
     * TODO: Cancel payment
     * For canceling payment need to use 
     * username and password for inicialize Sberbank API
     */

    /**
     * Send reques to bank acquiring API
     * 
     * @param  string $path API url
     * @param  array  $data params
     * @return bool success or not
     */
    private function sendRequest(string $path,  array $data) {
        $data['token']    = $this->access_token;
        $data = \http_build_query($data, '', '&');

        if($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, $path);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Cache-Control: no-cache',
                'Content-Type:  application/x-www-form-urlencoded',
            ));

            $response = curl_exec($curl);
            curl_close($curl);

            $this->response = $response;
            $json = json_decode($response);            

            if($json) {
                if ( $this->errorsFound() ) {
                    return FALSE;

                } else {                    
                    $this->payment_id       = @$json->orderId;
                    $this->payment_url      = @$json->formUrl;
                    $this->payment_status   = @$json->orderStatus;                    

                    return TRUE;
                }
            }

            $this->error .= "Can't create connection to: $path | with data: $data";
            return FALSE;

        } else {
            $this->error .= "CURL init filed: $path | with data: $data";
            return FALSE;
        }
    }

    /**
     * Finding all possible errors
     * @return bool
     */
    private function errorsFound():bool {
        $response = json_decode($this->response, TRUE);

        if (isset($response['errorCode'])) {
            $error_code = (int) $response['errorCode'];
        } elseif (isset($response['ErrorCode'])) {
            $error_code = (int) $response['ErrorCode'];
        } elseif (isset($response['error']['code'])) {
            $error_code = (int) $response['error']['code'];
        } else {
            $error_code = 0;
        }

        if (isset($response['errorMessage'])) {
            $error_message = $response['errorMessage'];
        } elseif (isset($response['ErrorMessage'])) {
            $error_message = $response['ErrorMessage'];
        } elseif (isset($response['error']['message'])) {
            $error_message = $response['error']['message'];
        } elseif (isset($response['error']['description'])) {
            $error_message = $response['error']['description'];
        } else {
            $error_message = 'Unknown error.';
        }

        if($error_code !== 0){
            $this->error = 'Error code: '. $error_code . ' | Message: ' . $error_message;
            return TRUE;
        }
        return FALSE;     
    }

     /**
     * Check payment array for all keys is isset
     * 
     * @param  array for checking
     * @return [bool]
     */
    private function paymentArrayChecked(array $array_for_check){
        $keys = ['orderNumber', 'amount', 'returnUrl', 'failUrl', 'description', 'language'];
        return $this->allKeysIsExistInArray($keys, $array_for_check);
    }

    /**
     * Checking for existing all $keys in $arr
     * 
     * @param  array $keys - array of keys
     * @param  array $arr - checked array
     * @return [bool]
     */
    private function allKeysIsExistInArray(array $keys, array $arr){
        return (bool) !array_diff_key(array_flip($keys), $arr);
    }

     /**
     * Setting up urls for API
     * 
     * @return void
     */
    private function setupUrls(){
        $this->acquiring_url = $this->checkSlashOnUrlEnd($this->acquiring_url);
        $this->url_init      = $this->acquiring_url . 'payment/rest/register.do';
        $this->url_cancel    = $this->acquiring_url . 'payment/rest/reverse.do';        
        $this->url_get_state = $this->acquiring_url . 'payment/rest/getOrderStatusExtended.do';
    }

    /**
     * Adding slash on end of url string if not there
     * 
     * @return url string
     */
    private function checkSlashOnUrlEnd($url) {
        if ( $url[strlen($url) - 1] !== '/'){
            $url .= '/';
        }
        return $url;
    }

    /**
     * return protected propertys
     * 
     * @param  [mixed] $property name
     * @return [mixed]           value
     */
    public function __get($property){
      if (property_exists($this, $property)) {
        return $this->$property;
      }
    }

    /**
     * Set up currency code
     * 
     * @param string $currency name
     */
    private function getCurrency($currency = 'RUB'){
        if($currency === 'EUR'){
            return '978';
        }
        if($currency === 'USD'){
            return '840';
        }

        if($currency === 'RUB'){
            return '643';
        }
    }
}
