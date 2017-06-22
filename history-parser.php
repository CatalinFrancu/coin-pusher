<?php

// Parses downloaded historical data and outputs a JSON file of useful fields

define('HISTORY_FILES', 'history/raw/*.html');
define('JSON_FILE', 'history/history.json');

$data = [];

foreach (glob(HISTORY_FILES) as $file) {
  // extract the date
  preg_match('/\d\d\d\d-\d\d-\d\d/', $file, $matches);
  $date = $matches[0];

  print "Parsing $file for date $date\n";

  // load the HTML file
  $doc = new DOMDocument();
  @$doc->loadHTMLFile($file);

  // verify table headers
  $header = $doc
          ->getElementById('currencies-all')
          ->getElementsByTagName('thead')[0]
          ->getElementsByTagName('th');
  assert($header[2]->textContent == 'Symbol');
  assert($header[3]->textContent == 'Market Cap');
  assert($header[4]->textContent == 'Price');

  $data[$date] = [];

  // parse data rows
  $rows = $doc
        ->getElementById('currencies-all')
        ->getElementsByTagName('tbody')[0]
        ->getElementsByTagName('tr');
  foreach ($rows as $row) {
    $cells = $row->getElementsByTagName('td');
    $symbol = trim($cells[2]->textContent);
    $marketCap = str_replace([' ', '$', ','], '', trim($cells[3]->textContent));
    $price = str_replace([' ', '$', ','], '', trim($cells[4]->textContent));

    if ($marketCap != '?') {
      print "  * {$symbol} | market cap = {$marketCap} | price = {$price}\n";
      $data[$date][] = [ $symbol, $marketCap, $price ];
    }
  }
}

file_put_contents(JSON_FILE, json_encode($data, JSON_PRETTY_PRINT));
