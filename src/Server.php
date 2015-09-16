<?php
namespace Jasny\SSO;

require_once __DIR__ . '/../vendor/autoload.php';

use Desarrolla2\Cache\Cache;
use Desarrolla2\Cache\Adapter;
use Jasny\ValidationResult;

/**
 * Single sign-on server.
 *
 * The SSO server is responsible of managing users sessions which are available for brokers.
 */
abstract class Server
{
    private $started = false;

    /**
     * Cache that stores the special session data for the brokers.
     *
     * @var Desarrolla2\Cache\Cache
     */
    public $cache;

    public function __construct()
    {
        $this->cache = $this->createCacheAdapter();
        $this->cache->set('hello world', 'bonjour');
        error_log('cache: ' . $this->cache->get('hello world'));
        error_log('request: ' . json_encode($_REQUEST));
    }

    /**
     * Start session and protect against session hijacking
     */
    protected function sessionStart()
    {
        if ($this->started) return;

        $this->started = true;

        // Broker session
        $matches = null;

        if (
            isset($_REQUEST[session_name()])
            && preg_match('/^SSO-(\w*+)-(\w*+)-([a-z0-9]*+)$/', $_REQUEST[session_name()], $matches)
        ) {
            $sid = $_REQUEST[session_name()];

            /* for (cross domain) ajax attach calls */
            if (isset($_POST['clientSid'])
                && $this->generateSessionId($matches[1], $matches[2], $_POST['clientAddr']) == $sid) {
                error_log('setting sid');
                session_id($_POST['clientSid']);
                session_start();

                if (isset($_SESSION['client_addr']) && $_SESSION['client_addr'] != $_POST['clientAddr']) {
                    unset($_SESSION['username']);
                }

                $_SESSION['client_addr'] = $_POST['clientAddr'];
                return;
            }

            $linkedId = $this->cache->get($sid);
            if ($linkedId) {
                session_id($linkedId);
                session_start();
                // TODO: the session cookie expires in 1 second.
                setcookie(session_name(), "", 1);
            } else {
                session_start();
            }

            error_log('session ' . json_encode($_SESSION));

            if (!isset($_SESSION['client_addr'])) {
                session_destroy();
                $this->fail("Not attached");
            }

            if ($this->generateSessionId($matches[1], $matches[2], $_SESSION['client_addr']) != $sid) {
                session_destroy();
                $this->fail("Invalid session id");
            }

            $this->broker = $matches[1];
            return;
        }

        // User session

        error_log('starting user session');
        session_start();

        error_log('session ' . json_encode($_SESSION));
        error_log('session dd' . session_id());
        if (isset($_SESSION['client_addr']) && $_SESSION['client_addr'] != $_SERVER['REMOTE_ADDR']) {
            error_log('regenerate id');
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
    protected function generateSessionId($broker, $token, $client_addr = null)
    {
        $brokerInfo = $this->getBrokerInfo($broker);

        if (!isset($brokerInfo)) return null;
        if (!isset($client_addr)) $client_addr = $_SERVER['REMOTE_ADDR'];

        return "SSO-{$broker}-{$token}-" . md5('session' . $token . $client_addr . $brokerInfo['secret']);
    }

    /**
     * Generate session id from session token
     *
     * @return string
     */
    protected function generateAttachChecksum($broker, $token)
    {
        $brokerInfo = $this->getBrokerInfo($broker);
        if (!isset($brokerInfo)) return null;

        return md5('attach' . $token . $_SERVER['REMOTE_ADDR'] . $brokerInfo['secret']);
    }

    /**
     * Authenticate
     */
    public function login()
    {
        $this->sessionStart();

        if (empty($_POST['username'])) $this->failLogin("No user specified");
        if (empty($_POST['password'])) $this->failLogin("No password specified");

        $validation = $this->authenticate($_POST['username'], $_POST['password']);

        if ($validation->failed()) {
            $this->failLogin($validation->getErrors());
        }

        $_SESSION['username'] = $_POST['username'];
        $this->userInfo();
    }

    /**
     * Log out
     */
    public function logout()
    {
        $this->sessionStart();
        unset($_SESSION['username']);

        header('Content-type: application/json; charset=UTF-8');
        echo "{}";
    }

    /**
     * Attach a user session to a broker session
     */
    public function attach()
    {
        $this->sessionStart();

        if (empty($_REQUEST['broker'])) $this->fail("No broker specified");
        if (empty($_REQUEST['token']))  $this->fail("No token specified");

        $checksum = $this->generateAttachChecksum($_REQUEST['broker'], $_REQUEST['token']);
        $sid = $this->generateSessionId($_REQUEST['broker'], $_REQUEST['token']);
        error_log('sid: ' . $sid);
        error_log('checksum: ' . $checksum);
        if (empty($_REQUEST['checksum'])
            || $checksum != $_REQUEST['checksum']) {
            $this->fail("Invalid checksum");
        }

        // what if there already exists an entry ?
        $this->cache->set($sid, session_id());

        if (!empty($_REQUEST['returnUrl'])) {
            header('Location: ' . $_REQUEST['returnUrl'], true, 307);
            exit();
        }

        // Output an image specially for AJAX apps
        header('Content-type: application/json; charset=UTF-8');
        echo json_encode(['token' => $_REQUEST['token']]);
    }

    /**
     * Ouput user information as json.
     */
    public function userInfo()
    {
        $this->sessionStart();
        if (!isset($_SESSION['username'])) $this->failLogin("Not logged in");

        $userData = $this->getUserInfo($_SESSION['username']);
        $userData['username'] = $_SESSION['username'];

        header('Content-type: application/json; charset=UTF-8');
        echo json_encode($userData);
    }

    /**
     * An error occured.
     *
     * @param string $message
     */
    protected function fail($message)
    {
        error_log($message);
            
        header("HTTP/1.1 400 Bad Request");
        header('Content-type: application/json; charset=UTF-8');

        echo json_encode(['error' => $message]);
        exit;
    }

    /**
     * Login failure.
     *
     * @param string $message
     */
    protected function failLogin($message)
    {
        header("HTTP/1.1 401 Unauthorized");
        header('Content-type: application/json; charset=UTF-8');
        
        echo json_encode(['error' => $message]);
        exit;
    }

    /**
     * Create a cache to store the broker session id.
     */
    protected function createCacheAdapter()
    {
        $adapter = new Adapter\File('/tmp');
        $adapter->setOption('ttl', 10 * 3600);
        
        return new Cache($adapter);
    }

    abstract protected function authenticate($username, $password);
    abstract protected function getBrokerInfo($brokerId);
    abstract protected function getUserInfo($brokerId);
}
