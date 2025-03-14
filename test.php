<?php
require_once 'vendor/autoload.php';

use NklKst\TheSportsDb\Client\ClientFactory;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$apiKey=$_ENV['API_KEY'];


$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, "/api/v2/json/team/133604");
//curl_setopt($curl, CURLOPT_URL, "https://www.thesportsdb.com/api/v2/json/livescore/soccer");
curl_setopt($curl, CURLOPT_HTTPGET, 1);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, array('X-API-KEY:' . $apiKey));
$result = curl_exec($curl);
$json = json_decode($result);
var_dump($json);
die;

// Create a client
$client = ClientFactory::create();
$client->configure()->setRateLimiter();
$client->configure()->setKey($apiKey);


// Get soccer livescores
$livescores = $client->livescore()->now('Soccer');
echo $livescores[0]->strProgress;

// Get video highlights
$highlights = $client->highlight()->latest();
echo $highlights[0]->strVideo;

// Get next events for Liverpool FC
$events = $client->schedule()->teamNext(133602);
echo $events[0]->strEvent;