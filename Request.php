<?php
namespace Tanbolt\Http;

use ErrorException;
use InvalidArgumentException;
use Tanbolt\Http\Request\File;
use Tanbolt\Http\Request\Header;
use Tanbolt\Http\Request\Server;
use Tanbolt\Http\Request\Client;
use Tanbolt\Http\Request\Parameter;
use Tanbolt\Session\SessionInterface;
use Tanbolt\Filesystem\FilesystemInterface;

/**
 * Class Request: Http Request 类
 * @package  Tanbolt\Http
 * @property-read Parameter $query
 * @property-read Parameter $request
 * @property-read Parameter $cookie
 * @property-read Parameter $attributes
 * @property-read File      $file
 * @property-read Header    $header
 * @property-read Parameter $json
 * @property-read Parameter $xml
 * @property-read Server    $server
 * @property-read Client    $client
 */
class Request
{
    /**
     * 绑定的 filesystem 对象
     * @var ?FilesystemInterface
     */
    protected $filesystem;

    /**
     * 绑定的 session 对象
     * @var SessionInterface
     */
    protected $session;

    /**
     * Request Version
     * @var float
     */
    protected $version;

    /**
     * request 方式
     * @var string
     */
    protected $method;

    /**
     * 执行脚本路径
     * @var string
     */
    protected $scriptPath;

    /**
     * base url
     * @var string
     */
    protected $baseUrl;

    /**
     * pathInfo
     * @var string
     */
    protected $pathInfo;

    /**
     * query raw string
     * @var string
     */
    protected $queryString;

    /**
     * request Header accepts
     * @var array
     */
    protected $accepts;

    /**
     * request Header charsets
     * @var array
     */
    protected $charsets;

    /**
     * request Header encodings
     * @var array
     */
    protected $encodings;

    /**
     * request Header languages
     * @var array
     */
    protected $languages;

    /**
     * request body content
     * @var string|resource
     */
    protected $body;

    /**
     * request body string
     * @var string|false|null
     */
    protected $bodyString;

    /**
     * 构建类所用 get post cookie 等数据容器
     * @var array
     */
    protected $parameters;

    /**
     * query/request/cookie/file/server/header/json/xml 类容器
     * @var array
     */
    protected $package;

    /**
     * 可信任客户端(代理服务器) ip
     * @var array
     */
    protected static $trustedClients = [];

    /**
     * 根据 http rfc7239 扩展标准, 请求者(客户端或代理服务器)可使用以下头信息传递数据
     * 注意: 这些头信息可以进行伪造, 所以应该仅用于可信任的请求者 (比如自己的均衡服务器IP)
     * @var array
     */
    protected static $forwardedHeaders = [
        'forwarded' => 'Forwarded',
        'proto'=> ['X-Forwarded-Proto', 'X-Forwarded-Protocol', 'X-Url-Scheme'],
        'ssl' => ['X-Forwarded-Ssl', 'Front-End-Https'],
        'host' => 'X-Forwarded-Host',
        'port' => 'X-Forwarded-Port',
        'for'  => ['X-Forwarded-For', 'X-ProxyUser-Ip', 'X-Real-Ip'],
    ];

    /**
     * http 请求常见类型
     * @var array
     */
    protected static $mimeTypes = [
        'html' => ['text/html', 'application/xhtml+xml'],
        'txt'  => ['text/plain'],
        'js'   => ['application/javascript', 'application/x-javascript', 'text/javascript'],
        'css'  => ['text/css'],
        'json' => ['application/json', 'application/x-json'],
        'xml'  => ['text/xml', 'application/xml', 'application/x-xml'],
        'rdf'  => ['application/rdf+xml'],
        'atom' => ['application/atom+xml'],
        'rss'  => ['application/rss+xml'],
        'form' => ['application/x-www-form-urlencoded'],
    ];

    /**
     * 获取常见 web 文件的 mimeType
     * - $format = null: 获取 mime 列表;
     * - $format = string: 指定 format (如 js,css) 的 mime
     * @param ?string $format
     * @return array|string|null
     */
    public static function getMimeType(string $format = null)
    {
        if (null === $format) {
            return static::$mimeTypes;
        }
        return isset(static::$mimeTypes[$format]) ? static::$mimeTypes[$format][0] : null;
    }

    /**
     * 由 mime 类型返回文件后缀名
     * @param ?string $mimeType
     * @return ?string
     */
    public static function getFormatByMimeType(?string $mimeType)
    {
        if (empty($mimeType)) {
            return null;
        }
        foreach (static::getMimeType() as $format => $mimeTypes) {
            if (in_array($mimeType, (array) $mimeTypes)) {
                return (string) $format;
            }
        }
        return null;
    }

    /**
     * 一次性重置所有可信任客户端 IP
     * @param array|string $ips
     */
    public static function setTrustedClient($ips)
    {
        static::$trustedClients = [];
        static::addTrustedClient($ips);
    }

    /**
     * 新增可信任客户端 IP
     * @param array|string $ips
     */
    public static function addTrustedClient($ips)
    {
        if (is_array($ips)) {
            foreach ($ips as $ip) {
                static::addOneTrustedClient($ip);
            }
        } else {
            static::addOneTrustedClient($ips);
        }
    }

    /**
     * 获取已设置的可信任客户端
     * @return array
     */
    public static function trustedClients()
    {
        return static::$trustedClients;
    }

    /**
     * 清空所有信任客户端IP
     */
    public static function clearTrustedClient()
    {
        static::$trustedClients = [];
    }

    /**
     * 新增一个可信任客户端ip
     * @param ?string $ip
     */
    private static function addOneTrustedClient(?string $ip)
    {
        $ip = $ip && '' != ($ip = trim($ip)) && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
        if (null !== $ip) {
            if (!is_array(static::$trustedClients)) {
                static::$trustedClients = [];
            }
            if (!in_array($ip, static::$trustedClients)) {
                static::$trustedClients[] = $ip;
            }
        }
    }

    /**
     * 由指定的 uri 创建 Request 实例 (主要用于单元测试)
     * @param ?string $uri
     * @param string $method
     * @param array $parameters
     * @param array $cookie
     * @param array $file
     * @param array $server
     * @param null $body
     * @param ?array $attributes
     * @return static
     */
    public static function create(
        string $uri = null,
        string $method = 'GET',
        array $parameters = [],
        array $cookie = [],
        array $file = [],
        array $server = [],
        $body = null,
        array $attributes = null
    ) {
        $method = strtoupper($method);
        $server = array_replace([
            'HTTP_ACCEPT'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_CHARSET'  => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
            'HTTP_USER_AGENT'      => 'RequestTest/1.0',
            'REMOTE_ADDR'          => '127.0.0.1',
            'SCRIPT_NAME'          => '',
            'SCRIPT_FILENAME'      => '',
            'SERVER_PROTOCOL'      => 'HTTP/1.1',
            'REQUEST_TIME'         => time(),
        ], $server, [
            'PATH_INFO'      => '',
            'REQUEST_METHOD' => $method,
        ]);

        // uri、server 参数都可以影响 scheme://host:port, 优先级为 uri > server
        $components = empty($uri = trim($uri)) ? [] : parse_url($uri);

        // port & https
        $port = null;
        if (isset($components['scheme'])) {
            if ('https' === $components['scheme']) {
                $port = 443;
                $server['HTTPS'] = 'on';
            } else {
                $port = 80;
                unset($server['HTTPS']);
            }
        }
        if (isset($components['port'])) {
            $port = $components['port'];
        } elseif (isset($server['SERVER_PORT'])) {
            $port = $server['SERVER_PORT'];
        }

        // host
        if (isset($components['host'])) {
            $server['SERVER_NAME'] = $server['HTTP_HOST'] = $components['host'];
        } elseif(isset($server['HTTP_HOST'])) {
            $host = $server['HTTP_HOST'];
            $pos = '[' === $host[0] ? strpos($host, ':', strrpos($host, ']')) : strrpos($host, ':');
            if (false !== $pos) {
                $port = (int) substr($host, $pos + 1);
                $host = substr($host, 0, $pos);
            }
            $server['HTTP_HOST'] = $server['SERVER_NAME'] = $host;
        } elseif(isset($server['SERVER_NAME'])) {
            $server['HTTP_HOST'] = $server['SERVER_NAME'];
        } else {
            $server['SERVER_NAME'] = $server['HTTP_HOST'] = 'localhost';
        }

        // host & port
        $server['SERVER_PORT'] = ($port = $port ?: 80);
        if ( (!isset($server['HTTPS']) && 80 !== $port) || (isset($server['HTTPS']) && 443 !== $port)) {
            $server['HTTP_HOST'] .= ':'.$port;
        }
        if (isset($components['user'])) {
            $server['PHP_AUTH_USER'] = $components['user'];
        }
        if (isset($components['pass'])) {
            $server['PHP_AUTH_PW'] = $components['pass'];
        }
        // query || request
        if (!isset($components['path'])) {
            $components['path'] = '/';
        }
        if (in_array($method, ['PUT', 'POST', 'DELETE', 'PATCH'])) {
            $query = [];
            $request = $parameters;
            if ('PATCH' != $method && !isset($server['CONTENT_TYPE'])) {
                $server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
            }
        } else {
            $query = $parameters;
            $request = [];
        }
        $queryString = '';
        if (isset($components['query'])) {
            parse_str(html_entity_decode($components['query']), $qs);
            if ($query) {
                $query = array_replace($qs, $query);
                $queryString = http_build_query($query);
            } else {
                $query = $qs;
                $queryString = $components['query'];
            }
        } elseif ($query) {
            $queryString = http_build_query($query);
        }
        $server['REQUEST_URI'] = $components['path'].('' !== $queryString ? '?'.$queryString : '');
        $server['QUERY_STRING'] = $queryString;
        return new static($query, $request, $cookie, $file, $server, $body, $attributes);
    }

    /**
     * 创建 Request 对象
     * @param ?array $query
     * @param ?array $request
     * @param ?array $cookie
     * @param ?array $file
     * @param ?array $server
     * @param mixed $body
     * @param ?array $attributes
     */
    public function __construct(
        array $query = null,
        array $request = null,
        array $cookie = null,
        array $file = null,
        array $server = null,
        $body = null,
        array $attributes = null
    ) {
        $this->package = [];
        $this->reset($query, $request, $cookie, $file, $server, $body, $attributes);
    }

    /**
     * 重置当前实例的参数
     * @param ?array $query
     * @param ?array $request
     * @param ?array $cookie
     * @param ?array $file
     * @param ?array $server
     * @param mixed $body
     * @param ?array $attributes
     * @return $this
     */
    public function reset(
        array $query = null,
        array $request = null,
        array $cookie = null,
        array $file = null,
        array $server = null,
        $body = null,
        array $attributes = null
    ) {
        //若参数皆未指定 则认为是通过 GLOBALS 参数进行实例化
        if (null === $query && null === $request && null === $cookie && null === $file && null === $server) {
            $query = $_GET;
            $request = $_POST;
            $cookie = $_COOKIE;
            $file = $_FILES;
            $server = $_SERVER;
            // With the php's bug #66606, the php's built-in web server
            if ('cli-server' === php_sapi_name()) {
                if (array_key_exists('HTTP_CONTENT_LENGTH', $_SERVER)) {
                    $server['CONTENT_LENGTH'] = $_SERVER['HTTP_CONTENT_LENGTH'];
                }
                if (array_key_exists('HTTP_CONTENT_TYPE', $_SERVER)) {
                    $server['CONTENT_TYPE'] = $_SERVER['HTTP_CONTENT_TYPE'];
                }
            }
        } else {
            $query = $query ?: [];
            $request = $request ?: [];
            $cookie = $cookie ?: [];
            $file = $file ?: [];
            $server = $server ?: [];
        }
        $attributes = $attributes ?: [];
        $this->version = null;
        $this->method = null;
        $this->scriptPath = null;
        $this->baseUrl = null;
        $this->pathInfo = null;
        $this->queryString = null;
        $this->accepts = null;
        $this->charsets = null;
        $this->encodings = null;
        $this->languages = null;
        $this->body = $body;
        $this->bodyString = false;
        $this->parameters = compact('query', 'request', 'cookie', 'file', 'server', 'attributes');
        foreach ($this->package as $key => $package) {
            $this->package[$key]['reset'] = true;
        }
        if ($this->session) {
            $this->session->setRequestCookies($this->cookie->all());
        }
        return $this;
    }

    /**
     * 使用当前实例初始化时的 $parameters 重新创建一个新实例.
     * @return $this
     */
    public function rebuild()
    {
        return new static(
            $this->parameters['query'],  $this->parameters['request'],
            $this->parameters['cookie'], $this->parameters['file'],
            $this->parameters['server'], $this->body,
            $this->parameters['attributes']
        );
    }

    /**
     * 用于 request->file 的 UploadFile 对象, 可通过 Filesystem 上传文件
     * @param ?FilesystemInterface $filesystem
     * @return $this
     */
    public function withFilesystem(FilesystemInterface $filesystem = null)
    {
        $this->filesystem = $filesystem;
        if (isset($this->package['file'])) {
            $this->package['file']['obj']->filesystem($filesystem);
        }
        return $this;
    }

    /**
     * 获取当前绑定的 Filesystem 对象
     * @return FilesystemInterface|null
     */
    public function filesystem()
    {
        return $this->filesystem;
    }

    /**
     * 设置 SessionInterface 对象，可自动提取 request cookie 注入到 session
     * > 这在使用 fastCgi 模式运行的 php 必要性不大，因为 Session 会自动从 $_COOKIE 提取，
     * 但在使用 cli 模式运行时，因为进程是复用的，该功能就比较有用了
     * @param ?SessionInterface $session
     * @return $this
     */
    public function withSession(SessionInterface $session = null)
    {
        if ($session) {
            $session->setRequestCookies($this->cookie->all());
        } elseif ($this->session) {
            $this->session->setRequestCookies(false);
        }
        $this->session = $session;
        return $this;
    }

    /**
     * 获取当前绑定的 session 对象
     * @return SessionInterface|null
     */
    public function session()
    {
        return $this->session;
    }

    /**
     * 获取 HTTP 协议版本
     * @return float
     */
    public function protocolVersion()
    {
        if (null === $this->version) {
            $ver = 1.0;
            if (null !== $httpVersion = $this->server->get('SERVER_PROTOCOL')) {
                if (preg_match('#^HTTP/(\d+\.?\d+)#i', $httpVersion, $matches)) {
                    $ver = (float) $matches[1];
                }
            }
            $this->version = $ver;
        }
        return $this->version;
    }

    /**
     * 重置 HTTP 协议版本
     * @param float|string|null $version
     * @return $this
     */
    public function setProtocolVersion($version)
    {
        $this->version = null === $version ? null : (float) $version;
        return $this;
    }

    /**
     * 获取 HTTP Method，可能是 X-METHOD-OVERRIDE 首部指定 或 手动设置的
     * @return string
     */
    public function method()
    {
        if (!$this->method) {
            $method = $this->realMethod();
            if ('POST' === $method &&
                $overrideMethod = $this->header->getLast(['X-METHOD-OVERRIDE', 'X-HTTP-METHOD-OVERRIDE'])
            ) {
                $method = strtoupper($overrideMethod);
            }
            $this->method = $method;
        }
        return $this->method;
    }

    /**
     * 获取请求实际使用的 HTTP Method
     * @return string
     */
    public function realMethod()
    {
        return strtoupper($this->server->get('REQUEST_METHOD', 'GET'));
    }

    /**
     * 重置 HTTP Method
     * @param string $method
     * @return $this
     */
    public function setMethod(string $method)
    {
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * 是否 GET
     * @return bool
     */
    public function isGet()
    {
        return 'GET' == $this->method();
    }

    /**
     * 是否 HEAD
     * @return bool
     */
    public function isHead()
    {
        return 'HEAD' == $this->method();
    }

    /**
     * 是否 POST
     * @return bool
     */
    public function isPost()
    {
        return 'POST' == $this->method();
    }

    /**
     * 是否 PUT
     * @return bool
     */
    public function isPut()
    {
        return 'PUT' == $this->method();
    }

    /**
     * 是否为安全的 Method (语义上为 只读 的 Method)
     * @return bool
     */
    public function isSafeMethod()
    {
        return in_array($this->method(), ['GET', 'HEAD', 'OPTIONS', 'TRACE']);
    }

    /**
     * 是否为指定的 Method, 可指定为数组, 匹配其一即为 true
     * @param array|string $method
     * @return bool
     */
    public function isMethod($method)
    {
        if (is_array($method)) {
            return in_array($this->method(), array_map('strtoupper', $method));
        }
        return 0 == strcasecmp($method, $this->method());
    }

    /**
     * 当前请求者是否为可信任ip
     * @return bool
     */
    public function isTrustedClient()
    {
        return is_array(static::$trustedClients) && in_array($this->server->get('REMOTE_ADDR'), static::$trustedClients);
    }

    /**
     * 获取客户端 IP, 返回用户用户真实 IP 或 第一个代理 IP (若代理IP在信任列表)
     * @return ?string
     */
    public function ip()
    {
        $ip = null;
        if ($this->isTrustedClient()) {
            $ips = $this->ips();
            $ip = $ips[0] ?? null;
        }
        return $ip ?: $this->server->get('REMOTE_ADDR');
    }

    /**
     * 获取客户端所有代理 ip
     * @return array
     */
    public function ips()
    {
        $forwarded = [];
        if ($val = $this->header->getLast(static::$forwardedHeaders['for'])) {
            $forwarded = explode(',', $val);
        } elseif (($val = $this->header->getLast(static::$forwardedHeaders['forwarded'])) &&
            preg_match_all('#for=("?\[?)([a-z0-9.:_\-/]*)#i', $val, $matches)
        ) {
            $forwarded = $matches[2];
        }
        $ips = [];
        foreach ($forwarded as $ip) {
            if ('' != ($ip = trim($ip)) && filter_var($ip, FILTER_VALIDATE_IP)) {
                $ips[] = $ip;
            }
        }
        return $ips;
    }

    /**
     * 获取 Header 的 If-None-Match
     * @return array|false
     */
    public function eTags()
    {
        $eTags = $this->header->getLast('If-None-Match');
        return $eTags ? preg_split('/\s*,\s*/', $eTags, null, PREG_SPLIT_NO_EMPTY) : false;
    }

    /**
     * 重置 Header 的 ETags
     * @param ?string $etag
     * @return $this
     */
    public function setETags(?string $etag)
    {
        if (null === $etag) {
            $this->header->remove('If-None-Match');
        } else {
            if (0 !== strpos($etag, '"') && 0 !== strpos($etag, 'W/')) {
                $etag = '"'.$etag.'"';
            }
            $this->header->set('If-None-Match', $etag);
        }
        return $this;
    }

    /**
     * 获取请求的来源站点
     * @return ?string
     */
    public function origin()
    {
        return $this->header->getLast('Origin');
    }

    /**
     * 获取请求的来源地址
     * @return ?string
     */
    public function referrer()
    {
        return $this->header->getLast('Referer');
    }

    /**
     * 当前是否跨域请求, 对于没有 Origin 或 Referrer header 的请求 认为不是跨站
     * @return bool
     */
    public function isCross()
    {
        if (!($from = $this->header->getLast(['Referer', 'Origin']))) {
            return false;
        }
        if (!($from = parse_url($from)) || !isset($from['scheme']) || !isset($from['host'])) {
            return false;
        }
        $port = $from['port'] ?? null;
        return $this->httpHost() !== $from['scheme'].'://'.$from['host'].($port ? ':'.$port : '');
    }

    /**
     * 是否 ajax 请求
     * @return bool
     */
    public function isAjax()
    {
        return 'XMLHttpRequest' == $this->header->getLast('X-Requested-With');
    }

    /**
     * 当前 Request 可接受的所有 Accept header 响应
     * @return array
     */
    public function accepts()
    {
        if (null === $this->accepts) {
            $this->accepts = static::getAcceptAttrs($this->header->getLast('Accept', ''));
        }
        return $this->accepts;
    }

    /**
     * 当前 Request 最佳 Accept header 响应
     * @param array|string|null $accepts
     * @return ?string
     */
    public function preferredAccepts($accepts = null)
    {
        $accepts = is_array($accepts) ? $accepts : (is_string($accepts) ? explode(',', $accepts) : null);
        return static::getPreferredAccept($this->accepts(), $accepts);
    }

    /**
     * 当前 Request 可接受的所有 Accept-Encoding header 响应
     * @return array
     */
    public function encodings()
    {
        if (null === $this->encodings) {
            $this->encodings = static::getAcceptAttrs($this->header->getLast('Accept-Encoding', ''));
        }
        return $this->encodings;
    }

    /**
     * 当前 Request 最佳 Accept-Encoding header 响应
     * @param array|string|null $encodings
     * @return ?string
     */
    public function preferredEncoding($encodings = null)
    {
        $encodings = is_array($encodings) ? $encodings : (is_string($encodings) ? explode(',', $encodings) : null);
        return static::getPreferredAccept($this->encodings(), $encodings);
    }

    /**
     * 当前 Request 可接受的所有 Accept-Language header 响应
     * @return array
     */
    public function languages()
    {
        if (null === $this->languages) {
            $this->languages = static::getAcceptAttrs($this->header->getLast('Accept-Language', ''));
        }
        return $this->languages;
    }

    /**
     * 当前 Request 最佳 Accept-Language header 响应
     * @param array|string|null $languages
     * @return ?string
     */
    public function preferredLanguage($languages = null)
    {
        $languages = is_array($languages) ? $languages : (is_string($languages) ? explode(',', $languages) : null);
        $languagesExtends = null;
        if (is_array($languages)) {
            $languagesExtends = [];
            $languagesLower = array_map('strtolower', $languages);
            foreach ($languages as $lang) {
                if (false === $pos = strpos($lang, '_')) {
                    $pos = strpos($lang, '-');
                }
                if (false !== $pos) {
                    $langLower = strtolower(substr($lang, 0, $pos));
                    if (!in_array($langLower, $languagesLower) && !isset($languagesExtends[$langLower])) {
                        $languagesExtends[$langLower] = $lang;
                    }
                }
            }
        }
        return static::getPreferredAccept($this->languages(), $languages, $languagesExtends);
    }

    /**
     * 当前 Request 可接受的所有 Accept-Charset header 响应
     * @return array
     */
    public function charsets()
    {
        if (null === $this->charsets) {
            $this->charsets = static::getAcceptAttrs($this->header->getLast('Accept-Charset', ''));
        }
        return $this->charsets;
    }

    /**
     * 当前 Request 最佳 Accept-Charset header 响应
     * @param array|string|null $charsets
     * @return ?string
     */
    public function preferredCharset($charsets = null)
    {
        $charsets = is_array($charsets) ? $charsets : (is_string($charsets) ? explode(',', $charsets) : null);
        return static::getPreferredAccept($this->charsets(), $charsets);
    }

    /**
     * 获取请求实体的 Content-Type  (text/html,text/json...)
     * @return ?string
     */
    public function contentType()
    {
        $mimeType = $this->header->getLast('Content-Type');
        if ($mimeType && false !== $pos = strpos($mimeType, ';')) {
            $mimeType = (string) substr($mimeType, 0, $pos);
        }
        return $mimeType;
    }

    /**
     * 重置 Header 的 Content-Type  (text/html,text/json...)
     * @param ?string $contentType
     * @return $this
     */
    public function setContentType(?string $contentType)
    {
        if (null === $contentType) {
            $this->header->remove('Content-Type');
        } else {
            $this->header->set('Content-Type', $contentType);
        }
        return $this;
    }

    /**
     * 获取请求实体的 Format  (html,json...)
     * @return ?string
     */
    public function format()
    {
        return static::getFormatByMimeType($this->contentType());
    }

    /**
     * 重置 Header 的 Format  (html,json...)
     * @param ?string $format
     * @return $this
     */
    public function setFormat(?string $format)
    {
        if (null === $format) {
            $this->setContentType(null);
        } elseif (null !== $newMimeType = static::getMimeType($format)) {
            $this->setContentType($newMimeType);
        }
        return $this;
    }

    /**
     * Request 的实体消息为 json
     * @return bool
     */
    public function isJson()
    {
        $contentType = $this->header->getLast('Content-Type');
        return $contentType && (strpos($contentType, '/json') || strpos($contentType, '/x-json'));
    }

    /**
     * Request 的实体消息为 xml
     * @return bool
     */
    public function isXml()
    {
        $contentType = $this->header->getLast('Content-Type');
        return $contentType && (strpos($contentType, '/xml') || strpos($contentType, '/x-xml'));
    }

    /**
     * 获取请求实体 raw data
     * @return ?string
     */
    public function body()
    {
        if (false === $this->bodyString) {
            if (is_resource($this->body)) {
                rewind($this->body);
                $requestBody = stream_get_contents($this->body);
            } elseif (null === $this->body) {
                $requestBody = file_get_contents('php://input');
            } else {
                $requestBody = (string) $this->body;
            }
            $this->bodyString = strlen($requestBody) ? $requestBody : null;
        }
        return $this->bodyString;
    }

    /**
     * 重置 Request raw data
     * @param mixed $content 可设置为 resource 或其他可通过 (string) 转换的值
     * @return $this
     */
    public function setBody($content)
    {
        $this->body = $content;
        $this->bodyString = false;
        // 重置魔术变量 $json  $xml
        $this->resetPackage('json')->resetPackage('xml');
        return $this;
    }

    /**
     * 获取当前执行脚本路径
     * @return string
     * @throws
     */
    public function script()
    {
        if (null === $this->scriptPath) {
            $this->scriptPath = $this->getScript();
        }
        return $this->scriptPath;
    }

    /**
     * Request 是否使用 https 访问
     * @return bool
     */
    public function isHttps()
    {
        // 可信 IP 代理转发
        if ($this->isTrustedClient()) {
            $val = $this->header->getLast(static::$forwardedHeaders['proto']);
            if (!$val) {
                $val = $this->header->getLast(static::$forwardedHeaders['forwarded']);
                $val = preg_match('#proto=(http|https)\b#i', $val, $matches) ? trim($matches[1]) : null;
            }
            if ($val) {
                return 0 === strcasecmp($val, 'https');
            }
            $val = $this->header->getLast(static::$forwardedHeaders['ssl']);
            if (null !== $val) {
                return false !== filter_var($val, FILTER_VALIDATE_BOOLEAN);
            }
        }
        return false !== filter_var($this->server->get('HTTPS', 'off'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * 完整的请求 url (ex : http://www.domin.com:8080/public/index.phpfoo/bar?foo=bar)
     * @return string
     *
     * 关于 url 相关方法实现参考下图
     *
     *
     *                              root
     * ┌─────────────────────────────────────────────────────────────────┐
     * │                                                                 │
     * │              httpHost                                           │
     * ┌─────────────────────────────────────┐                           │
     * │                     portHost        │           baseUrl         │
     * │              ┌──────────────────────┐  ┌────────────────────────┐
     * │              │                      │  │                        │
     * │ scheme       │  host            port│  │ basePath               │        pathInfo           queryString
     * ┌───────┐     ┌──────────────┐   ┌────┐  ┌───────────┐            │ ┌───────────────────┐    ┌───────────┐
     *   https   ://  www.domain.com  :  8080    /  public    / index.php    /   foo   /   bar    ?   foo = bar
     * │                                      └────────────────────────────────────────────────┘                │
     * │                                      │                     fullPath                                    │
     * │                                      │                                                                 │
     * │                                      └─────────────────────────────────────────────────────────────────┘
     * │                                                                     uri                                │
     * │                                                                                                        │
     * └────────────────────────────────────────────────────────────────────────────────────────────────────────┘
     *                                             url
     *
     *
     * 以下接口实现上图所示的功能, 另外额外多出三个方法, 方便使用, 参见注释
     * decodeQuery / decodePath / pathArr
     */
    public function url()
    {
        return $this->httpHost().$this->uri();
    }

    /**
     * URL协议, (http | https)
     * @return string
     */
    public function scheme()
    {
        return $this->isHttps() ? 'https' : 'http';
    }

    /**
     * 域名 host 或 ip (ipv6 使用 [] 包裹) (ex: www.domain.com, 123.34.567.89, [2001:db8:cafe::17])
     * @return string|null
     */
    public function host()
    {
        if (!$this->isTrustedClient() || !($host = $this->forwardedHost())) {
            $host = $this->header->getLast('HOST');
        }
        if (!$host) {
            $host = $this->server->get(['SERVER_NAME', 'SERVER_ADDR']);
        }
        if ($host) {
            // 去掉端口
            $host = strtolower(preg_replace('/:\d+$/', '', trim($host)));
            // 校验 Host 是否全部为合法字符
            if ($host && '' !== preg_replace('/(?:^\[)?[a-zA-Z0-9-:\]_]+\.?/', '', $host)) {
                $host = null;
            }
        }
        return $host;
    }

    /**
     * 获得代理转发的 host:port
     * @return string|null
     */
    protected function forwardedHost()
    {
        $val = $this->header->getLast(static::$forwardedHeaders['host']);
        if (!$val) {
            $val = $this->header->getLast(static::$forwardedHeaders['forwarded']);
            $val = preg_match('#host=([^;]+)#i', $val, $matches) ? trim($matches[1]) : null;
        }
        return $val;
    }

    /**
     * 端口 (ex: 80|443|...)
     * @return int
     */
    public function port()
    {
        $host = null;
        if ($this->isTrustedClient()) {
            // forwarded port
            if ($val = $this->header->getLast(static::$forwardedHeaders['port'])) {
                return (int) $val;
            }
            // 从 forwarded host 提取
            $host = $this->forwardedHost();
        }
        // 使用实际的 host
        if (!$host) {
            $host = $this->header->getLast('HOST');
        }
        if ($host) {
            // 兼容 ipv6 host
            $pos = '[' === $host[0] ? strpos($host, ':', strrpos($host, ']')) : strrpos($host, ':');
            if (false !== $pos) {
                return (int) substr($host, $pos + 1);
            }
            return $this->isHttps() ? 443 : 80;
        }
        return (int) $this->server->get('SERVER_PORT', 80);
    }

    /**
     * 域名 + 端口 (ex: www.domain.com:8080)
     * @return string
     */
    public function portHost()
    {
        $port = $this->port();
        $isHttps = $this->isHttps();
        return $this->host().((!$isHttps && 80 == $port) || ($isHttps && 443 == $port) ? '' : ':'.$port);
    }

    /**
     * 协议 + 域名 + 端口 (ex: http://www.domain.com:8080)
     * @return string
     */
    public function httpHost()
    {
        return $this->scheme().'://'.$this->portHost();
    }

    /**
     * 当前应用根目录地址 (ex: http://www.domin.com:8080/public/index.php)
     * @return string
     */
    public function root()
    {
        return $this->httpHost().$this->baseUrl();
    }

    /**
     * 请求 url 中域名之后的 uri (path + queryString), 上述示例图为普通模式；
     * 实际使用中可能会开启伪静态功能，实际获取就会有所不同，以下举例说明。
     *
     * example :
     *       DOCUMENT_ROOT  >  /data/site/
     *      SCRIPT_FILENAME >  /data/site/public/index.php
     *
     * Normal
     *           url >>>>>>>> /public/index.php/foo/bar/hello world?foo=hello world
     *                  uri : /public/index.php/foo/bar/hello%20world?foo=hello%20world
     *             fullPath : /public/index.php/foo/bar/hello%20world
     *              baseUrl : /public/index.php
     *             basePath : /public
     *             pathInfo : /foo/bar/hello%20world
     *           decodePath : /foo/bar/hello world
     *          queryString : foo=hello%20world
     *          decodeQuery : foo=hello world
     *
     * Rewrite A ( ^public/(.*)$  =>  public/index.php/$1 )
     *      view url >>>>>>>> /public/foo/bar/hello world?foo=hello world
     *                  uri : /public/foo/bar/hello%20world?foo=hello%20world
     *             fullPath : /public/foo/bar/hello%20world
     *              baseUrl : /public
     *             basePath : /public
     *             pathInfo : /foo/bar/hello%20world
     *           decodePath : /foo/bar/hello world
     *          queryString : foo=hello%20world
     *          decodeQuery : foo=hello world
     *
     * Rewrite B ( ^(.*)$  =>  public/index.php/$1 )
     *      view url >>>>>>>> /public/foo/bar/hello world?foo=hello world
     *                  uri : /public/foo/bar/hello%20world?foo=hello%20world
     *             fullPath : /public/foo/bar/hello%20world
     *              baseUrl : ''
     *             basePath : ''
     *             pathInfo : /public/foo/bar/hello%20world
     *           decodePath : /public/foo/bar/hello world
     *          queryString : foo=hello%20world
     *          decodeQuery : foo=hello world
     *
     * @return string
     */
    public function uri()
    {
        $queryString = $this->queryString();
        $fullPath = $this->fullPath();
        if ('/' === $fullPath) {
            return empty($queryString) ? '/' : '?'.$queryString;
        }
        return $fullPath . (empty($queryString) ? '' : '?'.$queryString);
    }

    /**
     * 请求 URL 中的 path, 总是以 "/" 开头 (raw url, ex: /public/index.php/foo/bar/hello%20world)
     * @see RequestInterface::uri example
     * @return string
     */
    public function fullPath()
    {
        $pathInfo = $this->pathInfo();
        $baseUrl = $this->baseUrl();
        return empty($baseUrl) ? $pathInfo : $baseUrl . ('/' === $pathInfo ? '' : $pathInfo);
    }

    /**
     * path 中属于执行脚本的部分 (如有文件名,将包含文件名, ex: /public/index.php)
     * @see RequestInterface::uri example
     * @return string
     */
    public function baseUrl()
    {
        if (null === $this->baseUrl) {
            $this->baseUrl = $this->getRequest('baseUrl');
        }
        return $this->baseUrl;
    }

    /**
     * path 中属于执行脚本的路径 (不含最终文件名 总是以 "/" 开头, 末尾无 "/")
     * > 即实际执行脚本 baseUrl() 去除文件名, 若 baseUrl() 不含文件名,两者相等 (ex: /public)
     * @see RequestInterface::uri example
     * @return string
     */
    public function basePath()
    {
        $baseUrl = $this->baseUrl();
        if (empty($baseUrl)) {
            return '';
        }
        if (basename($baseUrl) === basename($this->script())) {
            return rtrim(dirname($baseUrl), '/');
        }
        return $baseUrl;
    }

    /**
     * PATH-INFO (raw url 中包含的中文将保持原样) (总是以 "/" 开头, 末尾无 "/", ex: /foo/bar/hello%20world)
     * @see RequestInterface::uri example
     * @return string
     */
    public function pathInfo()
    {
        if (null === $this->pathInfo) {
            $this->pathInfo = $this->getRequest('pathInfo');
        }
        return $this->pathInfo;
    }

    /**
     * pathInfo 的 rawurldecode 版本 (ex: /foo/bar/hello world)
     * @see RequestInterface::uri example
     * @return string
     */
    public function decodePath()
    {
        return urldecode($this->pathInfo());
    }

    /**
     * pathInfo 转数组
     * @return array
     */
    public function pathArr()
    {
        return '' === ($path = ltrim($this->decodePath(), '/')) ? [] : explode('/', $path);
    }

    /**
     * 重置 pathInfo, 一般路由都以 pathInfo 作为输入源，在某些时候可能需要重置以实现组件代理
     * @param string $path
     * @return $this
     */
    public function setPathInfo(string $path)
    {
        $this->pathInfo = '/'.trim(static::rawEncodePathInfo($path), '/');
        return $this;
    }

    /**
     * 请求 URL 的 queryString (raw url, ex: foo=hello%20world)
     * @see RequestInterface::uri example
     * @return string
     */
    public function queryString()
    {
        if (null === $this->queryString) {
            $this->queryString = $this->getRequest('queryString');
        }
        return $this->queryString;
    }

    /**
     * queryString 的 rawUrl Decode 版本 (ex: foo=hello world)
     * @return string
     */
    public function decodeQuery()
    {
        return rawurldecode($this->queryString());
    }

    /**
     * 获取当前执行脚本的路径
     * @return string
     * @throws ErrorException
     */
    protected function getScript()
    {
        $scriptFile = $this->server->get('SCRIPT_FILENAME');
        if (empty($scriptFile)) {
            return '';
        }
        $scriptName = basename($scriptFile);
        $selfName = $this->server->get(['SCRIPT_NAME','PHP_SELF','ORIG_SCRIPT_NAME']);
        if (basename($selfName) === $scriptName) {
            $script = $selfName;
        } elseif (
            ($script = $this->server->get('SCRIPT_NAME')) &&
            ($phpSelf = $this->server->get(['PHP_SELF', 'ORIG_PATH_INFO'])) &&
            false !== ($pos = strpos($phpSelf, '/'.$scriptName))
        ) {
            $script = substr($script, 0, $pos) . '/' . $scriptName;
        } elseif (($root = $this->server->get('DOCUMENT_ROOT')) && 0 === strpos($scriptFile, $root)) {
            $script = substr($scriptFile, strlen($root));
        } else {
            throw new ErrorException("unable to determine the entry script URL.");
        }
        return '/' . trim(static::correctPathSeparator($script), '/');
    }

    /**
     * 获取当前访问 url 中的 baseUrl || pathInfo || query
     * @param string $key
     * @return string
     */
    protected function getRequest(string $key)
    {
        $query = $this->server->get('QUERY_STRING', '');
        $queryString = empty($query) ? '' : static::rawEncodeQueryString($query);
        if ('queryString' === $key) {
            return $queryString;
        }
        $script = $this->script();
        $fullPath = $this->getRequestUri($query);
        if ('' != $fullPath && $pos = strpos($fullPath, '?')) {
            // Remove unnecessary slashes
            $fullPath = preg_replace('#/+#', '/', substr($fullPath, 0, $pos));
        }
        $fullPath = static::rawEncodePathInfo($fullPath);
        $pathTest = $this->server->get('PATH_INFO');
        if (!$pathTest && !empty($script) && ($phpSelf = $this->server->get(['PHP_SELF', 'ORIG_PATH_INFO'])) &&
            0 === strpos($phpSelf, $script)
        ) {
            $pathTest = substr($phpSelf, strlen($script));
        }
        if ($pathTest) {
            // pathTest 已经是 PATH-INFO 了
            // 但 IIS7 + cgi + rewrite 模式下, PATH-INFO 的若有中文字符可能为乱码, 而 fullPath 中确包含准确的 encode 路径
            // 所以通过对比截取方式获取, 确保兼容环境
            $fullArr = explode('/', $fullPath);
            $pathArr = explode('/', $pathTest);
            if (count($fullArr) <= count($pathArr)) {
                $pathInfo = $fullPath;
            } else {
                $pathInfo = '/' . implode('/', array_slice($fullArr, (count($fullArr) - count($pathArr) + 1)) );
            }
        } elseif (!empty($script) && 0 === strpos($fullPath, $script)) {
            // 在未使用伪静态情况下,这样也是可以安全获取的
            $pathInfo = substr($fullPath, strlen($script));
        } elseif ('' === ($baseUrl = rtrim(dirname($script),'\\/')) || 0 === strpos($fullPath, $baseUrl) ) {
            // 该方案为下下策 用于一般的伪静态逻辑没问题 但用在特殊的伪静态规则上 不一定能返回正确的结果
            // 例如 Rewrite B 方案   ^(.*)$  =>  public/index.php/$1
            // 访问 /public/foo/bar 的正确 PATH_INFO 应该是 /public/foo/bar 但此处会返回 /foo/bar
            // 考虑到一般情况设置伪静态规则是  ^public/(.*)$  =>  public/index.php/$1 所以将该方式作为最后的备选方案
            $pathInfo = substr($fullPath, strlen($baseUrl));
        } else {
            // set default /
            $pathInfo = '/';
            //throw new \ErrorException("unable to determine the path info.");
        }
        $this->pathInfo = empty($pathInfo) ? '/' : '/'. trim($pathInfo, '/');
        $this->baseUrl = empty($pathInfo) ? $fullPath : substr($fullPath, 0, (-1 * strlen($pathInfo)));
        return 'pathInfo' === $key ? $this->pathInfo : $this->baseUrl;
    }

    /**
     * 获取当前访问的 URI
     * @param string $query
     * @return string
     */
    protected function getRequestUri(string $query)
    {
        $software = $this->server->get('SERVER_SOFTWARE', '');
        $isIIS = false !== stripos($software, 'iis/');
        if ($isIIS && $requestUri = $this->header->getLast('X_REWRITE_URL')) {
            // IIS + ISAPI_Rewrite
            $this->header->remove('X_REWRITE_URL');
        } elseif ($isIIS && $requestUri = $this->header->getLast('X_ORIGINAL_URL')) {
            // IIS + Microsoft Rewrite Module
            $this->header->remove('X_ORIGINAL_URL');
            $this->server->remove(['HTTP_X_ORIGINAL_URL', 'UNENCODED_URL', 'IIS_WasUrlRewritten']);
        } elseif ($isIIS && '1' === $this->server->get('IIS_WasUrlRewritten') &&
            $requestUri = $this->server->get('UNENCODED_URL', '')
        ) {
            // IIS7 + URL Rewrite
            $this->server->remove(['UNENCODED_URL', 'IIS_WasUrlRewritten']);
        } else {
            if ($requestUri = $this->server->get('REQUEST_URI')) {
                // HTTP proxy reqs setup request URI with scheme and host [and port] + the URL path, only use URL path
                $schemeAndHttpHost = $this->httpHost();
                if (0 === strpos($requestUri, $schemeAndHttpHost)) {
                    $requestUri = substr($requestUri, strlen($schemeAndHttpHost));
                }
            } elseif ($requestUri = $this->server->get(['PHP_SELF','ORIG_PATH_INFO'])) {
                // IIS PHP as CGI
                $requestUri .= empty($query) ? '' : '?'.$query;
            }
            // IIS7 FastCgi 环境 REQUEST_URI  ORIG_PATH_INFO 包含中文会是乱码 (IIS6 没问题)
            // 补丁地址: https://support.microsoft.com/zh-cn/kb/2277918
            // 考虑到生产环境使用 IIS7 且不使用伪静态的概率较低 此处仅针对 GBK 作简单兼容
            // !!注意 这部分并不能确保编码安全转换 这种事还是需要从服务器端下手解决才是正道
            if (false !== stripos($software, 'iis/7') && preg_match('/([\x00-\x1F\x80-\xFF]+)/', $requestUri, $test) &&
                !(function_exists('mb_check_encoding') ? mb_check_encoding($test[0], 'UTF-8') : preg_match('!!u', $test[0]))
            ){
                $inCharset = version_compare(PHP_VERSION, '5.4.0') >= 0 ? 'GB18030' : 'GBK';
                if (function_exists('mb_convert_encoding')) {
                    $requestUri = mb_convert_encoding($requestUri, 'UTF-8', $inCharset);
                } elseif (function_exists('iconv') && ($testValue = iconv($inCharset, 'UTF-8//IGNORE', $requestUri))) {
                    $requestUri = $testValue;
                }
            }
        }
        return $requestUri ? '/' . ltrim($requestUri, '/') : '';
    }

    /**
     * 返回 Header 中 Accept 头数组
     * 且数组中总会有 index q 字段, index 是其原始位置,  数组以 q 降序排列
     *
     * ex : Accept: audio/*; q=0.2, audio/basic, audio/mpeg;q=0.8;bitrate=256;
     *
     * rs :
     *  [
     *      'audio/basic' = [
     *          'index' => 1,
     *          'q' => 1
     *      ],
     *      'audio/mpeg' =  [
     *          'index' => 2,
     *          'q' => 0.8,
     *          'bitrate'  => 256,
     *      ],
     *      'audio/*' =  [
     *          'index' => 0,
     *          'q' => 0.2
     *      ],
     *  ]
     *
     * 代码参考 symfony (http-foundation 2.8)
     * Licensed under the MIT/X11 License (http://opensource.org/licenses/MIT)
     * Copyright (c) 2004-2015 Fabien Potencierm
     * @param $header
     * @return array
     * @link https://github.com/symfony/http-foundation/blob/2.8/AcceptHeaderItem.php
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html  RFC 2616
     */
    protected static function getAcceptAttrs($header)
    {
        $segments = [];
        $matches = preg_split('/\s*(?:,*("[^"]+"),*|,*(\'[^\']+\'),*|,+)\s*/',
            $header, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        foreach($matches as $index=>$match) {
            $bits = preg_split('/\s*(?:;*("[^"]+");*|;*(\'[^\']+\');*|;+)\s*/',
                $match, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            $key = array_shift($bits);
            if (substr($key, 0, 1) === substr($key, -1) && ('"' === $key || '\'' === $key)) {
                $key = substr($key, 1, -1);
            }
            $attrs = [
                'index' => $index
            ];
            $lastNullAtt = null;
            foreach ($bits as $bit) {
                if (($start = substr($bit, 0, 1)) === ($end = substr($bit, -1)) && ('"' === $start || '\'' === $start)) {
                    $k = $lastNullAtt;
                    $v = substr($bit, 1, -1);
                } elseif ('=' === $end) {
                    $k = $lastNullAtt = substr($bit, 0, -1);
                    $v = null;
                } else {
                    $parts = explode('=', $bit);
                    $k = $parts[0];
                    $v = isset($parts[1]) && strlen($parts[1]) > 0 ? $parts[1] : '';
                }
                $attrs[$k] = 'q' === $k ? (float) $v : (string) $v;
            }
            if (!isset($attrs['q'])) {
                $attrs['q'] = 1;
            }
            $segments[$key] = $attrs;
        }
        uasort($segments, function ($a, $b) {
            if ($a['q'] === $b['q']) {
                return $a['index'] > $b['index'] ? 1 : -1;
            }
            return $a['q'] > $b['q'] ? -1 : 1;
        });
        return $segments;
    }

    /**
     * 匹配最佳接受类型
     * @param array $accepts
     * @param array|null $locales
     * @param array|null $localesExtend
     * @return string|null
     */
    protected static function getPreferredAccept(array $accepts, array $locales = null, array $localesExtend = null)
    {
        $accepts = array_keys($accepts);
        if (empty($accepts)) {
            return $locales[0] ?? null;
        }
        if (empty($locales)) {
            return $accepts[0];
        }
        $accepts = array_map(function($v){
            return str_replace('-', '_', strtolower($v));
        }, $accepts);

        // 匹配所有可用类型
        $preferred = [];
        $preferredLower = [];
        foreach ($locales as $local) {
            $localLower = str_replace('-', '_', strtolower($local));
            if (in_array($localLower, $accepts)) {
                $preferred[$localLower] = $local;
                $preferredLower[] = $localLower;
            }
        }
        $preferredLower = array_values(array_intersect($accepts, $preferredLower));
        if (isset($preferredLower[0])) {
            return $preferred[$preferredLower[0]];
        }
        // 尝试以[修正参数 $localesExtend]进行匹配 可参见 languages 获取
        if ($localesExtend) {
            $preferredLower = [];
            foreach ($localesExtend as $localLower => $local) {
                if (in_array($localLower, $accepts)) {
                    $preferredLower[] = $localLower;
                }
            }
            $preferredLower = array_values(array_intersect($accepts, $preferredLower));
            if (isset($preferredLower[0])) {
                return $localesExtend[$preferredLower[0]];
            }
        }
        // 无法匹配 返回指定值的第一个
        $locales = array_values($locales);
        return $locales[0];
    }

    /**
     * 修正反斜杠
     * @param string $path
     * @return array|string|string[]
     */
    private static function correctPathSeparator(string $path)
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $path = str_replace('\\', '/', $path);
        }
        return $path;
    }

    /**
     * raw pathInfo
     * @param string $path
     * @return string
     */
    private static function rawEncodePathInfo(string $path)
    {
        $path = static::correctPathSeparator($path);
        $segments = [];
        foreach (explode('/', $path) as $path) {
            $segments[] = '' === $path ? '' : rawurlencode(urldecode($path));
        }
        return implode('/', $segments);
    }

    /**
     * raw queryString
     * @param string $query
     * @return string
     */
    private static function rawEncodeQueryString(string $query)
    {
        if ('' === $query) {
            return '';
        }
        $segments = [];
        foreach (explode('&', $query) as $query) {
            $qs = [];
            foreach (explode('=', $query) as $q) {
                $qs[] = rawurlencode(urldecode($q));
            }
            $segments[] = implode('=', $qs);
        }
        return implode('&', $segments);
    }

    /**
     * 针对请求包的魔术加载 ($query $request $cookie $file $server $header $json $xml)
     * @param $name
     * @return null
     * @throws InvalidArgumentException
     */
    public function __get($name)
    {
        $lower = strtolower($name);
        if (!isset($this->package[$lower])) {
            switch ($lower) {
                case 'query':
                case 'request':
                case 'cookie':
                case 'json':
                case 'xml':
                case 'attributes':
                    $object = new Parameter([], $lower);
                    break;
                case 'header':
                    $object = new Header([], $lower);
                    break;
                case 'file':
                    $object = (new File([], $lower))->filesystem($this->filesystem);
                    break;
                case 'server':
                    $object = new Server([], $lower);
                    break;
                case 'client':
                    $object = new Client();
                    break;
                default:
                    throw new InvalidArgumentException('Undefined property: '.__CLASS__.'::$'.$name);
            }
            $this->package[$lower] = [
                'reset' => true,
                'obj' => $object
            ];
        }
        $package = $this->package[$lower];
        if ($package['reset']) {
            switch ($lower) {
                case 'json':
                    $parameters = is_array($json = json_decode($this->body(), true)) ? $json : [];
                    break;
                case 'xml':
                    $parameters = ($xml = simplexml_load_string($this->body(), 'SimpleXMLElement', LIBXML_NOCDATA|LIBXML_NOERROR))
                        ? json_decode(json_encode((array) $xml), true)
                        : [];
                    break;
                case 'header':
                case 'client':
                    $parameters = $this->server->headers();
                    break;
                default:
                    $parameters = $this->parameters[$name];
                    break;
            }
            $package['obj']->reset($parameters);
            $this->package[$lower]['reset'] = false;
        }
        return $package['obj'];
    }

    /**
     * @param $name
     * @return $this
     */
    protected function resetPackage($name)
    {
        if (isset($this->package[$name])) {
            $this->package[$name]['reset'] = true;
        }
        return $this;
    }

    /**
     * 对象克隆 需克隆 package 中已创建实例
     */
    public function __clone()
    {
        foreach ($this->package as $key => $package) {
            $this->package[$key] = [
                'reset' => $package['reset'],
                'obj' => clone $package['obj']
            ];
        }
    }
}
