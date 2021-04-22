<?php

// Grabs current data from coinmarketcap.com and dumps three columns in CSV format:
// 1. symbol
// 2. market cap
// 3. price per share (USD)

require_once 'lib/Core.php';

$data = Core::getPrices();

foreach ($data as $rec) {
  $row = [
    $rec->symbol,
    $rec->quote->USD->market_cap,
    $rec->quote->USD->price,
  ];
  fputcsv(STDOUT, $row);
}
