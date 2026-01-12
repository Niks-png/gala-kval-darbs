<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

$rssUrl = "https://www.skysports.com/rss/12040"; 
$cacheFile = __DIR__ . "/cache/premier_league_rss.json";

if (!is_dir(__DIR__ . "/cache")) {
    mkdir(__DIR__ . "/cache", 0755, true);
}

$rss = simplexml_load_file($rssUrl);
if (!$rss) {
    die(" Failed to load RSS feed");
}

$items = [];

foreach ($rss->channel->item as $item) {
    preg_match('/
<img.*?src=["\'](.*?)["\']/', (string)$item->description, $matches);
    $img = $matches[1] ?? "images/default.png"; 

    $items[] = [
        "title"   => (string)$item->title,
        "link"    => (string)$item->link,
        "pubDate" => (string)$item->pubDate,
        "image"   => $img
    ];
}

file_put_contents($cacheFile, json_encode(["items" => $items], JSON_PRETTY_PRINT));
