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
    public $url;

    /**
     * My identifier, given by SSO provider.
     * @var string
     */
    public $brokerId;

    /**
     * My secret word, given by SSO provider.
     * @var string
     */
    public $secret;
    
    
    /**
     * Session token of the client
     * @var string
     */
    protected $token;

    /**
     * User info recieved from the server.
     * @var array
     */
    protected $userinfo;
    
    
    /**
     * Class constructor
     * 
     * @param string $url       Url of SSO server
     * @param string $brokerId  My identifier, given by SSO provider.
     * @param string $secret    My secret word, given by SSO provider.
     */
    public function __construct($url, $brokerId, $secret)
    {
        $this->url = $url;
        $this->brokerId = $brokerId;
        $this->secret = $secret;
        
        if (isset($_SESSION['SSO']['token'])) $this->token = $_SESSION['SSO']['token'];
        if (isset($_SESSION['SSO']['userinfo'])) $this->userinfo = $_SESSION['SSO']['userinfo'];
    }
    

    /**
     * Get session token
     * 
     * @return string
     */
    public function getToken()
    {
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
        $token = md5(uniqid(rand(), true));
        $_SESSION['SSO']['token'] = $token;
        
        $checksum = md5("attach{$token}{$_SERVER['REMOTE_ADDR']}{$this->secret}");
        return "{$this->url}?cmd=attach&broker={$this->broker}&token=$token&checksum=$checksum";
    }

    /**
     * Attach our session to the user's session on the SSO server.
     * 
     * @param string $returnUrl  The URL the client should be returned to after attaching
     */
    public function attach($returnUrl = null)
    {
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
            'commans' => $command,
            'brokerId' => $this->brokerId,
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
    protected function request($command, $params = null)
    {
        $ch = curl_init($this->getRequestUrl($command));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if (isset($params)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $response = curl_exec($ch);
        
        if (curl_errno($ch) != 0) throw new Exception("Server request failed: " . curl_error($ch));

        $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        list($contentType) = explode(';', $info->content_type);
        
        if ($contentType != 'application/json') {
            throw new Exception("Response did not come from the SSO server. $response", $info->http_code);
        }
        
        $data = json_decode($response);
        
        if ($info->http_code != 200) throw new Exception($data, $info->http_code);
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
        $result = $this->request('login', compact('username', 'password'));
        
        if ($result->success) $this->userinfo = $result->userinfo;
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
        if (!isset($this->userinfo)) {
            $this->userinfo = $this->request('userinfo');
        }

        return $this->userinfo;
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
