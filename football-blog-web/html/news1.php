<?php
header('Content-Type: application/json');

$rss_url = "http://feeds.bbci.co.uk/sport/football/rss.xml";

$xml = @simplexml_load_file($rss_url);

if ($xml === FALSE) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch news']);
    exit;
}

$articles = [];

foreach ($xml->channel->item as $item) {
    $namespaces = $item->getNamespaces(true);
    $media = $item->children($namespaces['media']);
    $image = isset($media->thumbnail) ? (string)$media->thumbnail->attributes()->url : "https://via.placeholder.com/200";

    $articles[] = [
        'title' => (string)$item->title,
        'link' => (string)$item->link,
        'description' => (string)$item->description,
        'image' => $image
    ];

    if (count($articles) >= 10) break;
}

echo json_encode($articles);
exit;
?>