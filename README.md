![jasny-banner](https://user-images.githubusercontent.com/100821/62123924-4c501c80-b2c9-11e9-9677-2ebc21d9b713.png)

Single Sign-On for PHP (Ajax compatible)
========

[![PHP](https://github.com/jasny/sso/workflows/PHP/badge.svg)](https://github.com/jasny/sso/actions)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/sso/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/sso/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/sso/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/sso/?branch=master)
[![Packagist Stable Version](https://img.shields.io/packagist/v/jasny/sso.svg)](https://packagist.org/packages/jasny/sso)
[![Packagist License](https://img.shields.io/packagist/l/jasny/sso.svg)](https://packagist.org/packages/jasny/sso)

Jasny SSO is a relatively simply and straightforward solution for single sign on (SSO).

With SSO, logging into a single website will authenticate you for all affiliate sites. The sites don't need to share a
toplevel domain.

### How it works

When using SSO, when can distinguish 3 parties:

* Client - This is the browser of the visitor
* Broker - The website which is visited
* Server - The place that holds the user info and credentials

The broker has an id and a secret. These are know to both the broker and server.

When the client visits the broker, it creates a random token, which is stored in a cookie. The broker will then send
the client to the server, passing along the broker's id and token. The server creates a hash using the broker id, broker
secret and the token. This hash is used to create a link to the user's session. When the link is created the server
redirects the client back to the broker.

The broker can create the same link hash using the token (from the cookie), the broker id and the broker secret. When
doing requests, it passes that has as session id.

The server will notice that the session id is a link and use the linked session. As such, the broker and client are
using the same session. When another broker joins in, it will also use the same session.

For a more in depth explanation, please [read this article](https://github.com/jasny/sso/wiki).

### How is this different from OAuth?

With OAuth, you can authenticate a user at an external server and get access to their profile info. However, you
aren't sharing a session.

A user logs in to website foo.com using Google OAuth. Next he visits website bar.org which also uses Google OAuth.
Regardless of that, he is still required to press on the 'login' button on bar.org.

With Jasny SSO both websites use the same session. So when the user visits bar.org, he's automatically logged in.
When he logs out (on either of the sites), he's logged out for both.

## Installation

Install this library through composer

    composer require jasny/sso

## Demo

There is a demo server and two demo brokers as example. One with normal redirects and one using
[JSONP](https://en.wikipedia.org/wiki/JSONP) / AJAX.

To proof it's working you should setup the server and two or more brokers, each on their own machine and their own
(sub)domain. However, you can also run both server and brokers on your own machine, simply to test it out.

On *nix (Linux / Unix / OSX) run:

    php -S localhost:8000 -t demo/server/
    export SSO_SERVER=http://localhost:8000/attach.php SSO_BROKER_ID=Alice SSO_BROKER_SECRET=8iwzik1bwd; php -S localhost:8001 -t demo/broker/
    export SSO_SERVER=http://localhost:8000/attach.php SSO_BROKER_ID=Greg SSO_BROKER_SECRET=7pypoox2pc; php -S localhost:8002 -t demo/broker/
    export SSO_SERVER=http://localhost:8000/attach.php SSO_BROKER_ID=Julius SSO_BROKER_SECRET=ceda63kmhp; php -S localhost:8003 -t demo/ajax-broker/

Now open some tabs and visit 

  * http://localhost:8001
  * http://localhost:8002
  * http://localhost:8003

username | password
-------- | --------
jackie   | jackie123
john     | john123

_Note that after logging in, you need to refresh on the other brokers to see the effect._

# Usage

## Server

The `Server` class takes a callback as first constructor argument. This callback should lookup the secret
for a broker based on the id.

The second argument must be a PSR-16 compatible cache object. It's used to store the link between broker token and
client session.

```php
use Jasny\SSO\Server\Server;

$brokers = [
    'foo' => ['secret' => '8OyRi6Ix1x', 'domains' => ['example.com']],
    // ...
];

$server = new Server(
    fn($id) => $brokers[$id] ?? null, // Unique secret and allowed domains for each broker.
    new Cache()                       // Any PSR-16 compatible cache
);
```

_In this example the brokers are simply configured as array. But typically you want to fetch the broker info from a DB._

### Attach

A client needs attach the broker token to the session id by doing an HTTP request to the server. This request can be
handled by calling `attach()`.

The `attach()` method returns a verification code. This code must be returned to the broker, as it's needed to
calculate the checksum.

```php
$verificationCode = $server->attach();
```

If it's not possible to attach (for instance in case of an incorrect checksum), an Exception is thrown.

### Handle broker API request

After the client session is attached to the broker token, the broker is able to send API requests on behalf of the
client. Calling the `startBrokerSession()` method with start the session of the client based on the bearer token. This
means that these request the server can access the session information of the client through `$_SESSION`.

```
$server->startBrokerSession();
```

The broker could use this to login, logout, get user information, etc. The API for handling such requests is outside
the scope of the project. However since the broker uses normal sessions, any existing the authentication can be used.

_If you're lookup for an authentication library, consider using [Jasny Auth](https://github.com/jasny/auth)._

### PSR-7

By default, the library works with superglobals like `$_GET` and `$_SERVER`. Alternatively it can use a PSR-7 server
request. This can be passed to `attach()` and `startBrokerSession()` as argument.

```php
$verificationCode = $server->attach($serverRequest);
```

### Session interface

By default, the library uses the superglobal `$_SESSION` and the `php_session_*()` functions. It does this through
the `GlobalSession` object, which implements `SessionInterface`.

For projects that use alternative sessions, it's possible to create a wrapper that implements `SessionInterface`.

```php
use Jasny\SSO\Server\SessionInterface;

class CustomerSessionHandler implements SessionInterface
{
    // ...
}
```

The `withSession()` methods creates a copy of the Server object with the custom session interface.

```php
$server = (new Server($callback, $cache))
    ->withSession(new CustomerSessionHandler());
```

The `withSession()` method can also be used with a mock object for testing.

### Logging

Enable logging for debugging and catching issues.

```php
$server = (new Server($callback, $cache))
    ->withLogging(new Logger());
``` 

Any PSR-3 compatible logger can be used, like [Monolog](https://packagist.org/packages/monolog/monolog) or
[Loggy](https://packagist.org/packages/yubb/loggy). The `context` may contain the broker id, token, and session id.

## Broker

When creating a `Broker` instance, you need to pass the server url, broker id and broker secret. The broker id and
secret needs to match the secret registered at the server.

**CAVEAT**: *The broker id MUST be alphanumeric.*

### Attach

Before the broker can do API requests on the client's behalve, the client needs to attach the broker token to the client
session. For this, the client must do an HTTP request to the SSO Server.

The `getAttachUrl()` method will generate a broker token for the client and use it to create an attach URL. The method
takes an array of query parameters as single argument.

There are several methods in making the client do an HTTP request. The broker can redirect the client or do a request
via the browser using AJAX or loading an image.

```php
use Jasny\SSO\Broker\Broker;

// Configure the broker.
$broker = new Broker(
    getenv('SSO_SERVER'),
    getenv('SSO_BROKER_ID'),
    getenv('SSO_BROKER_SECRET')
);

// Attach through redirect if the client isn't attached yet.
if (!$broker->isAttached()) {
    $returnUrl = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $attachUrl = $broker->getAttachUrl(['return_url' => $returnUrl]);

    header("Location: $attachUrl", true, 303);
    echo "You're redirected to <a href='$attachUrl'>$attachUrl</a>";
    exit();
}
```

### Verify

Upon verification the SSO Server will return a verification code (as query parameter or in the JSON response). The code
is used to calculate the checksum. The verification code prevents session hijacking using an attach link.

```php
if (isset($_GET['sso_verify'])) {
    $broker->verify($_GET['sso_verify']);
}
```

### API requests

Once attached, the broker is able to do API requests on behalf of the client. This can be done by

- using the broker `request()` method, or by
- using any HTTP client like Guzzle

#### Broker request

```
// Post to modify the user info
$broker->request('POST', '/login', $credentials);

// Get user info
$user = $broker->request('GET', '/user');
```

The `request()` method uses Curl to send HTTP requests, adding the bearer token for authentication. It expects a JSON
response and will automatically decode it.

#### HTTP library (Guzzle)

To use a library like [Guzzle](http://docs.guzzlephp.org/) or [Httplug](http://httplug.io/), get the bearer token using
`getBearerToken()` and set the `Authorization` header
    
```php
$guzzle = new GuzzleHttp\Client(['base_uri' => 'https://sso-server.example.com']);

$res = $guzzle->request('GET', '/user', [
    'headers' => [
        'Authorization' => 'Bearer ' . $broker->getBearerToken()
    ]
]);
```

### Client state

By default, the Broker uses the cookies (`$_COOKIE` and `setcookie()`) via the `Cookies` class to persist the client's
SSO token.

#### Cookie

Instantiate a new `Cookies` object with custom parameters to modify things like cookie TTL, domain and https only.

```php
use Jasny\SSO\Broker\{Broker,Cookies};

$broker = (new Broker(getenv('SSO_SERVER'), getenv('SSO_BROKER_ID'), getenv('SSO_BROKER_SECRET')))
    ->withTokenIn(new Cookies(7200, '/myapp', 'example.com', true));
```

_(The cookie can never be accessed by the browser.)_

#### Session

Alternative, you can store the SSO token in a PHP session for the broker by using `SessionState`.

```php
use Jasny\SSO\Broker\{Broker,Session};

session_start();

$broker = (new Broker(getenv('SSO_SERVER'), getenv('SSO_BROKER_ID'), getenv('SSO_BROKER_SECRET')))
    ->withTokenIn(new Session());
```

#### Custom

The method accepts any object that implements `ArrayAccess`, allowing you to create a custom handler if needed.

```php
class CustomStateHandler implements \ArrayAccess
{
    // ...
}
```

This can also be used with a mock object for testing. 
