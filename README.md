# coin-pusher

This is a collection of tools I wrote to help me build, balance and track a portfolio of digital coins. They work for me, but that's about it. :-) In particular, there is no graceful handling of errors -- after all, an Exception is graceful enough! The code is commented decently and you should be able to tweak it to suit your needs.

## Instalation

simply copy `coin-pusher.conf.sample` to `coin-pusher.conf` and edit to taste.

## Components

### Historical analysis

I recently came across an [article discussing index funds for digital currencies](http://www.coindesk.com/cryptocurrency-index-funds-simulations-surprising-results/). which I found very interesting. I wanted to be able to crunch my own numbers, so I wrote these scripts:

#### history-downloader.php

This script downloads the weekly stats from [coinmarketcap.com](https://coinmarketcap.com/historical/). The files are saved in raw (HTML) format in `history/raw`. As a side note, CoinMarketCap are awesome! I even made a donation.
