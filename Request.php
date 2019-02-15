<?php
namespace Request;

class Request
{

    const REQUEST_METHOD_POST   = 'POST';
    const REQUEST_METHOD_GET    = 'GET';
    const REQUEST_METHOD_PUT    = 'PUT';
    const REQUEST_METHOD_HEAD   = 'HEAD';
    const REQUEST_METHOD_DELETE = 'DELETE';
    const REQUEST_METHOD_TRACE  = 'TRACE';
    const REQUEST_METHOD_CONNECT= 'CONNECT';

    private $url;

    public $timeout         = 30;
    public $connectTimeout  = 30;
    public $userAgent       = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72';
    public $requestType;
    public $postFields;
    public $httpHeader          = [];
    private $defaultHttpHeader  = [];

    private $responseBody;
    private $responseHeader;

    private $httpCode;
    private $error;

    public function __construct()
    {
        //todo
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function setConnectTimeout($connectTimeout)
    {
        $this->connectTimeout = $connectTimeout;
    }

    public function setRequestType($requestType)
    {
        $this->requestType = $requestType;
    }

    public function setPostFields($postFields)
    {
        $this->postFields = http_build_query($postFields);
    }

    public function setHttpHeader($httpHeader)
    {
        $this->httpHeader = $httpHeader;
    }

    public function getResponseBody()
    {
        return $this->responseBody;
    }

    public function getResponseHeader()
    {
        return $this->responseHeader;
    }

    public function execute()
    {
        $ch = curl_init();

        if ($this->requestType) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->requestType);
        }

        if ($this->requestType == self::REQUEST_METHOD_POST) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->postFields);
        }

        if ($this->requestType == self::REQUEST_METHOD_PUT) {
            curl_setopt($ch, CURLOPT_PUT, true);
        }

        if (stripos($this->url, 'https://') !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, false);
        }

        if ($this->httpHeader) {
            $this->defaultHttpHeader = array_merge($this->defaultHttpHeader, $this->httpHeader);
        }

        if ($this->defaultHttpHeader) {
            curl_setopt($ch, CURLOPT_HEADEROPT, $this->defaultHttpHeader);
        }

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response       = curl_exec($ch);
        $this->error    = curl_error($ch);

        $headerSize     = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        $this->responseHeader   = substr($response, 0, $headerSize);
        $this->responseBody     = substr($response, $headerSize);
    }

    public function download($filePath)
    {
        $fp = fopen($filePath, 'wb+');

        $ch = curl_init();

        if ($this->requestType) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->requestType);
        }

        if ($this->requestType == self::REQUEST_METHOD_POST) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->postFields);
        }

        if ($this->requestType == self::REQUEST_METHOD_PUT) {
            curl_setopt($ch, CURLOPT_PUT, true);
        }

        if (stripos($this->url, 'https://') !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, false);
        }

        if ($this->httpHeader) {
            $this->defaultHttpHeader = array_merge($this->defaultHttpHeader, $this->httpHeader);
        }

        if ($this->defaultHttpHeader) {
            curl_setopt($ch, CURLOPT_HEADEROPT, $this->defaultHttpHeader);
        }

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FILE, $fp);

        curl_exec($ch);

        curl_close($ch);
        fclose($fp);
    }
}
