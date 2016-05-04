### Error
* at sso server: Meet the error: Invalid request (Malformed HTTP request)
* Fixed because use same port of debug: 9000

### Main point of program:
* Jasny\SSO\Broker::getSessionid()

### Debug
* You can use debug listening and stop to debug both  client and server

### Flow of broker when get user info
#### On broker side
* new Jasny\SSO\Broker('http://localhost:9005', 'Alice', '8iwzik1bwd');
* attached = true when token is set
* call attach will generate token and setcookie('sso_token_alice', base_convert(md5(uniqid(rand(), true)), 16, 36), '/');
* The broker will redirect with 307 url with informations such as:
    * command: attach
    * broker = Alice
    * token = 4cbg6l6nqpkwg4gg04404kwg8
    * checksum = aed4cd8fce73dca30eb7814cdb82d63506f3d1a6446164ec4bf239c1f884c3cc. = hash('sha256', 'attach' . $this->token . $this->secret)
    * return_url = http://localhost:9001/

#### On server side
* The broker will pass the cookie to them
* ssoServer keep information about brokers:
    * Alice: secret = 8iwzik1bwd
* generateAttachChecksum such as:$checksum = hash('sha256', 'attach' . $token . $broker['secret']); 
* The checksum between server and request to make sure it is valid
* sessionId = "SSO-{$brokerId}-{$token}-" . hash('sha256', 'session' . $token . $broker['secret']);
* $sessionId = SSO-Alice-4cbg6l6nqpkwg4gg04404kwg8-c182a0aea63f5dc9285dd65a1b712e9e53ee8b846398bc342b5f82e2516ffe7c
* we save Cache->set($sessionid, session_id());

#### AFter redirect from server at broker side.
* we have: sso_token_alice: 4cbg6l6nqpkwg4gg04404kwg8
* PHPSESSID: 56va1qm1035598lnqb0mra0vl3
* request urL have sso_session: SSO-Alice-4cbg6l6nqpkwg4gg04404kwg8-c182a0aea63f5dc9285dd65a1b712e9e53ee8b846398bc342b5f82e2516ffe7c
* it will get data from /tmp/sso_session.php.cache

#### NOw the broker call server again:
* Brokern send: sso_session = SSO-Alice-4cbg6l6nqpkwg4gg04404kwg8-c182a0aea63f5dc9285dd65a1b712e9e53ee8b846398bc342b5f82e2516ffe7cs
* $linkedId = $this->cache->get($sid);  $linkedId = 56va1qm1035598lnqb0mra0vl3
* From the session_sso we parse and we get:
```PHP
if (!preg_match('/^SSO-(\w*+)-(\w*+)-([a-z0-9]*+)$/', $_GET['sso_session'], $matches)) {
    return $this->fail("Invalid session id");
}

$brokerId = $matches[1];
$token = $matches[2];
```
* We check checksum again
#### When login on another borker site:
* sid = SSO-Greg-486t4mlh39mos4skogc4kw0go-63c694f80630cc525c281f1ffe2fee3ecfd6ecea4f0bb949482031801b9e08c4
* But when we use: $this->cache->get. it return 56va1qm1035598lnqb0mra0vl3 same session_id above
* When we call: $username = $this->getSessionData('sso_user'); it get data from session and we have the username = 'jackie'
 
#### The basic of problems
* The key is session_id($id). All broker will pass some information to server and get the same $id. So it will get the some value out.
* When user logout because the session with that $id has been deleted so we know the user has been logout
* When attach call the file /tmp/{{sso_session}}.php.cache will be created and the value of session_id() will be save to that file.
* We have one time redirect. this redirect is the key. It will create a PHPSESSID between your browser and the server. So From here we have save the session_id and 
use session_id($thatId).  and return data backto the browser.