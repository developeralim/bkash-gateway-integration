<?php 

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;

class Bkash
{
    protected Client $xhr;

    public function __construct() {
        $this->xhr = new \GuzzleHttp\Client();
    }

    public function token()
    {
        $response = $this->xhr->request('POST', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant', [
            'body' => '{"app_key":"4f6o0cjiki2rfm34kfdadl1eqq","app_secret":"2is7hdktrekvrbljjh44ll3d9l1dtjo4pasmjvs5vl5qr3fug4b"}',
            'headers' => [
                'accept'        => 'application/json',
                'content-type'  => 'application/json',
                'password'      => 'sandboxTokenizedUser02@12345',
                'username'      => 'sandboxTokenizedUser02',
            ],
        ]);

        if ( $json_response = $response->getBody() ) 
        {
            $id_token    = json_decode($json_response)->id_token ?? false;

            $credentials = $this->_get_config_file();

            // put token id to json credentials file

            $credentials['token'] = $id_token;

            $configFile = fopen(__DIR__.'/config.json','w+');
            fwrite($configFile,json_encode($credentials));
            fclose($configFile);
        }

        return $this;
    }

    protected function _get_config_file()
    {
        $path = __DIR__."/config.json";
        return json_decode(file_get_contents($path), true);
    }

    public function createpayment()
    {
  
        $credentials    = $this->_get_config_file();

        $amount         = 100;
        $invoice        = uniqid(); // must be unique
        $intent         = "sale";
        $proxy          = $credentials["proxy"];
        
        $createpaybody  = array(
            'amount' => $amount, 
            'currency' => 'BDT', 
            'merchantInvoiceNumber' => $invoice, 
            'intent' => $intent
        );

        $response = $this->xhr->request('POST', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create', [
            'body' => '{"mode":"0000","payerReference":"Md Alim Khan","callbackURL":"http://test.com/","agreementID":"ID20402078","amount":"500","currency":"BDT","intent":"sale","merchantInvoiceNumber":"ID20402078"}',
            'headers' => [
              'Authorization' => $credentials['token'],
              'X-APP-Key' => '4f6o0cjiki2rfm34kfdadl1eqq',
              'accept' => 'application/json',
              'content-type' => 'application/json',
            ],
        ]);
          
        return $response->getBody();
    }

    public function executepayment()
    {
        session_start();

        /*$strJsonFileContents = file_get_contents("config.json");
        $array = json_decode($strJsonFileContents, true);*/

        $array = $this->_get_config_file();

        $paymentID = $_GET['paymentID'];
        $proxy = $array["proxy"];

        $url = curl_init($array["executeURL"] . $paymentID);

        $header = array(
            'Content-Type:application/json',
            'authorization:' . $array["token"],
            'x-app-key:' . $array["app_key"]
        );

        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        // curl_setopt($url, CURLOPT_PROXY, $proxy);

        $resultdatax = curl_exec($url);
        curl_close($url);

        $this->_updateOrderStatus($resultdatax);

        echo $resultdatax;
    }

    protected function _updateOrderStatus($resultdatax)
    {
        $resultdatax = json_decode($resultdatax);

        if ($resultdatax && $resultdatax->paymentID != null && $resultdatax->transactionStatus == 'Completed') {
            DB::table('orders')->where([
                'invoice' => $resultdatax->merchantInvoiceNumber
            ])->update([
                'status' => 'Processing', 'trxID' => $resultdatax->trxID
            ]);
        }
    }
}

$bkash = new Bkash;

echo '<pre>';
print_r(json_decode($bkash->token()->createpayment(),true));