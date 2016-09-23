Single Sign-On for PHP (Ajax compatible)
---

Jasny\SSO is a relatively simply and straightforward solution for an single sign on (SSO) implementation. With SSO,
logging into a single website will authenticate you for all affiliate sites.

#### How it works

When using SSO, when can distinguish 3 parties:

* Client - This is the browser of the visitor
* Broker - The website which is visited
* Server - The place that holds the user info and credentials

The broker has an id and a secret. These are know to both the broker and server.

When the client visits the broker, it creates a random token, which is stored in a cookie. The broker will then send
the client to the server, passing along the broker's id and token. The server creates a hash using the broker id, broker
secret and the token. This hash is used to create a link to the users session. When the link is created the server
redirects the client back to the broker.

The broker can create the same link hash using the token (from the cookie), the broker id and the broker secret. When
doing requests, it passes that has as session id.

The server will notice that the session id is a link and use the linked session. As such, the broker and client are
using the same session. When another broker joins in, it will also use the same session.

For a more indepth explanation, please [read this article](https://github.com/jasny/sso/wiki).

#### How is this different from OAuth?

With OAuth, you can authenticate a user at an external server and get access to their profile info. However you
aren't sharing a session.

A user logs in to website foo.com using Google OAuth. Next he visits website bar.org which also uses Google OAuth.
Regardless of that, he is still required to press on the 'login' button on bar.org.

With Jasny/SSO both websites use the same session. So when the user visits bar.org, he's automatically logged in.
When he logs out (on either of the sites), he's logged out for both.

## Installation

Install this library through composer

    composer require jasny/sso

## Usage

#### Server

`Jasny\SSO\Server` is an abstract class. You need to create a your own class which implements the abstract methods.
These methods are called fetch data from a data souce (like a DB).

```php
class MySSOServer extends Jasny\SSO\Server
{
    /**
     * Authenticate using user credentials
     *
     * @param string $username
     * @param string $password
     * @return \Jasny\ValidationResult
     */
    abstract protected function authenticate($username, $password)
    {
        ...
    }
    
    /**
     * Get the secret key and other info of a broker
     *
     * @param string $brokerId
     * @return array
     */
    abstract protected function getBrokerInfo($brokerId)
    {
        ...
    }
    
    /**
     * Get the information about a user
     *
     * @param string $username
     * @return array|object
     */
    abstract protected function getUserInfo($username)
    {
        ...
    }
}
```

The MySSOServer class can be used as controller in an MVC framework.

Alternatively you can use MySSOServer as library class. In that case pass option `fail_exception` to the constructor.
This will make the object throw a Jasny\SSO\Exception, rather than set the HTTP response and exit.

For more information, checkout the `server` example.

#### Broker

When creating a Jasny\SSO\Broker instance, you need to pass the server url, broker id and broker secret. The broker id
and secret needs to be registered at the server (so fetched when using `getBrokerInfo($brokerId)`).

**Be careful**: *The broker id SHOULD be alphanumeric. In any case it MUST NOT contain the "-" character.*

Next you need to call `attach()`. This will generate a token an redirect the client to the server to attach the token
to the client's session. If the client is already attached, the function will simply return.

When the session is attached you can do actions as login/logout or get the user's info.

```php
$broker = new Jasny\SSO\Broker($serverUrl, $brokerId, $brokerSecret);
$broker->attach();

$user = $broker->getUserInfo();
echo json_encode($user);
```

For more information, checkout the `broker` and `ajax-broker` example.

## Examples

There is an example server and two example brokers. One with normal redirects and one using
[JSONP](https://en.wikipedia.org/wiki/JSONP) / AJAX.

To proof it's working you should setup the server and two or more brokers, each on their own machine and their own
(sub)domain. However you can also run both server and brokers on your own machine, simply to test it out.

On *nix (Linux / Unix / OSX) run:

    php -S localhost:9000 -t examples/server/
    export SSO_SERVER=http://localhost:9000 SSO_BROKER_ID=Alice SSO_BROKER_SECRET=8iwzik1bwd; php -S localhost:9001 -t examples/broker/
    export SSO_SERVER=http://localhost:9000 SSO_BROKER_ID=Greg SSO_BROKER_SECRET=7pypoox2pc; php -S localhost:9002 -t examples/broker/
    export SSO_SERVER=http://localhost:9000 SSO_BROKER_ID=Julias SSO_BROKER_SECRET=ceda63kmhp; php -S localhost:9003 -t examples/ajax-broker/

Now open some tabs and visit http://localhost:9001, http://localhost:9002 and http://localhost:9003.
usename/password
jackie/jackie123
john/john123

_Note that after logging in, you need to refresh on the other brokers to see the effect._
