<?php

require_once 'vendor/autoload.php';

use PHPHtmlParser\Dom;

$url = $_SERVER['REQUEST_URI'] ?? null;

if (empty($url)) {
    echo 'No URL provided';
    exit;
}

function escape(string $string): string
{
    return str_replace(['&'], ['&amp;'], $string);
}

function fixMediaTags(string $xml): string
{
    return str_replace([
        'media_group',
        'media_title',
        'media_thumbnail',
        'media_description',
        'xmlns_media'
    ], [
        'media:group',
        'media:title',
        'media:thumbnail',
        'media:description',
        'xmlns:media'
    ], $xml);
}

$baseUrl = 'https://rumble.com';

$text = file_get_contents("{$baseUrl}{$url}");

$dom = new Dom;

$dom->loadStr($text);
$listings = $dom->find('.video-listing-entry');

$channelUrl = $dom->find('[rel=canonical]')->getAttribute('href');
$channelName = $dom->find('.listing-header--thumb')->getAttribute('alt');
$channelImg = $dom->find('.listing-header--thumb')->getAttribute('src');

/* $xml = new SimpleXMLElement('<xml version="1.0" encoding="UTF-8"></xml>'); */
/* $feed = $xml->addChild('feed'); */
$feed = new SimpleXMLElement('<feed/>');

$feed->addAttribute('xmlns_media', 'http://search.yahoo.com/mrss/');
$feed->addAttribute('xmlns', 'http://www.w3.org/2005/Atom');
$feed->addAttribute('xml:lang', 'en-US');

$feed->addChild('id', str_replace(['https://', '/', '.'], ['', ':', '_'], $channelUrl));

$feed->addChild('title', escape($channelName));

$link = $feed->addChild('link');
$link->addAttribute('rel', 'self');
$link->addAttribute('href', $channelUrl);

$author = $feed->addChild('author');
$author->addChild('name', escape($channelName));
$author->addChild('uri', $channelUrl);

$image = $feed->addChild('image');
$image->addChild('url', $channelImg);
$image->addChild('title', escape($channelName));

$imageLink = $image->addChild('link');
$imageLink->addAttribute('rel', 'self');
$imageLink->addAttribute('href', $channelImg);


foreach ($listings as $listing) {
    $listingImg = $listing->find('.video-item--img')[0];

    $videoImg = $listingImg->getAttribute('src');
    $videoTitle = $listingImg->getAttribute('alt');

    $videoDateTime = $listing->find('.video-item--time')->getAttribute('datetime');
    $videoUrl = $baseUrl . $listing->find('.video-item--a')->getAttribute('href');

    $entry = $feed->addChild('entry');
    $entry->addChild('id', str_replace(['https://', '/', '.'], ['', ':', '_'], $videoUrl));
    $entry->addChild('title', escape($videoTitle));
    $entry->addChild('published', $videoDateTime);

    $author = $entry->addChild('author');
    $author->addChild('name', escape($channelName));
    $author->addChild('uri', $channelUrl);

    $entryLink = $entry->addChild('link');
    $entryLink->addAttribute('rel', 'alternate');
    $entryLink->addAttribute('href', $videoUrl);

    $mediaGroup = $entry->addChild('media_group');
    $mediaGroup->addChild('media_title', escape($videoTitle));

    $mediaGroup->addChild('media_description', escape($videoTitle));

    $mediaThumbnail = $mediaGroup->addChild('media_thumbnail');
    $mediaThumbnail->addAttribute('url', $videoImg);
    $mediaThumbnail->addAttribute('width', '420');
    $mediaThumbnail->addAttribute('height', '270');
    $entryContent = $entry->addChild('content');
    $entryContent->addAttribute('type', 'xhtml');

    $contentDiv = $entryContent->addChild('div');
    $contentDiv->addAttribute('xmlns', 'http://www.w3.org/1999/xhtml');
    $contentA = $contentDiv->addChild('a');
    $contentA->addAttribute('href', $videoUrl);
    $contentImg = $contentA->addChild('img');
    $contentImg->addAttribute('src', $videoImg);
}

header('Content-Type: application/xml');
echo fixMediaTags($feed->asXML());
