<?php

require_once 'lib/Core.php';

// Downloads real-time coin prices, then checks balances of accounts defined in the [api] section
// of coin-pusher.conf. Reports the USD value of the aggregate balance. If a cache file exists,
// then uses cached balances and skips the API calls, unless you specify -f or --force.

// command-line arguments:
//   -f, --force      Force API calls even when cached balances exist
$opts = getopt('f', [ 'force' ]);
$force = isset($opts['f']) || isset($opts['force']);

$prices = getPrices();

$cacheFile = __DIR__ . '/' . Config::get('global.balanceCache');
if (file_exists($cacheFile) && !$force) {
  $balances = json_decode(file_get_contents($cacheFile), true);
} else {
  $balances = [];
  getBittrexBalance($balances);
  getBitfinexBalance($balances);
  getKrakenBalance($balances);
  file_put_contents($cacheFile, json_encode($balances));
}

// add explicit coin balances from the [coins] section of coin-pusher.conf
$explicit = Config::get('coins.coin', []);
foreach ($explicit as $coin) {
  list($amount,$symbol,$source) = explode(',', $coin);
  $balances[] = [
    'symbol' => $symbol,
    'amount' => $amount,
    'source' => $source,
  ];
}

// canonicalize symbol names
$canonical = Config::get('symbols.canonical');
foreach ($balances as $i => $row) {
  if (isset($canonical[$row['symbol']])) {
    $balances[$i]['symbol'] = $canonical[$row['symbol']];
  }
}

$sumUsd = 0;
$sumBtc = 0;
foreach ($balances as $row) {
  if (isset($prices[$row['symbol']])) {
    $p = $prices[$row['symbol']];
    $usdEquiv = $p->price_usd * $row['amount'];
    $btcEquiv = $p->price_btc * $row['amount'];
    if (abs($usdEquiv) >= Config::get('global.balanceThreshold')) {
      printf("[%s] %s %s, unit price = %0.2f USD / %0.8f BTC, total = %0.2f USD / %0.8f BTC\n",
             $row['source'],
             $row['amount'],
             $row['symbol'],
             $p->price_usd,
             $p->price_btc,
             $usdEquiv,
             $btcEquiv);
      $sumUsd += $usdEquiv;
      $sumBtc += $btcEquiv;
    }
  } else if (floatval($row['amount']) > 0.0) {
    printf("No price info for currency [%s]\n", $row['symbol']);
  }
}

printf("BALANCE: %0.2f USD / %0.8f BTC\n", $sumUsd, $sumBtc);

$growth = $sumUsd / Config::get('global.initialUsd') * 100; // in percents
printf("ITD GROWTH: %.2f%%\n", $growth);

$btcGrowth = $prices['BTC']->price_usd / Config::get('global.initialBtcPrice') * 100;
printf("ITD BITCOIN GROWTH: %.2f%%\n", $btcGrowth);
printf("Portfolio vs pure Bitcoin: %.2f%%\n", $growth / $btcGrowth * 100);

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
