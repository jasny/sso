<?php
namespace Jasny\SSO;

/**
 * Single sign-on broker.
 *
 * The broker lives on the website visited by the user. The broken doesn't have any user credentials stored. Instead it
 * will talk to the SSO server in name of the user, verifying credentials and getting user information.
 */
class Broker
{
    /**
     * Url of SSO server
     * @var string
     */
    protected $url;

    /**
     * My identifier, given by SSO provider.
     * @var string
     */
    public $broker;

    /**
     * My secret word, given by SSO provider.
     * @var string
     */
    protected $secret;

    /**
     * Session token of the client
     * @var string
     */
   public $token;

    /**
     * User info recieved from the server.
     * @var array
     */
    protected $userinfo;

    /**
     * Class constructor
     *
     * @param string $url       Url of SSO server
     * @param string $broker  My identifier, given by SSO provider.
     * @param string $secret    My secret word, given by SSO provider.
     */
    public function __construct($url, $broker, $secret)
    {
        $this->url = $url;
        $this->broker = $broker;
        $this->secret = $secret;
        session_start();
        //error_log('userinfo: '. $_SESSION['SSO']['userinfo']);
        error_log('session ' . json_encode($_SESSION));
        if (isset($_SESSION['SSO']['token'])) $this->token = $_SESSION['SSO']['token'];
        // if (isset($_SESSION['SSO']['userinfo'])) $this->userinfo = $_SESSION['SSO']['userinfo'];

        error_log('token ' . $this->token);
    }

    /**
     * Generate session id from session key
     *
     * @return string
     */
    protected function getSessionId()
    {
		if (!isset($this->token)) return null;
        return "SSO-{$this->broker}-{$this->token}-" . md5('session' . $this->token . $_SERVER['REMOTE_ADDR'] . $this->secret);
    }

    /**
     * Get session token
     *
     * @return string
     */
    public function getToken()
    {
        if (!isset($this->token)) {
            $this->token = md5(uniqid(rand(), true));
            $_SESSION['SSO']['token'] = $this->token;
        }

        return $this->token;
    }

    /**
     * Check if we have an SSO token.
     *
     * @return boolean
     */
    public function isAttached()
    {
        return isset($this->token);
    }

    /**
     * Get URL to attach session at SSO server.
     *
     * @return string
     */
    public function getAttachUrl()
    {
        $token = $this->getToken();
        $checksum = md5("attach{$token}{$_SERVER['REMOTE_ADDR']}{$this->secret}");
        return "{$this->url}?command=attach&broker={$this->broker}&token=$token&checksum=$checksum";
    }

    /**
     * Attach our session to the user's session on the SSO server.
     *
     * @param string $returnUrl  The URL the client should be returned to after attaching
     */
    public function attach($returnUrl = null)
    {
        error_log('trying to attach');
        if ($this->isAttached()) return;

        if (!isset($returnUrl)) $returnUrl = "http://{$_SERVER["SERVER_NAME"]}{$_SERVER["REQUEST_URI"]}";
        $url = $this->getAttachUrl() . "&returnUrl=" . urlencode($returnUrl);

        header("Location: $url", true, 307);
        echo "You're redirected to <a href=\"$url\">$url</a>";
        exit();
    }

    /**
     * Detach our session from the user's session on the SSO server.
     */
    public function detach()
    {
        $this->token = null;
        $this->userinfo = null;

        unset($_SESSION['SSO']);
    }


    /**
     * Get the request url for a command
     *
     * @param string $command
     * @return string
     */
    protected function getRequestUrl($command)
    {
        $getParams = array(
            'command' => $command,
            'broker' => $this->broker,
            'token' => $this->token,
            'checksum' => md5('session' . $this->token . $_SERVER['REMOTE_ADDR'] . $this->secret)
        );

        return $this->url . '?' . http_build_query($getParams);
    }

    /**
     * Execute on SSO server.
     *
     * @param string $command  Command
     * @param array  $params   Post parameters
     * @return array
     */
    protected function request($command, $params = array())
    {
        $ch = curl_init($this->getRequestUrl($command));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        error_log($this->getSessionId());
        curl_setopt($ch, CURLOPT_POST, true);

        $params[session_name()] = $this->getSessionId();
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

        $response = curl_exec($ch);
        if (curl_errno($ch) != 0) throw new \Exception("Server request failed: " . curl_error($ch));

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE );
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $contentType = explode('; ', $contentType)[0];

        if ($contentType != 'application/json') {
            throw new \Exception("Response did not come from the SSO server. $response", $httpCode);
        }

        error_log('response ' . $response);
        $data = json_decode(stripslashes(trim($response)), true);
        if ($httpCode != 200) throw new \Exception($httpCode);
        return $data;
    }


    /**
     * Log the client in at the SSO server.
     *
     * Only brokers marked trused can collect and send the user's credentials. Other brokers should omit $username and
     * $password.
     *
     * @param string $username
     * @param string $password
     * @return object
     */
    public function login($username = null, $password = null)
    {
        if (!isset($username)) $username = $_REQUEST['username'];
        if (!isset($password)) $password = $_REQUEST['password'];
        $result = $this->request('login', compact('username', 'password'));
        if (!array_key_exists('error', $result)) {
            $this->userinfo = $result;
            // $_SESSION['SSO']['userinfo'] = $result;
            error_log('success');
        }
        else {
            error_log('failure');
        }
        return $result;
    }

    /**
     * Logout at sso server.
     */
    public function logout()
    {
        return $this->request('logout');
    }

    /**
     * Get user information.
     */
    public function getUserInfo()
    {
        error_log('trying to get user info');
        try {
            # TODO: the data is not updated
            if (!isset($this->userinfo)) {
                $this->userinfo = $this->request('userInfo');
            }

            return $this->userinfo;
        }
        catch (\Exception $ex) {
            error_log($ex);
            return null;
        }
    }

    /**
     * Handle notifications send by the SSO server
     *
     * @param string $event
     * @param object $data
     */
    public function on($event, $data)
    {
        if (method_exists($this, "on{$event}")) {
            $this->{"on{$event}"}($data);
        }
    }

    /**
     * Handle a login notification
     */
    protected function onLogin($data)
    {
        $this->userinfo = $data;
    }

    /**
     * Handle a logout notification
     */
    protected function onLogout()
    {
        $this->userinfo = null;
    }

    /**
     * Handle a notification about a change in the userinfo
     */
    protected function onUserinfo($data)
    {
        $this->userinfo = $data;
    }
}
?>