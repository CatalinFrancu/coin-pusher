<?php

/**
 * Simulates the performance of an index fund of digital coins.
 * Rebalances the portfolio monthly.
 *
 * Configurable parameters:
 * 1. Time frame. Historical data must be downloaded and parsed in advance
 *    for the specified time frame.
 * 2. Maximum index size (number of coins to keep in the portfolio).
 *
 * Included allocation strategies:
 * 1. Proportional to each coin's market cap.
 * 2. Proportional to the square root of the market cap.
 * 3. Proportional to the market cap to the power 3/4.
 *
 * The simulator cycles through all the index sizes and allocation strategies.
 * For each combination, it invests $1, rebalances the portfolio monthly and reports the final
 * amount.
 *
 * The rebalancing doesn't happen on the same day every month, because we only have
 * weekly snapshots of the historical data. The simulator selects data sets from the same
 * week of every month (13-19 by default).
 **/

class Simulator {

  const HISTORY_FILE = 'history/history.json';

  // time frame for the simulation
  const FIRST_MONTH = '2013-10';
  const LAST_MONTH = '2017-06';

  // for monthly rebalancing, look for a data set within this calendar week
  const WEEK_START = 13;
  const WEEK_END = 19;

  const MAX_INDEX_SIZE = 50;

  // strategies for computing the index allocation
  const S_MARKET_CAP = 0;
  const S_ROOT_MARKET_CAP = 1;
  const S_MARKET_CAP_POW_34 = 2;

  static $STRATEGY_NAMES = [
    self::S_MARKET_CAP => 'market cap',
    self::S_ROOT_MARKET_CAP => 'square root of market cap',
    self::S_MARKET_CAP_POW_34 => 'market cap^(3/4)',
  ];

  // array of [YYYY-MM] => array of [SYMBOL] => [ marketCap, price per coin ]
  public $stats;

  function __construct() {
    // load the full history and keep the monthly stats we need
    $history = json_decode(file_get_contents(self::HISTORY_FILE), true);
    $this->stats = [];

    foreach ($history as $date => $weeklyData) {
      list($month, $day) = $this->dateSplit($date);
      if (($month >= self::FIRST_MONTH) &&
          ($month <= self::LAST_MONTH) &&
          ($day >= self::WEEK_START) &&
          ($day <= self::WEEK_END)) {

        // index the coin data by symbol
        $data = [];
        foreach ($weeklyData as $rec) {
          $data[$rec[0]] = [
            'marketCap' => $rec[1],
            'price' => $rec[2],
          ];
        }
        $this->stats[$month] = $data;
      }
    }
  }

  function run() {
    // evaluate the various strategies and index sizes
    foreach (self::$STRATEGY_NAMES as $strategy => $strategyName) {
      print "Evaluating strategy '{$strategyName}'\n";
      for ($indexSize = 1; $indexSize <= self::MAX_INDEX_SIZE; $indexSize++) {
        $this->evaluateStrategy($strategy, $indexSize);
      }
    }
  }

  function dateSplit($date) {
    return [
      substr($date, 0, 7),
      substr($date, 8),
    ];
  }

  function evaluateStrategy($strategy, $indexSize) {
    // build a first portfolio of $1
    $index = $this->getIndexAllocation(self::FIRST_MONTH, $strategy, $indexSize);
    $portfolio = $this->getPortfolio(self::FIRST_MONTH, 1.0, $index);

    foreach ($this->stats as $month => $data) {
      if ($month != self::FIRST_MONTH) {
        // cash out completely...
        $value = $this->getPortfolioValue($portfolio, $month);
        // ... and repurchase
        $index = $this->getIndexAllocation($month, $strategy, $indexSize);
        $portfolio = $this->getPortfolio($month, $value, $index);
      }
    }
    printf("\tindex size: %d coins => portfolio value: $%0.2f\n", $indexSize, $value);
  }

  // returns a list of proportions for the symbols in a month
  function getIndexAllocation($month, $strategy, $indexSize) {
    $allSymbols = array_keys($this->stats[$month]);
    $symbols = array_slice($allSymbols, 0, $indexSize);

    // strategy: by market cap
    $mcSum = 0;
    foreach ($symbols as $symbol) {
      $mcSum += $this->getCoefficient($month, $symbol, $strategy);
    }

    $result = [];
    foreach ($symbols as $symbol) {
      $result[$symbol] = $this->getCoefficient($month, $symbol, $strategy) / $mcSum;
    }

    return $result;
  }

  // returns a coefficient a coin should have in the index, given the strategy
  function getCoefficient($month, $symbol, $strategy) {
    $cap = $this->stats[$month][$symbol]['marketCap'];
    switch ($strategy) {
      case self::S_MARKET_CAP: return $cap;
      case self::S_ROOT_MARKET_CAP: return sqrt($cap);
      case self::S_MARKET_CAP_POW_34: return pow($cap, 0.75);
      default: die(sprintf("undefined strategy '%s'\n", self::$STRATEGY_NAMES[$strategy]));
    }
  }

  // returns a portfolio of shares in each symbol given the index allocation for a month
  function getPortfolio($month, $sum, $index) {
    $result = [];
    foreach ($index as $symbol => $proportion) {
      $result[$symbol] = [
        'proportion' => $proportion,
        'sum' => $sum * $proportion,
        'shares' => $sum * $proportion / $this->stats[$month][$symbol]['price'],
      ];
    }

    return $result;
  }

  function getPortfolioValue($portfolio, $month) {
    $result = 0.0;
    foreach ($portfolio as $symbol => $data) {
      if (isset($this->stats[$month][$symbol])) {
        $result += $data['shares'] * $this->stats[$month][$symbol]['price'];
      }
    }

    return $result;
  }
}

$s = new Simulator();
$s->run();
