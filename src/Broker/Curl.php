<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Jasny\SSO\Broker;

/**
 * Wrapper for cURL.
 *
 * @codeCoverageIgnore
 */
class Curl
{
    /**
     * Curl constructor.
     *
     * @throws \Exception if curl extension isn't loaded
     */
    public function __construct()
    {
        if (!extension_loaded('curl')) {
            throw new \Exception("cURL extension not loaded");
        }
    }

    /**
     * Send an HTTP request to the SSO server.
     *
     * @param string $method HTTP method: 'GET', 'POST', 'DELETE'
     * @param string $url Full URL
     * @param string[] $headers HTTP headers
     * @param array<string,mixed>|string $data Query or post parameters
     * @return array{httpCode:int,contentType:string,body:string}
     * @throws RequestException
     */
    public function request($method, $url, $headers, $data = '')
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException("Failed to initialize a cURL session");
        }

        if ($data !== [] && $data !== '') {
            $post = is_string($data) ? $data : http_build_query($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $responseBody = (string)curl_exec($ch);

        if (curl_errno($ch) != 0) {
            throw new RequestException('Server request failed: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = $this->getContentType($ch);
        return ['httpCode' => $httpCode, 'contentType' => $contentType, 'body' => $responseBody];
    }

    private function getContentType($ch)
    {
        if (curl_getinfo($ch, CURLINFO_CONTENT_TYPE)) {
            return curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        }

        return 'text/html';
    }
}
