<?php
require './vendor/autoload.php';

// Dotenv to load API keys
Dotenv::load(__DIR__);
Dotenv::required(['OAUTH_CONSUMER_KEY', 'OAUTH_CONSUMER_SECRET', 'OAUTH_REALM', 'OPENX_EMAIL', 'OPENX_PASSWORD', 'OPENX_URL']);

// Use OpenX client
$client = new Vlucas\OpenX($_ENV['OAUTH_CONSUMER_KEY'], $_ENV['OAUTH_CONSUMER_SECRET'], $_ENV['OAUTH_REALM'], $_ENV['OPENX_URL']);
$client->login($_ENV['OPENX_EMAIL'], $_ENV['OPENX_PASSWORD']);

// GET /account - for list of accounts
$res = $client->get('account');
var_dump($res->json());

