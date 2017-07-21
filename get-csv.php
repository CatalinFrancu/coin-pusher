<?php

// Grabs current data from coinmarketcap.com and dumps three columns in CSV format:
// 1. symbol
// 2. market cap
// 3. price per share (USD)

require_once 'lib/Core.php';

$numCoins = Config::get('global.csvCoins');

$json = file_get_contents(Config::get('api.pricesUrl'));
$data = json_decode($json);

for ($i = 0; $i < $numCoins; $i++) {
  $rec = $data[$i];
  $row = [ $rec->symbol, $rec->market_cap_usd, $rec->price_usd ];
  fputcsv(STDOUT, $row);
}
