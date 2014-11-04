<?php
namespace Vlucas;

use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

/**
 * Helper class for connecting to an OpenX v4 oAuth API
 *
 * Lots of thanks to: http://www.sitepoint.com/using-guzzle-twitter-via-oauth/
 */
class OpenX
{
    /**
     * @var GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var GuzzleHttp\Subscriber\Oauth\Oauth1
     */
    protected $oauth;

    /**
     * @var string
     */
    protected $consumerKey;

    /**
     * @var string
     */
    protected $consumerSecret;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $requestToken;

    /**
     * @var string
     */
    protected $requestTokenSecret;

    /**
     * @var string
     */
    protected $verifier;

    /**
     * @var string
     */
    protected $accessToken;

    /**
     * Constructor
     *
     * @param string $consumerKey    oAuth consumer key
     * @param string $consumerSecret oAuth consumer secret
     * @param string $realm          Realm realm to login with
     * @param string $baseUrl        Full base URL of your OpenX service
     * @param array  $config         Configuration settings for oAuth URLs to use and callback
     */
    public function __construct($consumerKey, $consumerSecret, $realm, $baseUrl, array $config = [])
    {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->realm = $realm;
        $this->baseUrl = $baseUrl;

        $this->client = new Client([
            'base_url' => $this->baseUrl,
            'defaults' => [
                'auth' => 'oauth',
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]
        ]);

        $this->oauth = new Oauth1([
            'consumer_key'    => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret,
            'realm'           => $this->realm
        ]);
        $this->client->getEmitter()->attach($this->oauth);

        $this->config = array_merge([
            'requestTokenUrl' => 'https://sso.openx.com/api/index/initiate',
            'accessTokenUrl'  => 'https://sso.openx.com/api/index/token',
            'authorizeUrl'    => 'https://sso.openx.com/login/login',
            'loginUrl'        => 'https://sso.openx.com/login/process',
            'callbackUrl'     => 'oob' // oob = "Out of Band" (programmatic login)
        ], $config);
    }

    /**
     * Get Guzzle HTTP client
     *
     * @return GuzzleHttp\Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get oAuth request token - 1st step
     *
     * @param boolean $refresh Refresh request (retrieve token from webservice again instead of returning cached value)
     *
     * @return string
     */
    public function getRequestToken($refresh = false)
    {
        if (!empty($this->requestToken) && $refresh === false) {
            return $this->requestToken;
        }

        $res = $this->client->post($this->config['requestTokenUrl'], ['body' => ['oauth_callback' => $this->config['callbackUrl']]]);
        parse_str((string) $res->getBody(), $params);

        $this->requestToken       = $params['oauth_token'];
        $this->requestTokenSecret = $params['oauth_token_secret'];

        return $this->requestToken;
    }

    /**
     * Login with oAuth 1.0a API - 2nd step
     *
     * @param string $email    Email of user to login with
     * @param string $password Password of user to login with
     *
     * @return boolean
     */
    public function login($email, $password)
    {
        if (empty($this->requestToken)) {
            $this->getRequestToken();
        }

        $response = $this->client->post($this->config['loginUrl'], [
            'body' => [
                'email'       => $email,
                'password'    => $password,
                'oauth_token' => $this->requestToken
            ]
        ]);
        parse_str(substr((string) $response->getBody(), 4), $loginParams);

        $this->requestToken = $loginParams['oauth_token'];
        $this->verifier     = $loginParams['oauth_verifier'];

        return true;
    }

    /**
     * Get access token - 3rd and final step
     *
     * @param boolean $refresh Refresh request (retrieve token from webservice again instead of returning cached value)
     *
     * @return string
     */
    public function getAccessToken($refresh = false)
    {
        if (!empty($this->accessToken) && $refresh === false) {
            return $this->accessToken;
        }

        if (empty($this->requestToken) || empty($this->verifier)) {
            throw new \BadMethodCallException("This method requires a valid requestToken and verifier. Please call \$client->login() first.");
        }

        $this->client->getEmitter()->detach($this->oauth);
        $this->oauth = new Oauth1([
            'consumer_key'    => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret,
            'token'           => $this->requestToken,
            'token_secret'    => $this->requestTokenSecret,
            'verifier'        => $this->verifier
        ]);

        // Set the "auth" request option to "oauth" to sign using oauth
        $this->client->getEmitter()->attach($this->oauth);
        $res = $this->client->post($this->config['accessTokenUrl']);

        $response = (string) $res->getBody();
        parse_str($response, $accessTokenParams);

        // We don't need the oauth plugin any more
        $this->client->getEmitter()->detach($this->oauth);

        // Save and return acccess token
        $this->accessToken = $accessTokenParams['oauth_token'];

        return $this->accessToken;
    }

    /**
     * Return authentication cookie string
     *
     * @return string
     */
    public function getAuthCookie()
    {
        if (empty($this->accessToken)) {
            $this->getAccessToken();
        }

        return 'openx3_access_token=' . $this->accessToken;
    }

    /**
     * Passthrough method to Guzzle's common HTTP methods
     *
     * @param string $method HTTP Method to call
     * @param array  $args   Array of arguments to pass to function
     *
     * @return mixed
     */
    public function __call($method, array $args = [])
    {
        if (!in_array($method, ['get', 'post', 'put', 'delete', 'head', 'options', 'patch'])) {
            throw new \BadMethodCallException("Method $method does not exist on " . __CLASS__);
        }

        // Sort out arguments
        $url = isset($args[0]) ? $args[0] : null;
        $options = isset($args[1]) ? $args[1] : [];

        // Ensure cookie with access token is sent with each request
        $options['headers'] = array_merge([
            'Cookie' => $this->getAuthCookie()
        ], isset($options['headers']) ? $options['headers'] : []);

        // Make request
        return $this->client->$method($url, $options);
    }
}
