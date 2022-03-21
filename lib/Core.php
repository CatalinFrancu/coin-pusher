<?php

class Core {

  static function autoloadLibClass($className) {
    $filename = __DIR__ . '/' . $className . '.php';
    if (file_exists($filename)) {
      require_once $filename;
    }
  }

  static function init() {
    mb_internal_encoding('UTF-8');

    spl_autoload_register(); // clear the autoload stack
    spl_autoload_register('Core::autoloadLibClass');
  }

  static function getPrices($limit = null) {
    $url = Config::get('api.cmcUrl');
    $parameters = [
      'start' => '1',
      'limit' => $limit ?: Config::get('global.csvCoins'),
      'convert' => 'USD'
    ];

    $headers = [
      'Accepts: application/json',
      sprintf('X-CMC_PRO_API_KEY: %s', Config::get('api.cmcKey')),
    ];
    $qs = http_build_query($parameters); // query string encode the parameters
    $request = "{$url}?{$qs}"; // create the request URL

    $curl = curl_init(); // Get cURL resource
    // Set cURL options
    curl_setopt_array($curl, array(
      CURLOPT_URL => $request,            // set the request URL
      CURLOPT_HTTPHEADER => $headers,     // set the headers
      CURLOPT_RETURNTRANSFER => 1         // ask for raw response instead of bool
    ));

    $response = curl_exec($curl); // Send the request, save the response
    $dec = json_decode($response);

    $result = [];
    foreach ($dec->data as $row) {
      $result[$row->symbol] = $row;
    }
    return $result;
  }

}

Core::init();
