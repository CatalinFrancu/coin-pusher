<?php

require_once 'lib/Core.php';

$SYMBOL_SYNONYMS = [
  'IOT' => 'MIOTA',
  'XETH' => 'ETH',
  'XLTC' => 'LTC',
  'XXBT' => 'BTC',
  'XXRP' => 'XRP',
];

$prices = getPrices();

$balances = [];
getBittrexBalance($balances);
getBitfinexBalance($balances);
getKrakenBalance($balances);

// canonicalize symbol names
foreach ($balances as $i => $row) {
  if (isset($SYMBOL_SYNONYMS[$row['symbol']])) {
    $balances[$i]['symbol'] = $SYMBOL_SYNONYMS[$row['symbol']];
  }
}

$sumUsd = 0;
$sumBtc = 0;
foreach ($balances as $row) {
  if (isset($prices[$row['symbol']])) {
    $p = $prices[$row['symbol']];
    printf("[%s] %s %s, unit price = %0.2f USD / %0.8f BTC, total = %0.2f USD / %0.8f BTC\n",
           $row['source'],
           $row['amount'],
           $row['symbol'],
           $p->price_usd,
           $p->price_btc,
           $p->price_usd * $row['amount'],
           $p->price_btc * $row['amount']);
    $sumUsd += $p->price_usd * $row['amount'];
    $sumBtc += $p->price_btc * $row['amount'];
  } else if (floatval($row['amount']) > 0.0) {
    printf("No price info for currency [%s]\n", $row['symbol']);
  }
}

printf("BALANCE: %0.2f USD / %0.8f BTC\n", $sumUsd, $sumBtc);

/*************************************************************************/

function getPrices() {
  $json = file_get_contents(Config::get('api.pricesUrl'));
  $data = json_decode($json);
  $result = [];

  foreach ($data as $row) {
    $result[$row->symbol] = $row;
  }

  return $result;
}

function getBittrexBalance(&$balances) {
  $key = Config::get('api.bittrexKey');
  $secret = Config::get('api.bittrexSecret');
  $urlPattern = Config::get('api.bittrexUrl');
  if (!$key || !$secret || !$urlPattern) {
    return;
  }

  $nonce = time();
  $uri = sprintf($urlPattern, $key, $nonce);
  $sign = hash_hmac('sha512', $uri, $secret);
  $ch = curl_init($uri);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['apisign:' . $sign]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $execResult = curl_exec($ch);
  $data = json_decode($execResult);

  foreach ($data->result as $row) {
    $balances[] = [
      'symbol' => $row->Currency,
      'amount' => $row->Balance,
      'source' => 'bittrex',
    ];
  }
}

function getBitfinexBalance(&$balances) {
  $key = Config::get('api.bitfinexKey');
  $secret = Config::get('api.bitfinexSecret');
  $url = Config::get('api.bitfinexUrl');
  if (!$key || !$secret || !$url) {
    return;
  }
  
  $data = [
    'request' => '/v1/balances',
    'nonce' => strval(round(microtime(true) * 10, 0)),
  ];
  $payload = base64_encode(json_encode($data));
  $signature = hash_hmac("sha384", $payload, $secret);
  $headers = [
    "X-BFX-APIKEY: " . $key,
    "X-BFX-PAYLOAD: " . $payload,
    "X-BFX-SIGNATURE: " . $signature,
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_POSTFIELDS => '',
  ]);
  $execResult = curl_exec($ch);
  $data = json_decode($execResult);

  foreach($data as $row) {
    $balances[] = [
      'symbol' => strtoupper($row->currency),
      'amount' => $row->amount,
      'source' => 'bitfinex',
    ];
  }
}

function getKrakenBalance(&$balances) {
  $key = Config::get('api.krakenKey');
  $secret = Config::get('api.krakenSecret');
  $url = Config::get('api.krakenUrl');
  if (!$key || !$secret || !$url) {
    return;
  }

  $nonce = explode(' ', microtime());
  $request = [
    'nonce' => $nonce[1] . str_pad(substr($nonce[0], 2, 6), 6, '0'),
  ];

  // build the POST data string
  $postdata = http_build_query($request, '', '&');

  // set API key and sign the message
  $path = '/0/private/Balance';
  $sign = hash_hmac('sha512', $path . hash('sha256', $request['nonce'] . $postdata, true),
                    base64_decode($secret),
                    true);
  $headers = [
    'API-Key: ' . $key,
    'API-Sign: ' . base64_encode($sign),
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $postdata,
  ]);

  $execResult = curl_exec($ch);
  $data = json_decode($execResult, true);

  foreach ($data['result'] as $symbol => $amount) {
    $balances[] = [
      'symbol' => $symbol,
      'amount' => $amount,
      'source' => 'kraken',
    ];
  }
}
