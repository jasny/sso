_This page has been contributed by [tuanphpvn](https://github.com/tuanphpvn)._

### Errors and Solutions

* "Invalid request (Malformed HTTP request)" error when trying debug sso with xdebug.
  * Reason: Because if you run sso-server with port 9000 which will conflict with xdebug port .
  * Solution: There are two way you can resolve it.
    * The simple way: Change the port of sso-server of examples:     
```PHP
php -S localhost:9090 -t examples/server/
export SSO_SERVER=http://localhost:9090 SSO_BROKER_ID=Alice SSO_BROKER_SECRET=8iwzik1bwd; php -S localhost:9001 -t examples/broker/
export SSO_SERVER=http://localhost:9090 SSO_BROKER_ID=Greg SSO_BROKER_SECRET=7pypoox2pc; php -S localhost:9002 -t examples/broker/
export SSO_SERVER=http://localhost:9090 SSO_BROKER_ID=Julias SSO_BROKER_SECRET=ceda63kmhp; php -S localhost:9003 -t examples/ajax-broker/
```
    * The second one: Change the port of xdebug. 
      * Step 1: Go the the editor which you are using and change the xdebug port from 9000 -> 9090.
      * Step 2: Change xdebug.remote_port=9090 in php config. For example: /etc/php5/cli/conf.d/20-xdebug.ini.
      * Step 3: If you want to debug cli add this line to ~/.bashrc:
             
```
export XDEBUG_CONFIG="remote_enable=1 remote_mode=req remote_port=9090 remote_host=127.0.0.1 remote_connect_back=0"
```
   * Note: all the config of xdebug must be done before run php -S ...
     
     
### Explain code:
* $broker = new Jasny\SSO\Broker(getenv('SSO_SERVER'), getenv('SSO_BROKER_ID'), getenv('SSO_BROKER_SECRET'));
    * $broker->url = "http://localhost:9005"
    * $broker->secret = "8iwzik1bwd"
    * $broker->broker = "Alice"
    * $broker->token = $_COOKIE[$broker->getCookieName()]
        * $broker->getCookieName() 
```
'sso_token_' . preg_replace('/[_\W]+/', '_', strtolower("**Alice**"))
```

* $broker->attach(true);
  * Two cases:
    * case 1 - The first time visit broker: 
      * Force the browser send url to server to attach session between browser and server by using:
            
```
header("Location: $url", true, 307);
echo "You're redirected to <a href='$url'>$url</a>";
exit();
```
   * Generate cookie $broker->getCookieName() for broker domain "http:/localhost:9001"
   * The $url have info:
            
```
$data = [
            'command' => 'attach',
            'broker' => 'Alice',
            'token' => '5bxhf7qi6hwkw40go4wwwoco8',
            'checksum' => hash('sha256', 'attach' . '5bxhf7qi6hwkw40go4wwwoco8' . '8iwzik1bwd'),
            'return_url' => 'http://localhost:9001/login.php'
        ]
```
   * case 2: in the second time:
      * Do nothing because $_COOKIE[$broker->getCookieName()] has been created.
* What is happen when browser redirect to server ?:
    * Check checksum:
        * Purpose: We need know: "5bxhf7qi6hwkw40go4wwwoco8"(token) and "8iwzik1bwd"(secret)
        * At the request we have: token, broker= 'Alice'. Now we need secret
        * From broker = 'Alice'. we can get 'secret'. Because server of sso save this information.
    * Save linked session:
        * Save file have name: "SSO-{$brokerId}-{$token}-" . hash('sha256', 'session' . $token . $broker['secret']).php.cache with content of session_id();
            * Explain: session_id() is the connect between browser and server. So because in the first time you visit broker it will force the browser redirect to sso server. 
            So the content of linked session file will have the same.
    * redirect back to broker:
        * Depend on type of request the server will action different: such as: redirect, send json or image in case of ajax
* In case Redirect url:
    * We will go to login.php
    * $broker->attach(true); will do nothing because  $_COOKIE['$broker->getCookieName()'] already exists in the first request.
    * Broker send request to server with the information below:
```
command: userInfo
sso_session : SSO-Alice-5bxhf7qi6hwkw40go4wwwoco8-4f593ea7c2feab231dc3779c09a7f5d1967b7d0a85ce997e22c3f8ff52bcc4ed
```
   
    * At the sso-server:
      * We get the content of session_id from $linkedId = $this->cache->get($sid); with:
        * id = SSO-Alice-5bxhf7qi6hwkw40go4wwwoco8-4f593ea7c2feab231dc3779c09a7f5d1967b7d0a85ce997e22c3f8ff52bcc4ed
          * $linkedId = fc9lerurhboaav16d3pf5ka2o6
          * $linkedId = session_id() which is result of browser communicate directly with sso-server.
      * Get session content of browser not session content of broker:
```
session_id($linkedId);
session_start();
```
      * Explain:
         * $linkedId = content of SSO-Alice-5bxhf7qi6hwkw40go4wwwoco8-4f593ea7c2feab231dc3779c09a7f5d1967b7d0a85ce997e22c3f8ff52bcc4ed.php.cache.
         * $linkedId: is the key to allow many broker share same session.
* In case of login. Broker will send login command to server
    * At server side:
        * $this->startBrokerSession();
            * Because broker send: sso_session: SSO-Alice-5bxhf7qi6hwkw40go4wwwoco8-4f593ea7c2feab231dc3779c09a7f5d1967b7d0a85ce997e22c3f8ff52bcc4ed
            * From this sso-session we can get session_id() which saved for browser and server.
            * From that old session_id() has been saved. We init it.
```
session_id($id);
session_start();
```
        * $validation = $this->authenticate($POST['username'], $_POST['password']);
            * If login sucess we have the user in $_SESSION
            * If not success authentication we return error.
* What happen if another broker domain login.
    * $broker->attach(true);
        * In the first time:
            * at broker side. it will create cookie token for that broker.
            * force browser redirect to server for generate session between server and browser. In this progress we will save the file such as: SSO-Greg-token-hash  with content "fc9lerurhboaav16d3pf5ka2o6" (same as broker Alice).
            * init session at server
```
session_id(fc9lerurhboaav16d3pf5ka2o6);
session_start();
```
        * In the second time after server force browser call return url this function will do nothing because $_COOKIE[$broker->getCookieName()] has been created.
    * When get userInfo from server:
        * Broker will request with sso_session.
        * From the sso_session we can get the value: fc9lerurhboaav16d3pf5ka2o6
        * From this value we call:     
```
session_id(fc9lerurhboaav16d3pf5ka2o6);
session_start();
```
        * Now server know what user request. It will return the information of the user. As you know it. It is user which you login on the first broker.