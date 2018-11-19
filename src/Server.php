<?php
namespace Jasny\SSO;

use Desarrolla2\Cache\Cache;
use Desarrolla2\Cache\Adapter;

require_once __DIR__ . '/../src/Polyfill/getallheaders.php';
/**
 * Single sign-on server.
 *
 * The SSO server is responsible of managing users sessions which are available for brokers.
 *
 * To use the SSO server, extend this class and implement the abstract methods.
 * This class may be used as controller in an MVC application.
 */
abstract class Server
{
    /**
     * @var array
     */
    protected $options = ['files_cache_directory' => '/tmp', 'files_cache_ttl' => 36000];

    /**
     * Cache that stores the special session data for the brokers.
     *
     * @var Cache
     */
    protected $cache;

    /**
     * @var string
     */
    protected $returnType;

    /**
     * @var mixed
     */
    protected $brokerId;


    /**
     * Class constructor
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options + $this->options;
        $this->cache = $this->createCacheAdapter();
    }

    /**
     * Create a cache to store the broker session id.
     *
     * @return Cache
     */
    protected function createCacheAdapter()
    {
        $adapter = new Adapter\File($this->options['files_cache_directory']);
        $adapter->setOption('ttl', $this->options['files_cache_ttl']);

        return new Cache($adapter);
    }

    /**
     * Start the session for broker requests to the SSO server
     */
    public function startBrokerSession()
    {
        if (isset($this->brokerId)) return;

        $sid = $this->getBrokerSessionID();

        if ($sid === false) {
            throw new \Exception("Broker didn't send a session key", 400);
        }

        $linkedId = $this->cache->get($sid);

        if (!$linkedId) {
            throw new \Exception("The broker session id isn't attached to a user session", 403);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            if ($linkedId !== session_id()) {
                throw new \Exception("Session has already started", 400);
            }
            return;
        }

        session_id($linkedId);
        session_start();

        $this->brokerId = $this->validateBrokerSessionId($sid);
    }

            /**
         * Get session ID from header Authorization or from $_GET/$_POST
         */
        protected function getBrokerSessionID()
        {
            $headers = getallheaders();

            if (isset($headers['Authorization']) &&  strpos($headers['Authorization'], 'Bearer') === 0) {
                $headers['Authorization'] = substr($headers['Authorization'], 7);
                return $headers['Authorization'];
            }
            if (isset($_GET['access_token'])) {
                return $_GET['access_token'];
            }
            if (isset($_POST['access_token'])) {
                return $_POST['access_token'];
            }
            if (isset($_GET['sso_session'])) {
                return $_GET['sso_session'];
            }

            return false;
        }

    /**
     * Validate the broker session id
     *
     * @param string $sid session id
     * @return string  the broker id
     */
    protected function validateBrokerSessionId($sid)
    {
        $matches = null;

        if (!preg_match('/^SSO-(\w*+)-(\w*+)-([a-z0-9]*+)$/', $this->getBrokerSessionID(), $matches)) {
            throw new \Exception("Invalid session id", 400);
        }

        $brokerId = $matches[1];
        $token = $matches[2];

        if ($this->generateSessionId($brokerId, $token) != $sid) {
            throw new \Exception("Checksum failed: Client IP address may have changed", 403);
        }

        return $brokerId;
    }

    /**
     * Start the session when a user visits the SSO server
     */
    protected function startUserSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    /**
     * Generate session id from session token
     *
     * @param string $brokerId
     * @param string $token
     * @return string
     */
    protected function generateSessionId($brokerId, $token)
    {
        $broker = $this->getBrokerInfo($brokerId);

        if (!isset($broker)) return null;

        return "SSO-{$brokerId}-{$token}-" . hash('sha256', 'session' . $token . $broker['secret']);
    }

    /**
     * Generate session id from session token
     *
     * @param string $brokerId
     * @param string $token
     * @return string
     */
    protected function generateAttachChecksum($brokerId, $token)
    {
        $broker = $this->getBrokerInfo($brokerId);

        if (!isset($broker)) return null;

        return hash('sha256', 'attach' . $token . $broker['secret']);
    }


    /**
     * Detect the type for the HTTP response.
     * Should only be done for an `attach` request.
     */
    protected function detectReturnType()
    {
        if (array_key_exists('return_url', $_GET) && false === empty($_GET['return_url'])) {
            $this->returnType = 'redirect';
        } else {
            $this->returnType = 'json';
        }

    }

    /**
     * Attach a user session to a broker session
     */
    public function attach()
    {
        $this->detectReturnType();

        if (empty($_REQUEST['broker'])) {
            throw new \Exception("No broker specified", 400);
        }

        if (empty($_REQUEST['token'])) {
            throw new \Exception("No token specified", 400);
        }

        $checksum = $this->generateAttachChecksum($_REQUEST['broker'], $_REQUEST['token']);

        if (empty($_REQUEST['checksum']) || $checksum != $_REQUEST['checksum']) {
            throw new \Exception("Invalid checksum", 400);
        }

        $this->startUserSession();
        $sid = $this->generateSessionId($_REQUEST['broker'], $_REQUEST['token']);

        $this->cache->set($sid, $this->getSessionData('id'));
        $this->outputAttachSuccess();
    }

    /**
     * Output on a successful attach
     */
    protected function outputAttachSuccess()
    {
        if ($this->returnType === 'json') {
            header('Content-type: application/json; charset=UTF-8');
            echo json_encode(['success' => 'attached']);
            exit;
        }

        if ($this->returnType === 'redirect') {
            $url = $_REQUEST['return_url'];
            header("Location: $url", true, 307);
        }
    }

    /**
     * Authenticate
     */
    public function login()
    {
        $this->startBrokerSession();

        if (empty($_POST['username'])) {
            throw new \Exception("No username specified", 400);
        }

        if (empty($_POST['password'])) {
            throw new \Exception("No password specified", 400);
        }

        $validation = $this->authenticate($_POST['username'], $_POST['password']);

        if ($validation->failed()) {
            throw new \Exception($validation->getError(), 400);
        }

        $this->setSessionData('sso_user', $_POST['username']);
        return $this->userInfo();
    }

    /**
     * Log out
     */
    public function logout()
    {
        $this->startBrokerSession();
        $this->setSessionData('sso_user', null);

        return ['data' => '', 'status' => 204];
    }

    /**
     * Ouput user information as json.
     */
    public function userInfo()
    {
        $this->startBrokerSession();
        $user = null;

        $username = $this->getSessionData('sso_user');

        if ($username) {
            $user = $this->getUserInfo($username);
            if (!$user) throw new \Exception("User not found", 500); // Shouldn't happen
        }

        return ['data' => $user, 'status' => 200];
    }


    /**
     * Set session data
     *
     * @param string $key
     * @param string $value
     */
    protected function setSessionData($key, $value)
    {
        if (!isset($value)) {
            unset($_SESSION[$key]);
            return;
        }

        $_SESSION[$key] = $value;
    }

    /**
     * Get session data
     *
     * @param type $key
     */
    protected function getSessionData($key)
    {
        if ($key === 'id') return session_id();

        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    /**
     * Authenticate using user credentials
     *
     * @param string $username
     * @param string $password
     * @return \Jasny\ValidationResult
     */
    abstract protected function authenticate($username, $password);

    /**
     * Get the secret key and other info of a broker
     *
     * @param string $brokerId
     * @return array
     */
    abstract protected function getBrokerInfo($brokerId);

    /**
     * Get the information about a user
     *
     * @param string $username
     * @return array|object
     */
    abstract protected function getUserInfo($username);
}

