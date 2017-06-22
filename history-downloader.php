<?php

// Downloads historical data from https://coinmarketcap.com/historical/

// beginning of historical data
define('BIG_BANG', '2013-04-28');

define('HIST_URL', 'https://coinmarketcap.com/historical/%s/');

define('DOWNLOAD_DIR', 'history/raw/');

// delay between consecutive page downloads -- please be considerate!
define('DELAY', 2);

$date = BIG_BANG;
$today = date('Y-m-d');
@mkdir(DOWNLOAD_DIR, 0755, true);

while ($date < $today) {
  download($date);
  $date = addWeek($date);
}

/*************************************************************************/

function getUrl($date) {
  return sprintf(HIST_URL, str_replace('-', '', $date));
}

function addWeek($date) {
  $d = DateTime::createFromFormat('Y-m-d', $date);
  $d->modify('+1 week');
  return $d->format('Y-m-d');
}

function download($date) {
  $file = sprintf('%s%s.html', DOWNLOAD_DIR, $date);
  if (file_exists($file)) {
    return;
  }

  $url = getUrl($date);
  printf("downloading $url to $file\n");
  file_put_contents($file, file_get_contents($url));
  sleep(2);
}
