OpenX oAuth API Client
======================

Uses [Guzzle v4.x](https://github.com/guzzle/guzzle/tree/4.2.3) and the
[oauth-subscriber plugin](https://github.com/guzzle/oauth-subscriber).

## Installation

```
php composer.phar require vlucas/openx-oauth-client
```

## Usage in your code:

```php
// Setup client and login with user
$client = new Vlucas\OpenX($consumerKey, $consumerSecret, $oauthRealm, 'http://ox-ui.example.com/ox/4.0/');
$client->login('user@example.com', 'souper-seekret-password');

// GET /account - for list of accounts
$res = $client->get('account');
var_dump($res->json());
```

You should see the JSON dumped out for the accounts endpoint. Feel free to make
any other requests you want.

Be sure to [**read the OpenX API v4 Documentation**](http://docs.openx.com/api/)!

### Making HTTP Requests

The OpenX client proxies all normal get/post/put/delete, etc. requests through
and automatically adds the required `Cookie` header before sending the request.

```php
// Makes normal request with necessary Cookie header
$res = $client->get('account');
```

### Access to the Guzzle Client

If you need to get the base Guzzle object to make any further requests or
modifications, you can:

```php
// Returns the main GuzzleHttp\Client object
$guzzle = $client->getClient();
```

Please note that if you do this, the required `Cookie` header will not be
attached to your requests automatically, so you will need to do this yourself
with `$client->getAuthCookie()`.

## Using the Example
Steps to run the provided example.php:

1. `composer install --dev`
2. `cp .env.example .env`
3. Edit `.env` to add oAuth consumer key and secret, user/pass, etc.
4. Run it: `php example.php`

