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
  $header = getHeader($doc);
  assert($header[2]->textContent == 'Symbol');
  assert($header[3]->textContent == 'Market Cap');
  assert($header[4]->textContent == 'Price');

  $data[$date] = [];

  // parse data rows
  $rows = getRows($doc);
  foreach ($rows as $row) {
    $cells = $row->getElementsByTagName('td');
    $symbol = trim($cells[2]->textContent);
    $marketCap = str_replace([' ', '$', ','], '', trim($cells[3]->textContent));
    $price = str_replace([' ', '$', ','], '', trim($cells[4]->textContent));

    if ($marketCap != '?') {
      // print "  * {$symbol} | market cap = {$marketCap} | price = {$price}\n";
      $data[$date][] = [ $symbol, $marketCap, $price ];
    }
  }
}

file_put_contents(JSON_FILE, json_encode($data, JSON_PRETTY_PRINT));

/*************************************************************************/

function getHeader($doc) {
  $elem = $doc->getElementById('currencies-all');
  if ($elem) {
    return $elem
      ->getElementsByTagName('thead')[0]
      ->getElementsByTagName('th');
  } else {
    return
      $doc->getElementsByTagName('table')[2]
      ->getElementsByTagName('thead')[0]
      ->getElementsByTagName('th');
  }
}

function getRows($doc) {
  $elem = $doc->getElementById('currencies-all');
  if ($elem) {
    return $elem
      ->getElementsByTagName('tbody')[0]
      ->getElementsByTagName('tr');
  } else {
    return
      $doc->getElementsByTagName('table')[2]
      ->getElementsByTagName('tbody')[0]
      ->getElementsByTagName('tr');
  }
}
