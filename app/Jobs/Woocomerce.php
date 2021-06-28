<?php

namespace App\Jobs;

use Automattic\WooCommerce\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class Woocomerce implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $woocommerce;
    private $dev_name;
    private $cert_name;
    private $token;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        

        $this->woocommerce = new Client(
            'https://wp-test.mydessk.com', 
            'ck_2e24ae3e0ba2768adc7526bd8ee44e510d6b4662', 
            'cs_d43240bf57e0188c8ed7e7884357abec75f176d0',
            [
                'version' => 'wc/v3',
                'timeout' => 100
            ]
        ); 


        $this->dev_name = env('BONANZA_DEV_NAME');
        $this->cert_name = env('BONANZA_CERT_NAME');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $this->token = $this->getToken();

        Log::info("inicio - ".$this->token);

        for ($i=1; $i < 6; $i++) { 

           $items = collect($this->getItems($i,500));


           $products = $items->map(function($item){

                $data = [
                    
                    'name' => $item['title'],
                    'description' => strip_tags($item['description'],'<ul><li><p><a>'),
                    'sku' => ($item['sku'] == '') ? $item['title'] : $item['sku'].'-'.$item['title'] ,
                    'regular_price' => $item['buyItNowPrice'],
                    'sale_price' => $item['currentPrice'],
                    'manage_stock' => true,
                    'stock_quantity' => $item['quantity'],
                    'images' => collect($item['pictureURL'])->map(function($image){return ['src' => $image];})->all(),
                ];

               
               /**

                $data = [
                    'sku' => ($item['sku'] == '') ? $item['title'] : $item['sku'].'-'.$item['title'] ,
                    'post_title' => $item['title'],
                    'tax:product_cat' => implode('>', explode(' >> ', strip_tags($item['primaryCategory']['categoryName'])))
                ];

                **/

                return $data;


            });


           foreach ($products as $key => $product) {
               $wc_product = $this->woocommerce->get('products?sku='.urlencode($product['sku']));

               if (isset($wc_product[0])) {
                   $wc_product = $wc_product[0];

                   try {
                        Log::info("enviando prodcuto",["product" => $wc_product->id]);
                       $this->woocommerce->put('products/'.$wc_product->id, $product);
                       Log::info("producto actualizado",["product" => $wc_product->id]);
                   
                   } catch (Exception $e) {
                       Log::info("fallo del cliente");
                   }
               }

               


               
           }

           return true;


           $fileName = 'products-page-'.$i.'-'.count($products).'.json';

            $fp = fopen($fileName, 'w');
            fwrite($fp, json_encode($products));
            fclose($fp);


            /**


           foreach ($products as $key => $product) {
               $wc_product = $this->woocommerce->get('products?sku='.urlencode($product['sku']));

               if (isset($wc_product[0])) {
                   $wc_product = $wc_product[0];

                   try {
                       $this->woocommerce->put('products/'.$wc_product->id, $product);
                       Log::info("producto actualizado",["product" => $wc_product->id]);
                   
                   } catch (Exception $e) {
                       Log::info("fallo del cliente");
                   }
               }

               


               
           }

           **/

           

        }
    }



    public function getItems($page = 1,$per_page = 5)
    {
        $url = "https://api.bonanza.com/api_requests/secure_request";
        $headers = array("X-BONANZLE-API-DEV-NAME: " . $this->dev_name, "X-BONANZLE-API-CERT-NAME: " . $this->cert_name);
        $args = array( 'userId' => 'GoldenGateEmporium', 'itemsPerPage' => $per_page, 'page' => $page);
        $args['requesterCredentials']['bonanzleAuthToken'] = $this->token;   // only necessary if specifying an itemStatus other than "for_sale"
        $request_name = "getBoothItemsRequest";


        Log::info("Get items");
        $post_fields = "$request_name=" . json_encode($args) . " \n";
        
        $connection = curl_init($url);
        $curl_options = array(CURLOPT_HTTPHEADER=>$headers, CURLOPT_POSTFIELDS=>$post_fields,
                        CURLOPT_POST=>1, CURLOPT_RETURNTRANSFER=>1);  // data will be returned as a string
        curl_setopt_array($connection, $curl_options);
        $json_response = curl_exec($connection);
        if (curl_errno($connection) > 0) {
          echo curl_error($connection) . "\n";
          exit(2);
        }
        curl_close($connection);
        $response = json_decode($json_response,true);
        
        return $response['getBoothItemsResponse']['items'];

    }


    public function getToken()
    {
        
        $url = "https://api.bonanza.com/api_requests/secure_request";
        $headers = array("X-BONANZLE-API-DEV-NAME: " . $this->dev_name, "X-BONANZLE-API-CERT-NAME: " . $this->cert_name);
        $args = array();
        $post_fields = "fetchTokenRequest";
        $connection = curl_init($url);
        $curl_options = array(CURLOPT_HTTPHEADER=>$headers, CURLOPT_POSTFIELDS=>$post_fields,
                        CURLOPT_POST=>1, CURLOPT_RETURNTRANSFER=>1);  # data will be returned as a string
        curl_setopt_array($connection, $curl_options);
        $json_response = curl_exec($connection);
        if (curl_errno($connection) > 0) {
          echo curl_error($connection) . "\n";
          exit(2);
        }
        curl_close($connection);
        $response = json_decode($json_response,true);
        $token = $response['fetchTokenResponse']['authToken'];

        return $token;


    }
}
