<?php

/**
 * Usage:
 * Export your trade history file from Binance (TradeHistory.xlsx) and call this script (synchronous.php) adjacent to that file's (TradeHistory.xlsx) location
 *
 * ➜  Downloads ls
 *  TradeHistory.xlsx
 *  ➜  Downloads php ~/Documents/coinprofits/synchronous.php
 *  [*] Binance coinprofits
 *  [*] Processing 23 trades found in TradeHistory.xlsx
 *  [*] Total profits at current cryptocurrency prices: $2167.66 USD
 * 
 *  @TODO:
 * 
 * 1) Cryptocompare API is _too_fucking_slow_ - queue and asynchronously batch the requests (per historical/current price evaluations)
 * 2) If you're going through that effort, might as well re-structure this to be reusable and, specifically, 
 * write everything interface-first to allow for easy interoperability of filetype/format readers, evaluation API's, etc.
 */

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

echo "[*] Binance coinprofits \n";

$inputFileName = 'TradeHistory.xlsx';
$spreadsheet = IOFactory::load($inputFileName);
$sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

echo "[*] Processing " . count($sheetData) . " trades found in {$inputFileName}\n";

foreach ($sheetData as $key => $trade) {
    if ($key == 1) { // Ignore title row
        continue;
    }
    
    $timestamp = strtotime($trade['A']);
    list($tsym, $fsym) = getSymbolsFromPair($trade['B']);
    $transactionType = $trade['C'];
    $toStock = $trade['E'];
    $fromStock = $trade['F'];
    $feeStock = $trade['G'];
    $feeCoin = $trade['H'];

    $wealthTransfer = $fromStock * getHistoricalPrice($fsym, $timestamp);

    $currencies[$fsym]['wealth_transfer'] += $transactionType == 'BUY' ? $wealthTransfer : -$wealthTransfer;
    $currencies[$fsym]['stock'] += $transactionType == 'BUY' ? -$fromStock : $fromStock;

    $currencies[$tsym]['wealth_transfer'] += $transactionType == 'SELL' ? $wealthTransfer : -$wealthTransfer;
    $currencies[$tsym]['stock'] += $transactionType == 'SELL' ? -$toStock : $toStock;

    $currencies[$feeCoin]['stock'] -= $feeStock;
}

foreach ($currencies as $symbol => $currency) {
    $profit += $currency['wealth_transfer'];
    $profit += getCurrentPrice($symbol) * $currency['stock'];
}

echo "[*] Total profits at current cryptocurrency prices: $" . round($profit, 2) . " USD\n";

function getSymbolsFromPair($pair)
{
    $fsyms = ['BTC', 'ETH', 'USDT', 'BNB'];

    foreach ($fsyms as $fsym) {
        if (substr($pair, -strlen($fsym), strlen($fsym)) == $fsym) {
            $tsym = str_replace($fsym, '', $pair);
            return [$tsym, $fsym];
        }
    }
}

function getHistoricalPrice($fsym, $timestamp, $tsym = 'USD')
{
    $api = 'https://min-api.cryptocompare.com';
    $endpoint = '/data/pricehistorical?fsym=' . $fsym . '&tsyms=' . $tsym . '&ts=' . $timestamp;
    $json = json_decode(file_get_contents($api . $endpoint), true);

    return $json[$fsym][$tsym];
}

function getCurrentPrice($fsym, $tsym = 'USD')
{
    $api = 'https://min-api.cryptocompare.com';
    $endpoint = '/data/price?fsym=' . $fsym . '&tsyms=' . $tsym;
    $json = json_decode(file_get_contents($api . $endpoint), true);

    return $json[$tsym];
}
