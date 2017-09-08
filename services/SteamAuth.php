<?php

// use RuntimeException;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Config;
// App::import('Vendor', 'GuzzleHttp');
require APP.'vendor'.DS.'autoload.php';
// 
use GuzzleHttp\Client as GuzzleClient;
// use Illuminate\Support\Fluent;

class SteamAuth
{

    /**
     * @var integer|null
     */
    public $steamId = null;

    /**
     * @var SteamInfo
     */
    public $steamInfo = null;

    /**
     * @var string
     */
    public $authUrl;

    // /**
    //  * @var Request
    //  */
    // private $request;

    /**
     * @var GuzzleClient
     */
    protected $guzzleClient;

    /**
     * @var string
     */
    const OPENID_URL = 'https://steamcommunity.com/openid/login';

    /**
     * @var string
     */
    const STEAM_INFO_URL = 'http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=%s&steamids=%s';

    // /**
    //  * Create a new SteamAuth instance
    //  *
    //  * @param Request $request
    //  */
    public function __construct()
    {
        $this->authUrl = $this->buildUrl();
        $this->guzzleClient = new GuzzleClient;
    }

    /**
     * Validates if the request object has required stream attributes.
     *
     * @return bool
     */
    private function requestIsValid()
    {
        debug(array("dump params: ", $this->params));
        return isset($this->params['openid_assoc_handle'])
               && isset($this->params['openid_signed'])
               && isset($this->params['openid_sig']);
    }

    /**
     * Checks the steam login
     *
     * @return bool
     */
    public function validate()
    {
        var_dump("top of validate");
        if (!$this->requestIsValid()) {
            var_dump("not valid");
            return false;
        }

        $params = $this->getParams();

        $response = $this->guzzleClient->request('POST', self::OPENID_URL, [
            'form_params' => $params
        ]);

        $results = $this->parseResults($response->getBody()->getContents());
        debug($results);
        $this->parseSteamID();
        $this->parseInfo();

        return $results->is_valid == 'true';
    }

    /**
     * Get param list for openId validation
     *
     * @return array
     */
    public function getParams()
    {
        $params = [
            'openid.assoc_handle' => $this->params['openid_assoc_handle'],
            'openid.signed'       => $this->params['openid_signed'],
            'openid.sig'          => $this->params['openid_sig'],
            'openid.ns'           => 'http://specs.openid.net/auth/2.0',
            'openid.mode'         => 'check_authentication'
        ];

        $signedParams = explode(',', $this->params['openid_signed']);

        foreach ($signedParams as $item) {
            $value = $this->params['openid_' . str_replace('.', '_', $item)];
            $params['openid.' . $item] = get_magic_quotes_gpc() ? stripslashes($value) : $value;
        }

        return $params;
    }

    /**
     * Parse openID reponse to fluent object
     *
     * @param  string $results openid reponse body
     * @return Fluent
     */
    public function parseResults($results)
    {
        $parsed = [];
        $lines = explode("\n", $results);

        foreach ($lines as $line) {
            if (empty($line)) continue;

            $line = explode(':', $line, 2);
            $parsed[$line[0]] = $line[1];
        }

        return (object) $parsed;
    }

    /**
     * Validates a given URL, ensuring it contains the http or https URI Scheme
     *
     * @param string $url
     *
     * @return bool
     */
    private function validateUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return true;
    }

    /**
     * Build the Steam login URL
     *
     * @param string|null $return A custom return to URL
     *
     * @return string
     */
    private function buildUrl($return = null)
    {
        if (is_null($return)) {
            $return = $this->formUrl(Configure::read('steam_auth.redirect_url'));
        }
        if (!is_null($return) && !$this->validateUrl($return)) {
            throw new RuntimeException('The return URL must be a valid URL with a URI scheme or http or https.');
        }

        $params = array(
            'openid.ns'         => 'http://specs.openid.net/auth/2.0',
            'openid.mode'       => 'checkid_setup',
            'openid.return_to'  => $return,
            'openid.realm'      => (Configure::read('steam-auth.https') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'],
            'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
        );

        return self::OPENID_URL . '?' . http_build_query($params, '', '&');
    }

    /**
     * Returns the redirect response to login
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function redirect()
    {
        var_dump("wtf");
        return;
        return $this->redirect($this->getAuthUrl());
    }

    /**
     * Parse the steamID from the OpenID response
     *
     * @return void
     */
    public function parseSteamID()
    {
        preg_match("#^http://steamcommunity.com/openid/id/([0-9]{17,25})#", $this->params['openid_claimed_id'], $matches);
        $this->steamId = is_numeric($matches[1]) ? $matches[1] : 0;
    }

    /**
     * Get user data from steam api
     *
     * @return void
     */
    public function parseInfo()
    {
        if (is_null($this->steamId)) return;

        if (empty(Configure::read('steam_auth.api_key'))) {
            // TODO: throw an exception
            // throw new RuntimeException('The Steam API key has not been specified.');
        }

        $reponse = $this->guzzleClient->request('GET', sprintf(self::STEAM_INFO_URL, Configure::read('steam_auth.api_key'), $this->steamId));
        $json = json_decode($reponse->getBody(), true);
        debug($json['response']['players'][0]);
        $this->steamInfo = new SteamInfo($json['response']['players'][0]);
    }

    public function formUrl($path)
    {
        return ((Configure::read('steam_auth.https') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $path);
    }

    // /**
    //  * Returns the login url
    //  *
    //  * @return string
    //  */
    public function getAuthUrl()
    {
        return $this->authUrl;
    }

    /**
     * Returns the SteamUser info
     *
     * @return SteamInfo
     */
    public function getUserInfo()
    {
        return $this->steamInfo;
    }

    /**
     * Returns the steam id
     *
     * @return bool|string
     */
    public function getSteamId()
    {
        return $this->steamId;
    }
}

class SteamInfo
{

    /**
     * {@inheritdoc}
     */
    public function __construct($data)
    {
        $steamID = isset($data['steamid']) ? $data['steamid'] : null;
        unset($data['steamid']);
        $this->personaname = $data['personaname'];
        $this->avatarfull = $data['avatarfull'];
        $this->steamID64 = $steamID;
        $this->steamID = $this->getSteamID($steamID);
    }

    /**
     * Get SteamID
     *
     * @param  string $value
     * @return string
     */
    public function getSteamID($value)
    {
        if (is_null($value)) return '';

        //See if the second number in the steamid (the auth server) is 0 or 1. Odd is 1, even is 0
        $authserver = bcsub($value, '76561197960265728') & 1;
        //Get the third number of the steamid
        $authid = (bcsub($value, '76561197960265728') - $authserver) / 2;

        //Concatenate the STEAM_ prefix and the first number, which is always 0, as well as colons with the other two numbers
        return "STEAM_0:$authserver:$authid";
    }
}