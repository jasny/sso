Single Sign-On for PHP (Ajax compatible)
---

Jasny\SSO is a relatively simply and strait forward solution for an single sign on (SSO) implementation. With SSO,
logging into a single website will authenticate you for all affiliate sites.

There are many single sign-on applications and protocols. Most of these are fairly complex. Applications often come with full user management solutions. This makes them difficult to integrate. Most solutions also don&#8217;t work well with AJAX, because redirection is used to let the visitor log in at the SSO server.

I&#8217;ve written a simple single sign-on solution (400 lines of code), which works by linking sessions. This solutions works for normal websites as well as AJAX sites.

## Installation and examples
The dependencies can be installed with the use of composer.

```
composer install
```

This library makes use of curl so make sure it is one of the enabled functions.
The test can be executed by starting two PHP web-servers at the root of the
package, and running `vendor/bin/codecept run`. One of the web-servers must listen to
`127.0.0.1:9000`, the other to `127.0.0.1:9001`. The first is the server and the
second is used for the broker and the client.

Similarly, to run the examples, run the two web servers, and navigate to `127.0.0.1:9001/examples/<example>`.

## Without SSO

Let&#8217;s start with a website that doesn&#8217;t have SSO.

[![No SSO](http://blog.jasny.net/wp-content/uploads/sso-diagram_no-sso1-300x252.png "sso-diagram_no-sso")](http://blog.jasny.net/wp-content/uploads/sso-diagram_no-sso.png)

The client requests the index page. The page requires that the visitor is logged in. The server creates a new session and sends redirect to the login page. After the visitor has logged in, it displays the index page.

## How it works

When using SSO, when can distinguish 3 parties:

*   Client &#8211; This is the browser of the visitor
*   Broker &#8211; The website which is visited
*   Server &#8211; The place that holds the user information

The broker will talk to the server in name of the client. For that we want the broker to use the same session as the client. However the client won&#8217;t pass the session id which it has at the server, since it&#8217;s at another domain. Instead the broker will ask the client to pass a token to the server. The server uses the token, in combination with a secret word, to create a session key which is linked session of the client. The broker also know the token and the secret word and can therefore generate the same session key, which it uses to proxy login/logout commands and request info from the server.

## First visit

[![SSO Alex](http://blog.jasny.net/wp-content/uploads/sso-diagram_alex-280x300.png "sso-diagram_alex")](http://blog.jasny.net/wp-content/uploads/sso-diagram_alex.png)

When you visit a broker website, it will check to see if a token cookie already exists. It it doesn&#8217;t it, the broker sends a redirect to the server, giving the command to attach sessions and specifying the broker identity, a random token and the originally requested URL. It saves the token in a cookie.

The server will generate a session key based on the broker identity, the secret word of the broker and the token and link this to the session of the client. The session key contains a checksum, so hackers can go out and use random session keys to grab session info. The server redirects the client back to the original URL. After this, the client can talk to the broker, the same way it does when not using SSO.

The client requests the index page at the broker. The page requires that the visitor is logged in. The broker generates the session key, using the token and the secret word, and request the user information at the server. The server responds to the broker that the visitor is not logged. The broker redirects the client to the login page.

The client logs in, sending the username and password to the broker. The broker sends the username and password to the server, again passing the session key. The server returns that login is successful to the broker. The broker redirects the client to the index page. For the index page, the broker will request the user information from the server.

## Visiting another affiliate

[![SSO Binck](http://blog.jasny.net/wp-content/uploads/sso-diagram_binck-300x238.png "sso-diagram_binck")](http://blog.jasny.net/wp-content/uploads/sso-diagram_binck.png)

#### Broker

When creating a Jasny\SSO\Broker instance, you need to pass the server url, broker id and broker secret. The broker id
and secret needs to be registered at the server (so fetched when using `getBrokerInfo($brokerId)`).

Next you need to call `attach()`. This will generate a token an redirect the client to the server to attach the token
to the client's session. If the client is already attached, the function will simply return.

When the session is attached you can do actions as login/logout or get the user's info.

SSO and AJAX / RIA applications often don&#8217;t go well together. With this type of application, you do not want to leave the page. The application is static and you get the data and do all actions through AJAX. Redirecting an AJAX call to a different website won&#8217;t because of cross-site scripting protection within the browser.

## Examples

[![SSO Ajax](http://blog.jasny.net/wp-content/uploads/sso-diagram_ajax-241x300.png "sso-diagram_ajax")](http://blog.jasny.net/wp-content/uploads/sso-diagram_ajax.png)

The client check for the token cookie. If it doesn&#8217;t exists, he requests the attach URL from the broker. This attach url includes the broker name and the token, but not a original request URL. The client will open the received url in an &lt;img&gt; and wait until the image is loaded.

    php -S localhost:9000 -t examples/server/
    export SSO_SERVER=http://localhost:9000 SSO_BROKER_ID=Alice SSO_BROKER_SECRET=8iwzik1bwd; php -S localhost:9001 -t examples/broker/
    export SSO_SERVER=http://localhost:9000 SSO_BROKER_ID=Greg SSO_BROKER_SECRET=7pypoox2pc; php -S localhost:9002 -t examples/broker/
    export SSO_SERVER=http://localhost:9000 SSO_BROKER_ID=Julias SSO_BROKER_SECRET=ceda63kmhp; php -S localhost:9003 -t examples/ajax-broker/

Now open some tabs and visit http://localhost:9001, http://localhost:9002 and http://localhost:9003.

_Note that after logging in, you need to refresh on the other brokers to see the effect._
