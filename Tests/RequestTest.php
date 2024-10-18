<?php

use Tanbolt\Filesystem\Filesystem;
use Tanbolt\Http\Request;
use PHPUnit\Framework\TestCase;
use Tanbolt\Session\Session;

class RequestTest extends TestCase
{

    public function testTrustedClient()
    {
        Request::setTrustedClient([]);
        static::assertEquals([], Request::trustedClients());

        Request::addTrustedClient('127.0.0.1');
        static::assertEquals(['127.0.0.1'], Request::trustedClients());
        Request::addTrustedClient('127.0.0.2');
        static::assertEquals(['127.0.0.1','127.0.0.2'], Request::trustedClients());

        Request::addTrustedClient(['127.0.0.2','66.249.66.1','221.254.78.266']);
        static::assertEquals(['127.0.0.1','127.0.0.2','66.249.66.1'], Request::trustedClients());

        Request::setTrustedClient(['127.0.0.4','127.0.0.5']);
        static::assertEquals(['127.0.0.4','127.0.0.5'], Request::trustedClients());

        $request = Request::create(null, 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        static::assertFalse($request->isTrustedClient());

        $request = Request::create(null, 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.4',
        ]);
        static::assertTrue($request->isTrustedClient());

        Request::clearTrustedClient();
        static::assertEquals([], Request::trustedClients());
        static::assertFalse($request->isTrustedClient());
    }

    public function testCreate()
    {
        $request = Request::create('http://www.aa.com/');
        static::assertFalse($request->isHttps());
        static::assertEquals(80, $request->port());
        static::assertEquals('http://www.aa.com', $request->httpHost());

        $request = Request::create('https://www.aa.com/');
        static::assertTrue($request->isHttps());
        static::assertEquals(443, $request->port());
        static::assertEquals('https://www.aa.com', $request->httpHost());

        $request = Request::create('http://www.aa.com:8080/');
        static::assertFalse($request->isHttps());
        static::assertEquals(8080, $request->port());
        static::assertEquals('http://www.aa.com:8080', $request->httpHost());

        $request = Request::create('/', 'GET', [], [], [], [
            'SERVER_PORT' => 8080
        ]);
        static::assertFalse($request->isHttps());
        static::assertEquals(8080, $request->port());
        static::assertEquals('http://localhost:8080', $request->httpHost());

        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_HOST' => 'www.aa.com:8080',
            'HTTPS' => 'on'
        ]);
        static::assertTrue($request->isHttps());
        static::assertEquals(8080, $request->port());
        static::assertEquals('https://www.aa.com:8080', $request->httpHost());

        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_HOST' => 'www.aa.com',
            'HTTPS' => 'on',
            'SERVER_PORT' => 8080
        ]);
        static::assertTrue($request->isHttps());
        static::assertEquals(8080, $request->port());
        static::assertEquals('https://www.aa.com:8080', $request->httpHost());
    }

    public function testConstruct()
    {
        // 模拟系统变量
        $g_get = $_GET;
        $g_post = $_POST;
        $g_cookie = $_COOKIE;
        $g_server = $_SERVER;
        $g_file = $_FILES;

        $get = ['foo' => 'bar', 'hello' => 'world'];
        $post = ['foo2' => 'bar2', 'hello2' => 'world2'];
        $cookie = ['foo3' => 'bar3', 'hello3' => 'world3'];
        $server = [
            'foo4' => 'bar4', 'hello4' => 'world4',
            'HTTP_foo5' => 'bar5', 'HTTP_hello5' => 'world5',
        ];
        $file = [
            'foo6' => self::makeFileArray('foo6'),
            'hello6' => self::makeFileArray('hello6'),
        ];

        $_GET = $get;
        $_POST = $post;
        $_COOKIE = $cookie;
        $_SERVER = $server;
        $_FILES = $file;
        $request = new Request();
        $this->checkRequest($request, $get, $post, $cookie, $file, $server, [], [], [], '');
        $_GET = $g_get;
        $_POST = $g_post;
        $_COOKIE = $g_cookie;
        $_SERVER = $g_server;
        $_FILES = $g_file;

        // 实际系统变量
        $request = new Request();
        $this->checkRequest($request, $g_get, $g_post, $g_cookie, $g_file, $g_server, [], [], [], '');

        $attr = $json = $xml = ['foo' => 'bar', 'hello' => 'world'];

        // 指定 json body
        $jsonBody = '{"foo":"bar","hello":"world"}';
        $request = new Request($get, $post, $cookie, $file, $server, $jsonBody, $attr);
        $this->checkRequest($request, $get, $post, $cookie, $file, $server, $json, [], $attr, $jsonBody);

        // 测试 rebuild
        $rebuild = $request->rebuild();
        $this->checkRequest($rebuild, $get, $post, $cookie, $file, $server, $json, [], $attr, $jsonBody);

        // 指定 xml body
        $xmlBody = '<xml><foo>bar</foo><hello>world</hello></xml>';
        $request = new Request([], [], [], [], [], $xmlBody, $attr);
        $this->checkRequest($request, [], [], [], [], [], [], $xml, $attr, $xmlBody);

        // 测试 reset
        $request->reset($get, $post, $cookie, $file, $server, $jsonBody, $attr);
        $this->checkRequest($request, $get, $post, $cookie, $file, $server, $json, [], $attr, $jsonBody);

        // 测试 client
        $userAgent = 'Opera/9.80 (iPad; Opera Mini/7.1.32694/27.1407; U; en) Presto/2.8.119 Version/11.10';
        $request = Request::create(null, 'GET', [], [], [], [
            'HTTP_USER_AGENT'      => $userAgent,
        ]);
        static::assertInstanceOf(Request\Client::class, $request->client);
        static::assertEquals($userAgent, $request->client->userAgent());
    }

    protected function checkRequest(
        Request $req,
        $getArr,
        $postArr,
        $cookieArr,
        $fileArr,
        $serverArr,
        $jsonArr,
        $xmlArr,
        $attrArr,
        $bodyVal
    ) {
        // query
        $query = $req->query;
        static::assertInstanceOf(Request\Parameter::class, $query);
        static::assertSame($query, $req->query);
        static::assertEquals($getArr, $query->all());
        $query->set('foo', 'foo');
        static::assertEquals('foo', $req->query->get('foo'));

        // request
        $request = $req->request;
        static::assertInstanceOf(Request\Parameter::class, $request);
        static::assertSame($request, $req->request);
        static::assertEquals($postArr, $request->all());
        $request->set('foo', 'foo');
        static::assertEquals('foo', $req->request->get('foo'));

        // cookie
        $cookie = $req->cookie;
        static::assertInstanceOf(Request\Parameter::class, $cookie);
        static::assertSame($cookie, $req->cookie);
        static::assertEquals($cookieArr, $cookie->all());
        $cookie->set('foo', 'foo');
        static::assertEquals('foo', $req->cookie->get('foo'));

        // server
        $server = $req->server;
        static::assertInstanceOf(Request\Server::class, $server);
        static::assertSame($server, $req->server);
        static::assertEquals($serverArr, $server->all());
        $server->set('foo', 'foo');
        static::assertEquals('foo', $req->server->get('foo'));

        // header
        $header = $req->header;
        $headerArr = static::makeHeaders($server->headers());
        static::assertInstanceOf(Request\Header::class, $header);
        static::assertSame($header, $req->header);
        static::assertEquals($headerArr, $header->all());
        $header->set('foo', 'foo');
        static::assertEquals(['foo'], $req->header->get('foo'));

        // file
        $file = $req->file;
        static::assertInstanceOf(Request\File::class, $file);
        static::assertSame($file, $req->file);
        $allFiles = $file->all();
        static::assertCount(count($fileArr), $allFiles);
        foreach ($allFiles as $key => $val) {
            static::assertEquals($fileArr[$key], $val->file());
        }
        $file->set('foo', $fooFile = self::makeFileArray('foo'));
        static::assertEquals($fooFile, $req->file->get('foo')->file());

        // json
        $json = $req->json;
        static::assertInstanceOf(Request\Parameter::class, $json);
        static::assertSame($json, $req->json);
        static::assertEquals($jsonArr, $json->all());
        $json->set('foo', 'foo');
        static::assertEquals('foo', $req->json->get('foo'));

        // xml
        $xml = $req->xml;
        static::assertInstanceOf(Request\Parameter::class, $xml);
        static::assertSame($xml, $req->xml);
        static::assertEquals($xmlArr, $xml->all());
        $xml->set('foo', 'foo');
        static::assertEquals('foo', $req->xml->get('foo'));

        // attributes
        $attributes = $req->attributes;
        static::assertInstanceOf(Request\Parameter::class, $attributes);
        static::assertSame($attributes, $req->attributes);
        static::assertEquals($attrArr, $attributes->all());
        $attributes->set('foo', 'foo');
        static::assertEquals('foo', $req->attributes->get('foo'));

        // body
        static::assertEquals($req->body(), $bodyVal);
    }

    protected static function makeHeaders($servers)
    {
        $headers = [];
        foreach ($servers as $key => $val) {
            $key = str_replace('_', '-', strtolower($key));
            $key = implode('-', array_map('ucfirst', explode('-', $key)));
            $headers[$key] = is_array($val) ? $val : [$val];
        }
        return $headers;
    }

    protected static function makeFileArray($name)
    {
        return [
            'size' => 500,
            'name' => $name.'jpg',
            'tmp_name' => '/tmp/'.$name.'.temp',
            'type' => 'blah',
            'error' => null,
        ];
    }

    public function testWithFilesystem()
    {
        if (!class_exists(Filesystem::class)) {
            static::markTestSkipped('Filesystem class not exist, Skip testSetFilesystem');
        }
        $request = new Request();
        static::assertNull($request->filesystem());
        static::assertNull($request->file->filesystem());

        $filesystem = new Filesystem();
        static::assertSame($request, $request->withFilesystem($filesystem));
        static::assertSame($filesystem, $request->filesystem());
        static::assertSame($filesystem, $request->file->filesystem());

        static::assertSame($request, $request->withFilesystem(null));
        static::assertNull($request->filesystem());
        static::assertNull($request->file->filesystem());
    }

    /**
     * @runInSeparateProcess
     */
    public function testWithSession()
    {
        if (!class_exists(Session::class)) {
            static::markTestSkipped('Session class not exist, Skip testSetSession');
        }
        $currentConf = false !== filter_var(ini_get('session.use_cookies'), FILTER_VALIDATE_BOOLEAN);
        ini_set('session.use_cookies', true);

        $request = new Request();
        static::assertNull($request->session());

        $session = new Session();
        static::assertSame($request, $request->withSession($session));
        static::assertSame($session, $request->session());
        static::assertFalse($session->isIni('use_cookies'));

        static::assertSame($request, $request->withSession(null));
        static::assertNull($request->session());
        static::assertTrue($session->isIni('use_cookies'));

        ini_set('session.use_cookies', $currentConf);
    }

    public function testProtocolVersion()
    {
        $request = Request::create(null, 'GET', [], [], [], [
            'SERVER_PROTOCOL' => 'HTTP/1.1',
        ]);
        static::assertSame(1.1, $request->protocolVersion());
        static::assertSame($request, $request->setProtocolVersion(1.0));
        static::assertSame(1.0, $request->protocolVersion());
        static::assertSame($request, $request->setProtocolVersion(null));
        static::assertSame(1.1, $request->protocolVersion());
    }

    public function testMethod()
    {
        $request = Request::create(null, 'GET');
        static::assertSame('GET', $request->method());
        static::assertTrue($request->isGet());
        static::assertTrue($request->isSafeMethod());

        static::assertSame($request, $request->setMethod('POST'));
        static::assertSame('POST', $request->method());
        static::assertTrue($request->isPost());
        static::assertFalse($request->isSafeMethod());

        static::assertTrue($request->isMethod('post'));
        static::assertTrue($request->isMethod('POST'));
        static::assertFalse($request->isMethod('get'));
        static::assertFalse($request->isMethod('GET'));
        static::assertTrue($request->isMethod(['Post', 'get']));
        static::assertFalse($request->isMethod(['Put', 'get']));

        $request = Request::create(null, 'HEAD');
        static::assertSame('HEAD', $request->method());
        static::assertTrue($request->isHead());

        $request = Request::create(null, 'POST');
        static::assertSame('POST', $request->method());
        static::assertTrue($request->isPost());

        $request = Request::create(null, 'PUT');
        static::assertSame('PUT', $request->method());
        static::assertTrue($request->isPut());

        $request = Request::create(null, 'PATCH');
        static::assertSame('PATCH', $request->method());
        $request = Request::create(null, 'DELETE');
        static::assertSame('DELETE', $request->method());
        $request = Request::create(null, 'OPTIONS');
        static::assertSame('OPTIONS', $request->method());

        $request = Request::create(null, 'POST');
        $request->header->set('X-HTTP-METHOD-OVERRIDE', 'PUT');
        static::assertSame('PUT', $request->method());
        static::assertSame('POST', $request->realMethod());

        $request = Request::create(null, 'GET');
        $request->setMethod('POST');
        static::assertSame('POST', $request->method());
        static::assertSame('GET', $request->realMethod());
    }

    public function testIp()
    {
        $ip = '127.0.0.1';
        $request = Request::create(null, 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip,
        ]);
        static::assertEquals($ip, $request->ip());
    }

    public function testHeaderEtags()
    {
        $request = Request::create(null, 'GET', [], [], [], [
            'HTTP_If-None-Match' => 'W/"abcdef"'
        ]);
        static::assertEquals(['W/"abcdef"'], $request->eTags());

        $request = Request::create(null, 'GET', [], [], [], [
            'HTTP_If-None-Match' => 'W/"abcdef",W/"qwers"'
        ]);
        static::assertEquals(['W/"abcdef"', 'W/"qwers"'], $request->eTags());
        static::assertSame($request, $request->setETags('W/"qazwsx", W/"edcrfv"'));
        static::assertEquals(['W/"qazwsx"', 'W/"edcrfv"'], $request->eTags());

        static::assertSame($request, $request->setETags('auxdtcvb'));
        static::assertEquals(['"auxdtcvb"'], $request->eTags());

        static::assertSame($request, $request->setETags('"pfewnvbrt"'));
        static::assertEquals(['"pfewnvbrt"'], $request->eTags());

        static::assertSame($request, $request->setETags(null));
        static::assertFalse($request->eTags());
    }

    public function testReferrerCross()
    {
        $request = Request::create(null);
        static::assertFalse($request->isCross());

        $request = Request::create('http://www.foo.com', 'GET', [] ,[], [], [
            'HTTP_REFERER' => 'error'
        ]);
        static::assertEquals('error', $request->referrer());
        static::assertFalse($request->isCross());

        // domain
        $request = Request::create('http://www.foo.com', 'GET', [] ,[], [], [
            'HTTP_REFERER' => 'http://www.bar.com/foo.html'
        ]);
        static::assertTrue($request->isCross());

        $request = Request::create('http://foo.com', 'GET', [] ,[], [], [
            'HTTP_REFERER' => 'http://www.foo.com/foo.html'
        ]);
        static::assertTrue($request->isCross());

        // port
        $request = Request::create('http://foo.com', 'GET', [] ,[], [], [
            'HTTP_REFERER' => 'http://foo.com:8080/foo.html'
        ]);
        static::assertTrue($request->isCross());

        $request = Request::create('http://foo.com:8080', 'GET', [] ,[], [], [
            'HTTP_REFERER' => 'http://foo.com:8080/foo.html'
        ]);
        static::assertFalse($request->isCross());

        // scheme
        $request = Request::create('http://foo.com', 'GET', [] ,[], [], [
            'HTTP_REFERER' => 'https://foo.com/foo.html'
        ]);
        static::assertTrue($request->isCross());

        $request = Request::create('https://foo.com', 'GET', [] ,[], [], [
            'HTTP_REFERER' => 'https://foo.com/foo.html'
        ]);
        static::assertFalse($request->isCross());

        // origin referrer
        $request = Request::create('/', 'GET', [] ,[], [], [
            'HTTP_HOST' => 'foo.com',
            'HTTP_Origin' => ($origin = 'http://foo.com'),
            'HTTP_REFERER' => ($referrer = 'http://foo.com/foo.html')
        ]);
        static::assertEquals($origin, $request->origin());
        static::assertEquals($referrer, $request->referrer());
        static::assertFalse($request->isCross());
    }

    public function testAccepts()
    {
        $request = Request::create('/test?foo=bar_get', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;level=3;q=0.8',
        ]);
        static::assertEquals([
            'text/html' => [
                'index' => 0,
                'q' => 1
            ],
            'application/xhtml+xml' => [
                'index' => 1,
                'q' => 1
            ],
            'application/xml' => [
                'index' => 2,
                'q' => 0.9
            ],
            '*/*' => [
                'index' => 3,
                'q' => 0.8,
                'level' => '3'
            ],
        ], $request->accepts());
        static::assertEquals('text/html', $request->preferredAccepts());
        static::assertEquals('text/html', $request->preferredAccepts('text/json,text/html'));
        static::assertEquals('text/html', $request->preferredAccepts('application/xml,text/html'));
        static::assertEquals('text/json', $request->preferredAccepts('text/json,text/xml'));
        static::assertEquals('text,json', $request->preferredLanguage(['text,json', 'text/xml']));
    }

    public function testAcceptEncodings()
    {
        $request = Request::create('/test?foo=bar_get', 'GET', [], [], [], [
            'HTTP_Accept-Encoding' => 'gzip,deflate;b=2,sdch;q=0.5',
        ]);
        static::assertEquals([
            'gzip' => [
                'index' => 0,
                'q' => 1
            ],
            'deflate' => [
                'index' => 1,
                'q' => 1,
                'b' => '2',
            ],
            'sdch' => [
                'index' => 2,
                'q' => 0.5
            ],
        ], $request->encodings());
        static::assertEquals('gzip', $request->preferredEncoding());
        static::assertEquals('gzip', $request->preferredEncoding('unknown,gzip'));
        static::assertEquals('gzip', $request->preferredEncoding('sdch,gzip'));
        static::assertEquals('unknown', $request->preferredEncoding('unknown,other,test'));
        static::assertEquals('unknown,unknown', $request->preferredLanguage(['unknown,unknown', 'other']));
    }

    public function testAcceptLanguage()
    {
        $request = Request::create('/test?foo=bar_get', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5,zh;f=0;q=0.4',
        ]);
        static::assertEquals([
            'en-us' => [
                'index' => 0,
                'q' => 1
            ],
            'en' => [
                'index' => 1,
                'q' => 0.5
            ],
            'zh' => [
                'index' => 2,
                'q' => 0.4,
                'f' => '0'
            ],
        ], $request->languages());
        static::assertEquals('en-us', $request->preferredLanguage());

        static::assertEquals('en', $request->preferredLanguage('en,zh'));
        static::assertEquals('zh-CN', $request->preferredLanguage('fr,ja,zh-CN'));
        static::assertEquals('zh_CN', $request->preferredLanguage('fr,ja,zh_CN'));
        static::assertEquals('zh', $request->preferredLanguage('fr,ja,zh_CN,zh'));
        static::assertEquals('en', $request->preferredLanguage('fr,ja,cs,zh,en'));
        static::assertEquals('fr', $request->preferredLanguage('fr,ja,cs'));
        static::assertEquals('fr,fr', $request->preferredLanguage(['fr,fr', 'ja', 'cs']));
    }

    public function testAcceptCharsets()
    {
        $request = Request::create('/test?foo=bar_get', 'GET', [], [], [], [
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
        ]);
        static::assertEquals([
            'ISO-8859-1' => [
                'index' => 0,
                'q' => 1
            ],
            'utf-8' => [
                'index' => 1,
                'q' => 0.7
            ],
            '*' => [
                'index' => 2,
                'q' => 0.7
            ],
        ], $request->charsets());
        static::assertEquals('ISO-8859-1', $request->preferredCharset());
        static::assertEquals('ISO-8859-1', $request->preferredCharset('gbk,ISO-8859-1'));
        static::assertEquals('ISO-8859-1', $request->preferredCharset('utf-8,ISO-8859-1'));
        static::assertEquals('gbk', $request->preferredCharset('gbk,big5'));
        static::assertEquals('gbk,gbk', $request->preferredLanguage(['gbk,gbk', 'big5']));
    }

    /**
     * @dataProvider getContentTypeData
     * @param $contentType
     * @param $format
     * @param null $type
     */
    public function testContentTypeFormat($contentType, $format, $type = null)
    {
        $request = Request::create(null, 'GET', [], [], [], [
            'HTTP_Content-Type' => $contentType,
        ]);
        static::assertEquals($contentType, $request->contentType());
        static::assertEquals($format, $request->format());

        $request = Request::create(null, 'GET');
        $request->setContentType($contentType);
        static::assertEquals($contentType, $request->contentType());
        static::assertEquals($format, $request->format());

        $request = Request::create(null, 'GET');
        $request->setFormat($format);
        static::assertEquals($type ?: $contentType, $request->contentType());
        static::assertEquals($format, $request->format());
    }

    public function getContentTypeData()
    {
        return [
            ['text/html', 'html'],
            ['application/xhtml+xml', 'html', 'text/html'],

            ['text/plain', 'txt'],
            ['text/css', 'css'],

            ['application/javascript', 'js'],
            ['application/x-javascript', 'js', 'application/javascript'],
            ['text/javascript', 'js', 'application/javascript'],

            ['text/xml', 'xml'],
            ['application/xml', 'xml', 'text/xml'],
            ['application/x-xml', 'xml', 'text/xml'],

            ['application/json', 'json'],
            ['application/x-json', 'json', 'application/json'],

            ['application/rdf+xml', 'rdf'],
            ['application/atom+xml', 'atom'],
            ['application/rss+xml', 'rss'],
            ['application/x-www-form-urlencoded', 'form'],
        ];
    }

    public function testIsContentType()
    {
        $request = Request::create(null);
        static::assertNull($request->contentType());
        static::assertNull($request->format());

        $request->setContentType('text/html')->setContentType(null);
        static::assertNull($request->contentType());
        static::assertNull($request->format());

        $request->setFormat('html')->setFormat(null);
        static::assertNull($request->contentType());
        static::assertNull($request->format());

        static::assertFalse($request->isAjax());
        static::assertFalse($request->isJson());
        static::assertFalse($request->isXml());

        $request = Request::create(null, 'GET', [], [], [], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);
        static::assertTrue($request->isAjax());

        $request = Request::create(null, 'GET', [], [], [], [
            'HTTP_Content-Type' => 'application/x-json',
        ]);
        static::assertTrue($request->isJson());

        $request = Request::create(null, 'GET', [], [], [], [
            'HTTP_Content-Type' => 'application/json',
        ]);
        static::assertTrue($request->isJson());

        $request = Request::create(null, 'GET', [], [], [], [
            'HTTP_Content-Type' => 'text/xml',
        ]);
        static::assertTrue($request->isXml());

        $request = Request::create(null, 'GET', [], [], [], [
            'HTTP_Content-Type' => 'application/x-xml',
        ]);
        static::assertTrue($request->isXml());
    }

    /**
     * 代理报文测试
     * @dataProvider getForwardedData
     * @param $url
     * @param $server
     * @param $trustIps
     * @param $scheme
     * @param $host
     * @param $port
     * @param $ips
     * @param $ip
     */
    public function testForwarded($url, $server, $trustIps, $scheme, $host, $port, $ips, $ip)
    {
        Request::setTrustedClient($trustIps);

        $request = Request::create($url, 'GET', [], [], [], $server);
        static::assertEquals($scheme, $request->scheme());
        static::assertEquals($host, $request->host());
        static::assertEquals($port, $request->port());
        static::assertEquals($ips, $request->ips());
        static::assertEquals($ip, $request->ip());
    }

    public function getForwardedData()
    {
        return [
            [
                'http://www.test.com',
                [
                    'REMOTE_ADDR' => '127.0.0.1',
                    'HTTP_FORWARDED' => 'for=210.242.32.241,for=127.0.0.1,for=221.254.78.265,for=2620:0:1cfe:face:b00c::1,for=::1,for=2001:db8:;host=www.test2.com;proto=https;port=8080;',
                ],
                null,
                'http',
                'www.test.com',
                80,
                ['210.242.32.241', '127.0.0.1', '2620:0:1cfe:face:b00c::1', '::1'],
                '127.0.0.1',
            ],

            [
                'http://www.test.com',
                [
                    'REMOTE_ADDR' => '127.0.0.1',
                    'HTTP_FORWARDED' => 'for=210.242.32.241,for=127.0.0.1,for=221.254.78.265,for=2620:0:1cfe:face:b00c::1,for=::1,for=2001:db8:;host=[::1]:8080;proto=https',
                ],
                '127.0.0.1',
                'https',
                '[::1]',
                8080,
                ['210.242.32.241', '127.0.0.1', '2620:0:1cfe:face:b00c::1', '::1'],
                '210.242.32.241',
            ],

            [
                'http://www.test.com',
                [
                    'REMOTE_ADDR' => '127.0.0.1',
                    'HTTP_FORWARDED' => 'for=210.242.32.241,for=127.0.0.1,for=221.254.78.265,for=2620:0:1cfe:face:b00c::1,for=::1,for=2001:db8:;host=www.test2.com;proto=https;port=8080;',
                    'HTTP_X_FORWARDED_FOR' => '210.242.32.242,127.0.0.2,221.254.78.266,2620:0:1cfe:face:b00c::2,::2,2001:db8:',
                    'HTTP_X_FORWARDED_HOST' => 'www.test3.com',
                    'HTTP_X_FORWARDED_PROTO' => 'https',
                    'HTTP_X_FORWARDED_PORT' => 9090,
                ],
                '127.0.0.1',
                'https',
                'www.test3.com',
                9090,
                ['210.242.32.242', '127.0.0.2', '2620:0:1cfe:face:b00c::2', '::2'],
                '210.242.32.242',
            ],

            [
                'http://www.test.com',
                [
                    'REMOTE_ADDR' => '127.0.0.1',
                    'HTTP_FORWARDED' => 'for=210.242.32.241,for=127.0.0.1,for=221.254.78.265,for=2620:0:1cfe:face:b00c::1,for=::1,for=2001:db8:;host=www.test2.com;proto=https;port=8080;',
                    'HTTP_X_FORWARDED_FOR' => '210.242.32.242,127.0.0.2,221.254.78.266,2620:0:1cfe:face:b00c::2,::2,2001:db8:',
                    'HTTP_X_FORWARDED_HOST' => 'www.test3.com',
                    'HTTP_X_FORWARDED_PROTO' => 'https',
                    'HTTP_X_FORWARDED_PORT' => 9090,
                ],
                null,
                'http',
                'www.test.com',
                80,
                ['210.242.32.242', '127.0.0.2', '2620:0:1cfe:face:b00c::2', '::2'],
                '127.0.0.1',
            ],
        ];
    }

    public function testRequestEmptyPath()
    {
        $request = Request::create('/', 'GET');
        $request->server->set([
            'SCRIPT_FILENAME' => '/data/site/public/index.php',
            'SCRIPT_NAME' => '/index.php',
            'PHP_SELF' => '/index.php'
        ]);
        static::assertSame('/', $request->uri());
        static::assertSame('/', $request->fullPath());
        static::assertSame('', $request->baseUrl());
        static::assertSame('', $request->basePath());
        static::assertSame('/', $request->pathInfo());
        static::assertSame([], $request->pathArr());
        static::assertSame('', $request->queryString());

        $request = Request::create('/public/index.php', 'GET');
        $request->server->set([
            'SCRIPT_FILENAME' => '/data/site/public/index.php',
            'SCRIPT_NAME' => '/public/index.php',
            'PHP_SELF' => '/public/index.php'
        ]);
        static::assertSame('/public/index.php', $request->uri());
        static::assertSame('/public/index.php', $request->fullPath());
        static::assertSame('/public/index.php', $request->baseUrl());
        static::assertSame('/public', $request->basePath());
        static::assertSame('/', $request->pathInfo());
        static::assertSame([], $request->pathArr());
        static::assertSame('', $request->queryString());
    }

    public function testResetRequestPathInfo()
    {
        // normal
        $request = Request::create('/public/index.php/foo/bar/hello world?foo=hello world', 'GET');
        $request->server->set([
            'SCRIPT_FILENAME' => '/data/site/public/index.php',
            'SCRIPT_NAME' => '/public/index.php',
            'PHP_SELF' => '/public/index.php/foo/bar/hello world?foo=hello world'
        ]);
        static::assertSame('/public/index.php/foo/bar/hello%20world?foo=hello%20world', $request->uri());
        static::assertSame('/public/index.php', $request->baseUrl());
        static::assertSame('/public', $request->basePath());
        static::assertSame('/foo/bar/hello%20world', $request->pathInfo());
        static::assertSame('foo=hello%20world', $request->queryString());

        static::assertSame($request, $request->setPathInfo('/hello/world/foo bar'));
        static::assertSame('/public/index.php/hello/world/foo%20bar?foo=hello%20world', $request->uri());
        static::assertSame('/public/index.php', $request->baseUrl());
        static::assertSame('/public', $request->basePath());
        static::assertSame('/hello/world/foo%20bar', $request->pathInfo());
        static::assertSame('foo=hello%20world', $request->queryString());

        //rewrite
        $request = Request::create('/public/foo/bar/hello world?foo=hello world', 'GET');
        $request->server->set([
            'SCRIPT_FILENAME' => '/data/site/public/index.php',
            'SCRIPT_NAME' => '/public/index.php',
            'PHP_SELF' => '/public/index.php/foo/bar/hello world?foo=hello world'
        ]);
        static::assertSame('/public/foo/bar/hello%20world?foo=hello%20world', $request->uri());
        static::assertSame('/public', $request->baseUrl());
        static::assertSame('/public', $request->basePath());
        static::assertSame('/foo/bar/hello%20world', $request->pathInfo());
        static::assertSame('foo=hello%20world', $request->queryString());

        static::assertSame($request, $request->setPathInfo('/hello/world/foo bar'));
        static::assertSame('/public/hello/world/foo%20bar?foo=hello%20world', $request->uri());
        static::assertSame('/public', $request->baseUrl());
        static::assertSame('/public', $request->basePath());
        static::assertSame('/hello/world/foo%20bar', $request->pathInfo());
        static::assertSame('foo=hello%20world', $request->queryString());

        //rewrite2
        $request = Request::create('/public/foo/bar/hello world?foo=hello world', 'GET');
        $request->server->set([
            'SCRIPT_FILENAME' => '/data/site/public/index.php',
            'SCRIPT_NAME' => '/index.php',
            'PHP_SELF' => '/index.php/public/foo/bar/hello world?foo=hello world'
        ]);
        static::assertSame('/public/foo/bar/hello%20world?foo=hello%20world', $request->uri());
        static::assertSame('', $request->baseUrl());
        static::assertSame('', $request->basePath());
        static::assertSame('/public/foo/bar/hello%20world', $request->pathInfo());
        static::assertSame('foo=hello%20world', $request->queryString());

        static::assertSame($request, $request->setPathInfo('/public/hello/world/foo bar'));
        static::assertSame('/public/hello/world/foo%20bar?foo=hello%20world', $request->uri());
        static::assertSame('', $request->baseUrl());
        static::assertSame('', $request->basePath());
        static::assertSame('/public/hello/world/foo%20bar', $request->pathInfo());
        static::assertSame('foo=hello%20world', $request->queryString());

        static::assertSame($request, $request->setPathInfo('/hello/world/foo bar'));
        static::assertSame('/hello/world/foo%20bar?foo=hello%20world', $request->uri());
        static::assertSame('', $request->baseUrl());
        static::assertSame('', $request->basePath());
        static::assertSame('/hello/world/foo%20bar', $request->pathInfo());
        static::assertSame('foo=hello%20world', $request->queryString());
    }

    /**
     * @dataProvider getUrlParseData
     * @param $createUri
     * @param $server
     * @param $script
     * @param $httpHost
     * @param $scheme
     * @param $port
     * @param $host
     * @param $uri
     * @param $fullPath
     * @param $basePath
     * @param $baseUrl
     * @param $queryString
     * @param $pathInfo
     * @param $decodePath
     * @param $pathArr
     * @param $root
     * @param $url
     */
    public function testUrlParse(
        $createUri,
        $server, $script,
        $httpHost, $scheme, $port, $host,
        $uri, $fullPath, $basePath, $baseUrl,
        $queryString,
        $pathInfo, $decodePath, $pathArr,
        $root, $url
    )
    {
        $request = Request::create($createUri, 'GET');
        $request->server->set($server);
        static::assertSame($script, $request->script());
        static::assertSame($httpHost, $request->httpHost());
        static::assertSame($scheme, $request->scheme());
        static::assertSame($port, $request->port());
        static::assertSame($host, $request->host());

        static::assertSame($uri, $request->uri());
        static::assertSame($fullPath, $request->fullPath());
        static::assertSame($basePath, $request->baseUrl());
        static::assertSame($baseUrl, $request->basePath());

        static::assertSame($queryString, $request->queryString());
        static::assertSame(rawurldecode($queryString), $request->decodeQuery());
        static::assertSame($pathInfo, $request->pathInfo());
        static::assertSame($decodePath, $request->decodePath());
        static::assertSame($pathArr, $request->pathArr());
        static::assertSame($root, $request->root());
        static::assertSame($url, $request->url());
    }

    public function getUrlParseData()
    {
        /*
         * example :
         *    DOCUMENT_ROOT  >  /data/site/
         *    SCRIPT_FILENAME >  /data/site/public/index.php
         */
        return [
            // Domain
            [
                'http://www.test.com/',
                [],
                '',
                'http://www.test.com',
                'http',
                80,
                'www.test.com',
                '/',
                '/',
                '',
                '',
                '',
                '/',
                '/',
                [],
                'http://www.test.com',
                'http://www.test.com/',
            ],
            // Create
            [
                'http://www.test.com/public/index.php/foo/bar/hello world?foo=hello world',
                [],
                '',
                'http://www.test.com',
                'http',
                80,
                'www.test.com',
                '/public/index.php/foo/bar/hello%20world?foo=hello%20world',
                '/public/index.php/foo/bar/hello%20world',
                '',
                '',
                'foo=hello%20world',
                '/public/index.php/foo/bar/hello%20world',
                '/public/index.php/foo/bar/hello world',
                ['public', 'index.php', 'foo', 'bar', 'hello world'],
                'http://www.test.com',
                'http://www.test.com/public/index.php/foo/bar/hello%20world?foo=hello%20world',
            ],
            // Normal
            [
                'http://www.test.com/public/index.php/foo/bar/hello world?foo=hello world',
                [
                    'SCRIPT_FILENAME' => '/data/site/public/index.php',
                    'SCRIPT_NAME' => '/public/index.php',
                ],
                '/public/index.php',
                'http://www.test.com',
                'http',
                80,
                'www.test.com',
                '/public/index.php/foo/bar/hello%20world?foo=hello%20world',
                '/public/index.php/foo/bar/hello%20world',
                '/public/index.php',
                '/public',
                'foo=hello%20world',
                '/foo/bar/hello%20world',
                '/foo/bar/hello world',
                ['foo', 'bar', 'hello world'],
                'http://www.test.com/public/index.php',
                'http://www.test.com/public/index.php/foo/bar/hello%20world?foo=hello%20world',
            ],

            // Normal url with double slash
            [
                'https://www.test.com/public/index.php/foo//bar/hello world/?foo=hello world',
                [
                    'SCRIPT_FILENAME' => '/data/site/public/index.php',
                    'SCRIPT_NAME' => '/public/index.php',
                ],
                '/public/index.php',
                'https://www.test.com',
                'https',
                443,
                'www.test.com',
                '/public/index.php/foo/bar/hello%20world?foo=hello%20world',
                '/public/index.php/foo/bar/hello%20world',
                '/public/index.php',
                '/public',
                'foo=hello%20world',
                '/foo/bar/hello%20world',
                '/foo/bar/hello world',
                ['foo', 'bar', 'hello world'],
                'https://www.test.com/public/index.php',
                'https://www.test.com/public/index.php/foo/bar/hello%20world?foo=hello%20world',
            ],

            /*
             *  Rewrite A ( ^public/(.*)$  =>  public/index.php/$1 )
             */
            // IIS + ISAPI_Rewrite
            [
                'http://www.test.com:8080/public/foo/bar/hello world?foo=hello world',
                [
                    'SCRIPT_FILENAME' => '/data/site/public/index.php',
                    'SCRIPT_NAME' => '/public/index.php',
                    'X_REWRITE_URL' => '/public/foo/bar/hello%20world?foo=hello%20world',
                    'PHP_SELF' => '/public/index.php/foo/bar/hello world',
                ],
                '/public/index.php',
                'http://www.test.com:8080',
                'http',
                8080,
                'www.test.com',
                '/public/foo/bar/hello%20world?foo=hello%20world',
                '/public/foo/bar/hello%20world',
                '/public',
                '/public',
                'foo=hello%20world',
                '/foo/bar/hello%20world',
                '/foo/bar/hello world',
                ['foo', 'bar', 'hello world'],
                'http://www.test.com:8080/public',
                'http://www.test.com:8080/public/foo/bar/hello%20world?foo=hello%20world',
            ],

            // IIS + Microsoft Rewrite Module
            [
                'http://www.test.com/public/foo/bar/hello world?foo=hello world',
                [
                    'SCRIPT_FILENAME' => '/data/site/public/index.php',
                    'SCRIPT_NAME' => '/public/index.php',
                    'X_ORIGINAL_URL' => '/public/foo/bar/hello%20world?foo=hello%20world',
                    'PHP_SELF' => '/public/index.php/foo/bar/hello world',
                ],
                '/public/index.php',
                'http://www.test.com',
                'http',
                80,
                'www.test.com',
                '/public/foo/bar/hello%20world?foo=hello%20world',
                '/public/foo/bar/hello%20world',
                '/public',
                '/public',
                'foo=hello%20world',
                '/foo/bar/hello%20world',
                '/foo/bar/hello world',
                ['foo', 'bar', 'hello world'],
                'http://www.test.com/public',
                'http://www.test.com/public/foo/bar/hello%20world?foo=hello%20world',
            ],

            // IIS + URL Rewrite
            [
                'http://www.test.com/public/foo/bar/hello world?foo=hello world',
                [
                    'SCRIPT_FILENAME' => '/data/site/public/index.php',
                    'SCRIPT_NAME' => '/public/index.php',
                    'IIS_WasUrlRewritten' => 1,
                    'UNENCODED_URL' => '/public/foo/bar//hello%20world?foo=hello%20world',
                    'PHP_SELF' => '/public/index.php/foo/bar/hello world',
                ],
                '/public/index.php',
                'http://www.test.com',
                'http',
                80,
                'www.test.com',
                '/public/foo/bar/hello%20world?foo=hello%20world',
                '/public/foo/bar/hello%20world',
                '/public',
                '/public',
                'foo=hello%20world',
                '/foo/bar/hello%20world',
                '/foo/bar/hello world',
                ['foo', 'bar', 'hello world'],
                'http://www.test.com/public',
                'http://www.test.com/public/foo/bar/hello%20world?foo=hello%20world',
            ],

            // Only REQUEST_URI
            [
                'http://www.test.com/public/foo/bar/hello world?foo=hello world',
                [
                    'SCRIPT_FILENAME' => '/data/site/public/index.php',
                    'SCRIPT_NAME' => '/public/index.php',
                    'REQUEST_URI' => '/public/foo/bar/hello world?foo=hello%20world',
                    'PHP_SELF' => '/public/index.php/foo/bar/hello world',
                ],
                '/public/index.php',
                'http://www.test.com',
                'http',
                80,
                'www.test.com',
                '/public/foo/bar/hello%20world?foo=hello%20world',
                '/public/foo/bar/hello%20world',
                '/public',
                '/public',
                'foo=hello%20world',
                '/foo/bar/hello%20world',
                '/foo/bar/hello world',
                ['foo', 'bar', 'hello world'],
                'http://www.test.com/public',
                'http://www.test.com/public/foo/bar/hello%20world?foo=hello%20world',
            ],

            /*
             *  Rewrite A ( ^(.*)$  =>  public/index.php/$1 )
             */
            // IIS + ISAPI_Rewrite
            [
                'http://www.test.com/public/foo/bar/hello world?foo=hello world',
                [
                    'SCRIPT_FILENAME' => '/data/site/public/index.php',
                    'SCRIPT_NAME' => '/public/index.php',
                    'X_REWRITE_URL' => '/public/foo/bar/hello%20world?foo=hello%20world',
                    'PHP_SELF' => '/public/index.php/public/foo/bar/hello world',
                ],
                '/public/index.php',
                'http://www.test.com',
                'http',
                80,
                'www.test.com',
                '/public/foo/bar/hello%20world?foo=hello%20world',
                '/public/foo/bar/hello%20world',
                '',
                '',
                'foo=hello%20world',
                '/public/foo/bar/hello%20world',
                '/public/foo/bar/hello world',
                ['public', 'foo', 'bar', 'hello world'],
                'http://www.test.com',
                'http://www.test.com/public/foo/bar/hello%20world?foo=hello%20world',
            ],

            // IIS + Microsoft Rewrite Module
            [
                'http://www.test.com/public/foo/bar/hello world?foo=hello world',
                [
                    'SCRIPT_FILENAME' => '/data/site/public/index.php',
                    'SCRIPT_NAME' => '/public/index.php',
                    'X_ORIGINAL_URL' => '/public/foo/bar/hello%20world?foo=hello%20world',
                    'PHP_SELF' => '/public/index.php/public/foo/bar/hello world',
                ],
                '/public/index.php',
                'http://www.test.com',
                'http',
                80,
                'www.test.com',
                '/public/foo/bar/hello%20world?foo=hello%20world',
                '/public/foo/bar/hello%20world',
                '',
                '',
                'foo=hello%20world',
                '/public/foo/bar/hello%20world',
                '/public/foo/bar/hello world',
                ['public', 'foo', 'bar', 'hello world'],
                'http://www.test.com',
                'http://www.test.com/public/foo/bar/hello%20world?foo=hello%20world',
            ],

            // IIS + URL Rewrite
            [
                'http://www.test.com/public/foo/bar/hello world?foo=hello world',
                [
                    'SCRIPT_FILENAME' => '/data/site/public/index.php',
                    'SCRIPT_NAME' => '/public/index.php',
                    'IIS_WasUrlRewritten' => 1,
                    'UNENCODED_URL' => '/public/foo/bar//hello%20world?foo=hello%20world',
                    'PHP_SELF' => '/public/index.php/public/foo/bar/hello world',
                ],
                '/public/index.php',
                'http://www.test.com',
                'http',
                80,
                'www.test.com',
                '/public/foo/bar/hello%20world?foo=hello%20world',
                '/public/foo/bar/hello%20world',
                '',
                '',
                'foo=hello%20world',
                '/public/foo/bar/hello%20world',
                '/public/foo/bar/hello world',
                ['public', 'foo', 'bar', 'hello world'],
                'http://www.test.com',
                'http://www.test.com/public/foo/bar/hello%20world?foo=hello%20world',
            ],

            // Only REQUEST_URI
            [
                'http://www.test.com/public/foo/bar/hello world?foo=hello world',
                [
                    'SCRIPT_FILENAME' => '/data/site/public/index.php',
                    'SCRIPT_NAME' => '/public/index.php',
                    'REQUEST_URI' => '/public/foo/bar/hello world?foo=hello%20world',
                    'PHP_SELF' => '/public/index.php/public/foo/bar/hello world',
                ],
                '/public/index.php',
                'http://www.test.com',
                'http',
                80,
                'www.test.com',
                '/public/foo/bar/hello%20world?foo=hello%20world',
                '/public/foo/bar/hello%20world',
                '',
                '',
                'foo=hello%20world',
                '/public/foo/bar/hello%20world',
                '/public/foo/bar/hello world',
                ['public', 'foo', 'bar', 'hello world'],
                'http://www.test.com',
                'http://www.test.com/public/foo/bar/hello%20world?foo=hello%20world',
            ],
        ];
    }
}
