<?php
namespace Tanbolt\Http;

use DateTime;
use DateTimeInterface;
use Tanbolt\Mime\Magic;
use InvalidArgumentException;
use UnexpectedValueException;
use Tanbolt\Http\Response\File;
use Tanbolt\Http\Response\Json;
use Tanbolt\Http\Response\Header;
use Tanbolt\Cookie\CookieInterface;
use Tanbolt\Filesystem\File as SystemFile;
use Tanbolt\Http\Response\FactoryInterface;
use Tanbolt\Filesystem\FilesystemInterface;

/**
 * Class Response: Http Response 类
 * @package Tanbolt\Http
 * @property-read Header $header
 */
class Response
{
    const DEFAULT_VERSION = 1.1;
    const DEFAULT_CHARSET = 'utf-8';

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var FilesystemInterface
     */
    protected $filesystem;

    /**
     * @var CookieInterface
     */
    protected $cookie;

    /**
     * HTTP 版本
     * @var float
     */
    protected $version;

    /**
     * 编码
     * @var string
     */
    protected $charset;

    /**
     * HTTP CODE
     * @var int
     */
    protected $statusCode;

    /**
     * 状态描述
     * @var string
     */
    protected $statusText;

    /**
     * Response Header 对象
     * @var Header
     */
    protected $resHeader;

    /**
     * Response body
     * @var mixed
     */
    protected $resBody;

    /**
     * response body factory 缓存
     * @var FactoryInterface[]
     */
    protected $factoryCache = [];

    /**
     * 输出保护 仅可输出一次
     * @var bool
     */
    private $isSent = null;

    /**
     * Status codes translation table.
     * Unless otherwise noted, the status code is defined in RFC2616.
     * Hypertext Transfer Protocol (HTTP) Status Code Registry
     * @link http://www.iana.org/assignments/http-status-codes/
     * @var array
     */
    public static $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',            // RFC2518
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',          // RFC4918
        208 => 'Already Reported',      // RFC5842
        226 => 'IM Used',               // RFC3229
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Reserved',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',    // RFC7238
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',                                               // RFC2324
        422 => 'Unprocessable Entity',                                        // RFC4918
        423 => 'Locked',                                                      // RFC4918
        424 => 'Failed Dependency',                                           // RFC4918
        425 => 'Reserved for WebDAV advanced collections expired proposal',   // RFC2817
        426 => 'Upgrade Required',                                            // RFC2817
        428 => 'Precondition Required',                                       // RFC6585
        429 => 'Too Many Requests',                                           // RFC6585
        431 => 'Request Header Fields Too Large',                             // RFC6585
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates (Experimental)',                      // RFC2295
        507 => 'Insufficient Storage',                                        // RFC4918
        508 => 'Loop Detected',                                               // RFC5842
        510 => 'Not Extended',                                                // RFC2774
        511 => 'Network Authentication Required',                             // RFC6585
    ];

    /**
     * 获取 Http Response 状态码对应的说明
     * @param int $code
     * @param string $val
     * @return string
     */
    public static function codeText(int $code, string $val = '')
    {
        return static::$statusTexts[$code] ?? $val;
    }

    /**
     * 初始化
     * @param mixed $content
     * @param int $code
     * @param array $headers
     * @param ?string $charset
     * @param float|string|null $version
     */
    public function __construct($content = null, int $code = 200, array $headers = [], string $charset = null, $version = null)
    {
        $this->reset($content, $code, $headers, $charset, $version);
    }

    /**
     * 一次性重置所有参数
     * @param mixed $content
     * @param int $code
     * @param array $headers
     * @param ?string $charset
     * @param float|string|null $version
     * @return $this
     */
    public function reset($content = null, int $code = 200, array $headers = [], string $charset = null, $version = null)
    {
        $this->isSent = null;
        $this->charset = $charset;
        $this->version = null === $version ? null : (float) $version;
        if ($this->resHeader) {
            $this->resHeader->reset($headers);
        } else {
            $this->resHeader = new Header($headers);
        }
        $this->setStatus($code)->setBody($content);
        return $this;
    }

    /**
     * 绑定本次请求的 Request 实例, 部分 Response header 需根据 Request 进行调整。
     * @param ?Request $request
     * @return $this
     */
    public function withRequest(Request $request = null)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * 获取当前绑定的 Request 对象
     * @return ?Request
     */
    public function request()
    {
        return $this->request;
    }

    /**
     * 设置 Cookie 对象，用于在发送 Response 时自动发送 cookie header
     * @param ?CookieInterface $cookie
     * @return $this
     */
    public function withCookie(CookieInterface $cookie = null)
    {
        $this->cookie = $cookie;
        return $this;
    }

    /**
     * 获取当前绑定的 Cookie 对象
     * @return CookieInterface|null
     */
    public function cookie()
    {
        return $this->cookie;
    }

    /**
     * 设置 Filesystem 对象，方便快速 Response file
     * @param FilesystemInterface|null $filesystem
     * @return $this
     */
    public function withFilesystem(FilesystemInterface $filesystem = null)
    {
        $this->filesystem = $filesystem;
        /** @var File $file */
        $file = $this->factoryCache['file'] ?? null;
        if ($file) {
            $file->filesystem($filesystem);
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
     * 设置 HTTP 协议版本
     * @param float|string $version
     * @return $this
     */
    public function setProtocolVersion($version)
    {
        $this->version = (float) $version;
        return $this;
    }

    /**
     * 获取 HTTP 协议版本
     * @return float
     */
    public function protocolVersion()
    {
        return $this->version ?: ($this->request ? $this->request->protocolVersion() : static::DEFAULT_VERSION);
    }

    /**
     * 设置 HTTP STATUS CODE
     * @param int $code
     * @param ?string $text 设置为 null 则根据 $code 参数自动设置
     * @return $this
     */
    public function setStatus(int $code, string $text = null)
    {
        $this->statusCode = $code;
        if ($this->isInvalid()) {
            throw new InvalidArgumentException(sprintf('The HTTP status code "%s" is not valid.', $code));
        }
        $this->statusText = null === $text ? static::codeText($code) : $text;
        return $this;
    }

    /**
     * 获取 HTTP STATUS CODE
     * @return int
     */
    public function statusCode()
    {
        return $this->statusCode;
    }

    /**
     * 获取 HTTP Status text
     * @return string
     */
    public function statusText()
    {
        return $this->statusText;
    }

    /**
     * 是否为非标准 status code
     * @return bool
     */
    public function isInvalid()
    {
        return $this->statusCode < 100 || $this->statusCode >= 600;
    }

    /**
     * 是否为成功状态 (状态码是否为 200)
     * @return bool
     */
    public function isOk()
    {
        return 200 === $this->statusCode;
    }

    /**
     * 是否禁止访问 (状态码是否为 403)
     * @return bool
     */
    public function isForbidden()
    {
        return 403 === $this->statusCode;
    }

    /**
     * 是否未找到网页 (状态码是否为 404)
     * @return bool
     */
    public function isNotFound()
    {
        return 404 === $this->statusCode;
    }

    /**
     * 是否为空 (状态码是否为 204 或 304)
     * @return bool
     */
    public function isEmpty()
    {
        return  204 === $this->statusCode || 304 === $this->statusCode;
    }

    /**
     * 是否为信息响应头 (状态码是否为 10x)
     * @return bool
     */
    public function isInformational()
    {
        return $this->statusCode >= 100 && $this->statusCode < 200;
    }

    /**
     * 服务器是否成功接收 (状态码是否为 20x)
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * 是否重定向 (状态码是否为 30x)
     * @return bool
     */
    public function isRedirection()
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * 是否客户端请求错误
     * @return bool
     */
    public function isClientError()
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * 是否服务器错误
     * @return bool
     */
    public function isServerError()
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * 是否为跳转
     * @param ?string $location 可验证是否为跳转到指定 url
     * @return bool
     */
    public function isRedirect(string $location = null)
    {
        return in_array($this->statusCode, [201, 301, 302, 303, 307, 308]) &&
            (null === $location || $this->resHeader->getLast('Location') == $location);
    }

    /**
     * 发送一个 30x 的跳转链接 response
     * @param string|int $url
     * @param int $code
     * @return $this
     */
    public function setRedirect($url = '', int $code = 302)
    {
        $this->setStatus($code);
        $this->resHeader->set('Location', $url);
        return $this;
    }

    /**
     * 设置 Response 编码, 缺省为 utf-8
     * @param string $charset
     * @return $this
     */
    public function setCharset(string $charset)
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * 获取 Response 编码
     * @return string
     */
    public function charset()
    {
        return $this->charset ?: static::DEFAULT_CHARSET;
    }

    /**
     * 设置 Header
     * @param array|string $key
     * @param array|string|null $value
     * @param bool $replace
     * @return $this
     */
    public function setHeader($key, $value = null, bool $replace = true)
    {
        if ($replace) {
            $this->resHeader->set($key, $value, $replace);
        } else {
            $this->resHeader->add($key, $value);
        }
        return $this;
    }

    /**
     * 获取指定 key 的 Header line
     * @param array|string $key
     * @param mixed $default
     * @return ?string
     */
    public function getHeader($key, $default = null)
    {
        return $this->resHeader->getLine($key, $default);
    }

    /**
     * 设置为下载文件的 http 头
     * @param ?string $fileName
     * @param ?string $mimeType
     * @param int $length
     * @return $this
     */
    public function setDownloadHeader(string $fileName = null, string $mimeType = null, int $length = 0)
    {
        $fileName = $fileName ? str_replace([';', '"', "\n", "\r"], '-', $fileName) : null;
        $header = [
            'Content-Description' => 'File Transfer',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Disposition' => 'attachment'.(empty($fileName) ? '' : "; filename=\"$fileName\""),
        ];
        if ($length > 0) {
            $header['Content-Length'] = $length;
        }
        $this->resHeader->set($header);
        if ($mimeType) {
            $this->resHeader->setIf('Content-Type', $mimeType);
        } else {
            $this->resHeader->setIf('Content-Type', 'application/octet-stream');
        }
        return $this;
    }

    /**
     * 当前 Response 是否为文件下载
     * @return bool
     */
    public function isDownloadHeader()
    {
        return false !== stripos($this->resHeader->getLast('Content-Disposition'), 'attachment');
    }

    /**
     * 设置发送格式 (css js html...),
     * > 常用 web 格式一般没有问题, 但并不能保证支持所有格式, 若比较特殊的格式，建议直接设置 header Content-Type.
     * 若未指定或未通过类似 FileFactory 设置 Body, 默认为 text/html
     * @param ?string $format 可设置为 null 移除已设置
     * @return $this
     */
    public function setFormat(?string $format)
    {
        if (!$format) {
            $this->resHeader->remove('Content-Type');
        } elseif ($contentType = Magic::guessMimeTypeByExtension($format)) {
            $this->setHeader('Content-Type', $contentType);
        }
        return $this;
    }

    /**
     * 获取发送格式 (css js html...)
     * @return ?string
     */
    public function format()
    {
        if (null !== $contentType = $this->resHeader->getLast('Content-Type')) {
            if (false !== strpos($contentType, ';')) {
                $contentType = explode(';', $contentType, 2);
                $contentType = $contentType[0];
            }
            return Magic::guessExtensionByMimeType($contentType);
        }
        return null;
    }

    /**
     * 设置资源的发送时间
     * > 通常，该值即为当前时间，在当前服务器为代理服务器，则发送原始服务器提供的 Date 值。
     * 客户端可利用该值与当下时间计算出资源的 age, 即该实体从产生到现在经过的时长。
     * 客户端可通过该值计算接收消息耗费时长、判断是否命中服务端缓存。
     * @param DateTime|int|string|null $date 设置为 null 则移除 Date
     * @return $this
     */
    public function setDate($date)
    {
        if ($date) {
            $this->resHeader->setDate('Date', $date);
        } else {
            $this->resHeader->remove('Date');
        }
        return $this;
    }

    /**
     * 获取服务器消息发出的时间
     * @return DateTime
     */
    public function date()
    {
        return $this->resHeader->getDate('Date', new DateTime());
    }

    /**
     * 设置服务器发出时间到当前响应时间的时长，若设置了 Date，无需手动设置 Age, 会自动计算
     * @param ?int $age 设置为 null 则移除 Age
     * @return $this
     */
    public function setAge(?int $age)
    {
        if (null === $age) {
            $this->resHeader->remove('Age');
        } else {
            $this->resHeader->set('Age', $age);
        }
        return $this;
    }

    /**
     * 获取服务器发出时间到当前响应时间的时长
     * @return int
     */
    public function age()
    {
        if (null !== $headerAge = $this->resHeader->getLast('Age')) {
            return (int) $headerAge;
        }
        return max(time() - $this->date()->getTimestamp(), 0);
    }

    /**
     * 完全禁止任何形式的缓存, 会设置 Cache-Control Pragma Expires;
     * @return $this
     */
    public function noStore()
    {
        // Pragma 为 http/1.0 首部，为增强兼容性，也发送该首部
        $this->resHeader->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        return $this;
    }

    /**
     * 可缓存，设置缓存过期时间
     * @param DateTime|int|string|null $expires 设置为 null 移除 Expires
     * @return $this
     */
    public function setExpires($expires)
    {
        if ($expires) {
            $this->resHeader->setDate('Expires', $expires);
        } else {
            $this->resHeader->remove('Expires');
        }
        return $this;
    }

    /**
     * 获取缓存过期时间
     * @return ?DateTimeInterface
     */
    public function expires()
    {
        return $this->resHeader->getDate('Expires');
    }

    /**
     * 可缓存，设置 Cache-Control 的 max-age 或 s-maxage 值 （缓存时长）
     * @param ?int $seconds 设置为 null 则移除 max-age 或 s-maxage
     * @param bool $shared 是否为 s-maxage 操作
     * @return $this
     */
    public function setMaxAge(?int $seconds, bool $shared = false)
    {
        $key = $shared ? 's-maxage' : 'max-age';
        $this->resHeader->removeCacheControl($key);
        if (null !== $seconds) {
            $this->resHeader->setCacheControl($key, $seconds);
        }
        return $this;
    }

    /**
     * 获取已设置的缓存时长, 获取优先级: s-maxage > max-age > expires
     * @return ?int
     */
    public function maxAge()
    {
        if (null !== $maxAge = $this->resHeader->getCacheControl(['s-maxage', 'max-age'])) {
            return (int) $maxAge;
        }
        if ($expires = $this->expires()) {
            return $expires->getTimestamp() - $this->date()->getTimestamp();
        }
        return 0;
    }

    /**
     * 设置 Cache-Control 为 public，表示任何对象都可以缓存，包括代理服务器、客户端等。
     * @param int $maxAge 设置缓存时长
     * @return $this
     */
    public function setPublic(int $maxAge = 0)
    {
        $this->resHeader->removeCacheControl(['no-store', 'private'])->setCacheControl('public');
        if ($maxAge) {
            $this->setMaxAge($maxAge);
        }
        return $this;
    }

    /**
     * Cache-Control 是否可缓存 且为 public
     * @return bool
     */
    public function isPublic()
    {
        return $this->resHeader->hasCacheControl('public') &&
            !$this->resHeader->hasCacheControl(['no-store', 'private'], true);
    }

    /**
     * 设置 Cache-Control 可缓存且为 private，只能被单个用户缓存（通常为最终客户端，如浏览器），不能共享缓存（如代理服务器不能缓存它）
     * @param int $maxAge 设置缓存时长
     * @return $this
     */
    public function setPrivate(int $maxAge = 0)
    {
        // 大部分浏览器默认即为 private, 但既然设置了, 就显性的设置为 private
        $this->resHeader->removeCacheControl(['no-store', 'public'])->setCacheControl('private');
        if ($maxAge) {
            $this->setMaxAge($maxAge);
        }
        return $this;
    }

    /**
     * Cache-Control 是否可缓存 且为 private
     * @return bool
     */
    public function isPrivate()
    {
        // no-store 不缓存, 先排除; 大部分浏览器默认即为 private, 明确指定 private 或未指定 public 也可以
        return !$this->resHeader->hasCacheControl('no-store') && (
            $this->resHeader->hasCacheControl('private') || !$this->resHeader->hasCacheControl('public')
        );
    }

    /**
     * 设置最后修改时间，可作为下次请求判断资源新鲜度的标准，以便返回 304
     * @param DateTime|int|string|null $lastModified 设置为 null 则移除 Last-Modified
     * @return $this
     */
    public function setLastModified($lastModified)
    {
        if ($lastModified) {
            $this->resHeader->setDate('Last-Modified', $lastModified);
        } else {
            $this->resHeader->remove('Last-Modified');
        }
        return $this;
    }

    /**
     * 获取最后修改时间
     * @return ?DateTimeInterface
     */
    public function lastModified()
    {
        return $this->resHeader->getDate('Last-Modified');
    }

    /**
     * 设置 Etag 标记，响应实体的唯一标识值，可作为下次请求判断资源新鲜度的标准，以便返回 304
     * @param ?string $etag 设置为 null 则移除 Etag
     * @param bool $weak 是否为弱验证
     * @return $this
     */
    public function setETag(?string $etag, bool $weak = false)
    {
        if ($etag) {
            $etag = (true === $weak ? 'W/' : '') . (0 !== strpos($etag, '"') ? '"'.$etag.'"' : $etag);
            $this->resHeader->set('ETag', $etag);
        } else {
            $this->resHeader->remove('Etag');
        }
        return $this;
    }

    /**
     * 获取 Etag 标记
     * @return string
     */
    public function eTag()
    {
        return $this->resHeader->getLast('ETag');
    }

    /**
     * 当前 Response 是否为可验证资源, 即有可能根据 Request 返回 304
     * @return bool
     */
    public function isValidate()
    {
        return $this->resHeader->has(['ETag', 'Last-Modified'], true);
    }

    /**
     * 当前资源是否可被客户端缓存，即使缓存 0 秒（即每次都会进行校验）也返回 true
     * @param bool $strict 是否采用严格模式：验证 method 和 statusCode
     * @return bool
     */
    public function isCacheable(bool $strict = false)
    {
        // 严格模式完全按照 RFC7231 验证 method 和 statusCode. 但浏览器不一定完全按照协议, 可参考以下链接
        // https://developer.mozilla.org/en-US/docs/Glossary/cacheable
        // https://developer.mozilla.org/zh-CN/docs/Web/HTTP/Status
        if ($strict) {
            // https://tools.ietf.org/html/rfc7231#section-4.2.3
            if ($this->request && !$this->request->isMethod(['GET', 'HEAD', 'POST'])) {
                return false;
            }
            // https://tools.ietf.org/html/rfc7231#section-6.1
            if (!in_array($this->statusCode, [200, 203, 204, 206, 300, 301, 302, 404, 405, 410, 414, 501])) {
                return false;
            }
        }
        // 没有 no-store 且 header 包含缓存标准 (Etag / Last-Modified || Expires)
        return !$this->resHeader->hasCacheControl('no-store') &&
            ($this->isValidate() || $this->maxAge() - $this->age() > 0);
    }

    /**
     * 当前 Response 是否未改动，即可以返回 304 (需要已配置 Request、Etag 或 Last-Modified)
     * @return bool
     */
    public function isUnModified()
    {
        if (!$this->request) {
            return false;
        }
        if (($etag = $this->eTag()) && ($matches = $this->request->eTags())) {
            return in_array($etag, $matches);
        }
        if (($modified = $this->lastModified()) && ($since = $this->request->header->getDate('If-Modified-Since'))) {
            return $modified->getTimestamp() === $since->getTimestamp();
        }
        return false;
    }

    /**
     * Response body: 输出 json 或 jsonp
     * @param mixed $data 可以转为 json 的变量
     * @param ?string $callback
     * @return Json
     */
    public function setJson($data, string $callback = null)
    {
        $json = $this->factoryCache['json'] ?? null;
        if (!$json) {
            $json = $this->factoryCache['json'] = new Json();
        }
        return $this->resBody = $json->reset()->setData($data)->setCallback($callback);
    }

    /**
     * Response body: 输出文件
     * @param SystemFile|resource|string|mixed $content 可以是 文件路径或内容(string)、文件指针(resource)、或 Filesystem\File
     * @param bool|null $local 定义 (string) $content 的性质, true:本地文件; false:Filesystem下的文件路径; null:文件内容
     * @return File
     */
    public function setFile($content, ?bool $local = true)
    {
        $file = $this->factoryCache['file'] ?? null;
        if (!$file) {
            $file = $this->factoryCache['file'] = new File();
        }
        return $this->resBody = $file->reset()->filesystem($this->filesystem)->setContent($content, $local);
    }

    /**
     * 设置 response body：可以为数组，输出为 json 格式，也可是 FactoryInterface 对象 或 任意可转为 string 的变量、对象
     * @param FactoryInterface|array|string|mixed $body
     * @return $this
     */
    public function setBody($body)
    {
        if (is_array($body)) {
            $this->setJson($body);
        } elseif (null !== $body && !is_string($body) &&  !is_numeric($body) &&
            !($body instanceof FactoryInterface) && !method_exists($body, '__toString')
        ) {
            throw new UnexpectedValueException(sprintf(
                'The Response content must be a string or object implementing __toString(), "%s" given.',
                gettype($body)
            ));
        } else {
            $this->resBody = $body;
        }
        return $this;
    }

    /**
     * 获取 Response body
     * @return FactoryInterface|array|string|mixed
     */
    public function getBody()
    {
        return $this->resBody;
    }

    /**
     * 获取当前要发送到客户端的 cookie 合集
     * @param bool $asLine 是否以字符串形式表示单个 cookie
     * @return array
     */
    public function getSentCookies(bool $asLine = false)
    {
        $sent = [];
        // Cookie 对象中设置的 cookie
        if ($this->cookie) {
            $cakes = $this->cookie->tobeSend();
            foreach ($cakes as $cake) {
                if ($cake = $asLine ? (string) $cake : $cake->toArray(true)) {
                    $sent[] = $cake;
                }
            }
        }
        // Request 使用了 Session 对象, 增加 sessionId cookie
        $session = $this->request ? $this->request->session() : null;
        if ($session && ($seCookie = $session->getResponseCookie($asLine))) {
            $sent[] = $seCookie;
        }
        return $sent;
    }

    /**
     * 获取将要发送的 header, 该函数调用后可能会重置部分 header 属性或其他 response 属性；
     * > **务必仅在 send 前调用该函数，若必须保持原本 Response 属性，可在调用前 clone Response**
     * @param bool $withCookie 是否包含 cookie header
     * @return array
     */
    public function getSentHeaders(bool $withCookie = true)
    {
        // 处理 Factory
        $request = $this->request;
        if ($this->resBody instanceof FactoryInterface) {
            $this->resBody->prepare($this, $request);
        }
        // 处理 resHeader
        $resHeader = $this->resHeader->getSent($this, $request);
        $version = 1 == ($version = $this->protocolVersion()) ? '1.0' : $version;
        $header = [
            'header' => sprintf('HTTP/%s %s %s', $version, $this->statusCode, $this->statusText)
        ];
        if ($withCookie) {
            $cookie = $this->getSentCookies(true);
            if (isset($resHeader['Set-Cookie'])) {
                $cookie = array_merge($cookie, $resHeader['Set-Cookie']);
                if (count($cookie)) {
                    $resHeader['Set-Cookie'] = $cookie;
                } else {
                    unset($resHeader['Set-Cookie']);
                }
            } elseif (count($cookie)) {
                $resHeader['Set-Cookie'] = $cookie;
            }
        }
        return $header + $resHeader;
    }

    /**
     * 通过 php header 函数输出报文
     * @param bool $withCookie 是否包括 cookie
     * @return $this
     */
    public function sendHeaders(bool $withCookie = true)
    {
        if (headers_sent()) {
            return $this;
        }
        $headers = $this->getSentHeaders($withCookie);
        foreach ($headers as $name => $value) {
            if ('header' === $name) {
                header($value, true, $this->statusCode);
            } else {
                foreach ($value as $val) {
                    header($name . ': ' . $val, false, $this->statusCode);
                }
            }
        }
        return $this;
    }

    /**
     * 获取 Response body 的 string, body 数据与 header 相关, 所以必须在 getSentHeaders 或 sendHeaders 之后调用
     * @return string
     */
    public function getSendBody()
    {
        return $this->responseBody(false);
    }

    /**
     * 输出 Response body string, body 数据与 header 相关, 所以必须在 getSentHeaders 或 sendHeaders 之后调用
     * @param ?callable $sendFunction 自定义输出函数，默认为 echo
     * @param ?callable $onEnd 输出完成后的回调
     * @return $this
     */
    public function sendBody(callable $sendFunction = null, callable $onEnd = null)
    {
        $this->responseBody($sendFunction);
        if ($onEnd) {
            call_user_func($onEnd);
        }
        return $this;
    }

    /**
     * 获取 或 发送 body
     * @param callable|false|null $sendFunction
     * @return $this|string|Response
     */
    protected function responseBody($sendFunction)
    {
        $request = $this->request;
        $isGet = false === $sendFunction;
        // 仅发送 header 的情况
        if ($this->isInformational() || $this->isEmpty() || ($request && $request->isHead())) {
            return $isGet ? '' : $this;
        }
        $stream = $this->resBody;
        if ($stream instanceof FactoryInterface) {
            set_time_limit(0);
            $body = '';
            $stream->rewind();
            while ($stream->valid()) {
                //var_dump('key:'.$stream->key());
                if ($isGet) {
                    $body .= $stream->current();
                } elseif ($sendFunction) {
                    call_user_func($sendFunction, $stream->current());
                } else {
                    echo $stream->current();
                }
                $stream->next();
            }
            return $isGet ? $body : $this;
        }
        $body = method_exists($stream, '__toString') ? $stream->__toString() : (string) $stream;
        if ($isGet) {
            return $body;
        }
        if ($sendFunction) {
            call_user_func($sendFunction, $body);
        } else {
            echo $body;
        }
        return $this;
    }

    /**
     * 发送结果到客户端, 仅可执行一次
     * @return $this
     */
    public function send()
    {
        if (!$this->isSent) {
            $this->isSent = true;
            $this->sendHeaders()->sendBody();
            static::finishRequest();
        }
        return $this;
    }

    /**
     * 魔术加载 $header
     * @param $name
     * @return null
     * @throws InvalidArgumentException
     */
    public function __get($name)
    {
        if ('header' === strtolower($name)) {
            return $this->resHeader;
        }
        throw new InvalidArgumentException('Undefined property: '.__CLASS__.'::$'.$name);
    }

    /**
     * clone 对象
     */
    public function __clone()
    {
        $this->resHeader = clone $this->resHeader;
    }

    /**
     * @return Response
     */
    public function __destruct()
    {
        if ($this->resBody instanceof FactoryInterface) {
            $this->resBody->reset();
        }
        return $this->reset();
    }

    /**
     * 结束输出，但不结束进程，仍可执行一些其他任务
     */
    public static function finishRequest()
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif ('cli' !== PHP_SAPI) {
            static::closeOutputBuffers(0, true);
        }
    }

    /**
     * 结束向客户端传输数据，但不结束进程，仍可执行一些其他任务
     *
     * > code from symfony
     * Licensed under the MIT/X11 License (http://opensource.org/licenses/MIT)
     * (c) Fabien Potencier <fabien@symfony.com>
     * @link https://github.com/symfony/symfony/blob/3.0/src/Symfony/Component/HttpFoundation/Response.php
     * @param int $targetLevel The target output buffering level
     * @param bool $flush Whether to flush or clean the buffers
     */
    public static function closeOutputBuffers(int $targetLevel, bool $flush)
    {
        $status = ob_get_status(true);
        $level = count($status);
        $flags = !defined('PHP_OUTPUT_HANDLER_REMOVABLE') ? -1 :
            PHP_OUTPUT_HANDLER_REMOVABLE | ($flush ? PHP_OUTPUT_HANDLER_FLUSHABLE : PHP_OUTPUT_HANDLER_CLEANABLE);
        while (
            $level-- > $targetLevel &&
            ($s = $status[$level]) &&
            (!isset($s['del']) ? !isset($s['flags']) || $flags === ($s['flags'] & $flags) : $s['del'])
        ) {
            if ($flush) {
                ob_end_flush();
            } else {
                ob_end_clean();
            }
        }
    }
}
