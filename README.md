# coin-pusher

This is a collection of tools I wrote to help me build, balance and track a portfolio of digital coins. They work for me, but that's about it. :-) In particular, there is no graceful handling of errors -- after all, an Exception is graceful enough! The code is commented decently and you should be able to tweak it to suit your needs.

## Instalation

Simply copy `coin-pusher.conf.sample` to `coin-pusher.conf` and edit to taste.

## Components

### Historical analysis

I recently came across an [article discussing index funds for digital currencies](http://www.coindesk.com/cryptocurrency-index-funds-simulations-surprising-results/), which I found very interesting. I wanted to be able to crunch my own numbers regarding past behavior of the digital coin market, so I wrote these scripts:

#### history-downloader.php

This script downloads the weekly stats from [coinmarketcap.com](https://coinmarketcap.com/historical/). The files are saved in raw (HTML) format in `history/raw`. As a side note, CoinMarketCap are awesome! I even made a donation.

#### history-parser.php

This script parses the HTML files downloaded by `history-downloader.php`. For each week, it extracts all the coin symbols, market caps and share prices. It stores this information in `history/history.json`. A copy of `history.json` as of June 2017 is included in the repo. If you need more recent data, please rerun `history-downloader.php` and `history-parser.php` in this order.

#### index-fund-simulator.php

This script simulates the performance of an index fund of digital coins with (near-)monthly rebalancing.

There are constants in the script to configure:

1. The time frame. Historical data must be downloaded and parsed in advance for the specified time frame.
2. The maximum index size (maximum number of coins the script should consider keeping in the portfolio).

The simulator considers three allocation strategies:
 
1. Proportional to each coin's market cap.
2. Proportional to the square root of the market cap.
3. Proportional to the market cap to the power 3/4.

The simulator cycles through all the index sizes and allocation strategies. For each combination, it invests $1, rebalances the portfolio monthly and reports the final amount.

The rebalancing doesn't happen on the same day every month, because we only have weekly snapshots of the historical data. The simulator selects data sets from the same week of every month (13-19 by default).

### Portfolio tracker

If you have wallets on several exchanges, keeping track of the total wealth becomes hard. The script `balances.php` connects to all these wallets (after you set up API access) and gives you a balance sheet of every coin you have, plus equivalent totals in USD and Bitcoin.

Currently supported are [Kraken](https://www.kraken.com/), [Bittrex](https://bittrex.com/) and [Bitfinex](https://www.bitfinex.com/). If you use other exchanges, patches to integrate with them are kindly appreciated. You can also enumerate coin balances explicitly in the `[coins]` section of `coin-pusher.conf` (for personal wallets or for sites that don't offer a wallet API).

To use it:

1. Set up API keys on the respective sites.
2. Add the API key and secret in the appropriate place in `coin-pusher.conf`.
3. Enumerate coin balances explicitly.
3. Run `balances.php`.

By default, `balances.php` caches account balances in `cache/balances.json`. This helps speed up the script. You can force a refresh by adding the command line argument `-f` or `--force` (or by deleting the cache file).

### Portfolio builder spreadsheet

`docs/portfolio-tracker.ods` is a simple helper spreadsheet you can use to build your own index fund. Pick the coins you like, figure out the desired allocation, plug in the amount of fiat coins you want to invest, and the spreadsheet will tell you how many shares of each digital coins to buy.

Additional details are included in the spreadsheet itself.
