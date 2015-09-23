<?php
namespace Jasny\SSO;

require_once __DIR__ . '/../vendor/autoload.php';

use Desarrolla2\Cache\Cache;
use Desarrolla2\Cache\Adapter;

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
     * Class constructor
     */
    public function __construct()
    {
        $this->cache = $this->createCacheAdapter();
    }

    /**
     * Create a cache to store the broker session id.
     *
     * @return Cache
     */
    protected function createCacheAdapter()
    {
        $adapter = new Adapter\File('/tmp');
        $adapter->setOption('ttl', 10 * 3600);
        
        return new Cache($adapter);
    }
    

    /**
     * Start session and protect against session hijacking
     */
    protected function startSession()
    {
        $matches = null;

        if (
            isset($_GET['sso_session'])
            && preg_match('/^SSO-(\w*+)-(\w*+)-([a-z0-9]*+)$/', $_GET['sso_session'], $matches)
        ) {
            $this->startBrokerSession($_GET['sso_session'], $matches[1], $matches[2]);
        } else {
            $this->startUserSession();
        }
    }

    /**
     * Start the session for broker requests to the SSO server
     */
    protected function startBrokerSession($sid, $brokerId, $token)
    {
        $linkedId = $this->cache->get($sid);
        
        if (!$linkedId) {
            return $this->fail("The broker session id isn't attached to a user session", 403);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            if ($linkedId !== session_id()) throw new \Exception("Session has already started.");
            return;
        }
        
        session_id($linkedId);
        session_start();
        
        if (!isset($_SESSION['client_addr'])) {
            session_destroy();
            return $this->fail("Unknown client IP address for the attached session", 500);
        }

        if ($this->generateSessionId($brokerId, $token, $_SESSION['client_addr']) != $sid) {
            session_destroy();
            return $this->fail("Checksum failed: Client IP address may have changed", 403);
        }

        $this->broker = $brokerId;
        return;
    }

    /**
     * Start the session when a user visits the SSO server
     */
    protected function startUserSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();

        if (isset($_SESSION['client_addr']) && $_SESSION['client_addr'] !== $_SERVER['REMOTE_ADDR']) {
            session_regenerate_id(true);
        }
        
        if (!isset($_SESSION['client_addr'])) {
            $_SESSION['client_addr'] = $_SERVER['REMOTE_ADDR'];
        }
    }
    
    
    /**
     * Generate session id from session token
     *
     * @return string
     */
    protected function generateSessionId($brokerId, $token, $client_addr = null)
    {
        $broker = $this->getBrokerInfo($brokerId);

        if (!isset($broker)) return null;
        if (!isset($client_addr)) $client_addr = $_SERVER['REMOTE_ADDR'];

        return "SSO-{$brokerId}-{$token}-" . hash('sha256', 'session' . $token . $client_addr . $broker['secret']);
    }

    /**
     * Generate session id from session token
     *
     * @return string
     */
    protected function generateAttachChecksum($brokerId, $token)
    {
        $broker = $this->getBrokerInfo($brokerId);
        if (!isset($broker)) return null;

        return hash('sha256', 'attach' . $token . $_SERVER['REMOTE_ADDR'] . $broker['secret']);
    }
    

    /**
     * Detect the type for the HTTP response.
     * Should only be done for an `attach` request.
     */
    protected function detectReturnType()
    {
        if (!empty($_REQUEST['return_url'])) {
            $this->returnType = 'redirect';
        } elseif (strpos($_SERVER['HTTP_ACCEPT'], 'image/') !== false) {
            $this->returnType = 'image';
        } elseif (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            $this->returnType = 'json';
        } elseif (!empty($_REQUEST['return_url'])) {
            $this->returnType = 'jsonp';
        }
    }

    /**
     * Attach a user session to a broker session
     */
    public function attach()
    {
        $this->detectReturnType();
        
        if (empty($_REQUEST['broker'])) return $this->fail("No broker specified", 400);
        if (empty($_REQUEST['token'])) return $this->fail("No token specified", 400);

        if (!$this->returnType) return $this->fail("No return url specified", 400);

        $checksum = $this->generateAttachChecksum($_REQUEST['broker'], $_REQUEST['token']);
        
        if (empty($_REQUEST['checksum']) || $checksum != $_REQUEST['checksum']) {
            return $this->fail("Invalid checksum", 400);
        }

        $this->startUserSession();
        $sid = $this->generateSessionId($_REQUEST['broker'], $_REQUEST['token']);
        
        $this->cache->set($sid, session_id());
        $this->outputAttachSuccess();
    }

    /**
     * Output on a successful attach
     */
    protected function outputAttachSuccess()
    {
        if ($this->returnType === 'image') {
            $this->outputImage();
        }
        
        if ($this->returnType === 'json') {
            header('Content-type: application/json; charset=UTF-8');        
            echo json_encode(['success' => 'attached']);
        }
        
        if ($this->returnType === 'jsonp') {
            echo $_REQUEST['callback'] . "(200);";
        }
        
        if ($this->returnType === 'redirect') {
            $url = $_REQUEST['return_url'];
            header("Location: $url", true, 307);
            echo "You're being redirected to <a href='{$url}'>$url</a>";
        }
    }

    /**
     * Output a 1x1px transparent image
     */
    protected function outputImage()
    {
        header('Content-Type: image/png');
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQ'
            . 'MAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZg'
            . 'AAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=');
    }


    /**
     * Authenticate
     */
    public function login()
    {
        $this->startSession();

        if (empty($_POST['username'])) $this->fail("No user specified", 400);
        if (empty($_POST['password'])) $this->fail("No password specified", 400);

        $validation = $this->authenticate($_POST['username'], $_POST['password']);

        if ($validation->failed()) {
            return $this->fail($validation->getError(), 400);
        }

        $_SESSION['sso_user'] = $_POST['username'];
        $this->userInfo();
    }

    /**
     * Log out
     */
    public function logout()
    {
        $this->startSession();
        unset($_SESSION['sso_user']);

        header('Content-type: application/json; charset=UTF-8');
        http_response_code(204);
    }

    /**
     * Ouput user information as json.
     */
    public function userInfo()
    {
        $this->startSession();
        $user = null;
        
        if (isset($_SESSION['sso_user'])) {
            $user = $this->getUserInfo($_SESSION['sso_user']);
            if (!$user) return $this->fail("User not found", 500); // Shouldn't happen
        }

        header('Content-type: application/json; charset=UTF-8');
        echo json_encode($user);
    }


    /**
     * An error occured.
     *
     * @param string $message
     * @param int    $http_status
     */
    protected function fail($message, $http_status = 500)
    {
        if ($http_status === 500) trigger_error($message, E_USER_WARNING);
        
        if ($this->returnType === 'jsonp') {
            echo $_REQUEST['callback'] . "($http_status, '" . addslashes($message) . "');";
            exit();
        }

        if ($this->returnType === 'redirect') {
            $url = $_REQUEST['return_url'] . '?sso_error=' . $message;
            header("Location: $url", true, 307);
            echo "You're being redirected to <a href='{$url}'>$url</a>";
            exit();
        }
        
        http_response_code($http_status);
        header('Content-type: application/json; charset=UTF-8');

        echo json_encode(['error' => $message]);
        exit();
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

