<?php

use Tanbolt\Http\Request;
use Tanbolt\Http\Response;
use Tanbolt\Cookie\Cookie;
use Tanbolt\Session\Session;
use Tanbolt\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{

    public function setUp():void
    {
        PHPUNIT_LOADER::addDir('Tanbolt\Response\Fixtures', __DIR__.'/Fixtures');
        parent::setUp();
    }

    public function testConstructAndReset()
    {
        $response = new Response();
        static::assertEmpty($response->getBody());
        static::assertEquals(200, $response->statusCode());
        static::assertEquals([], $response->header->all());
        static::assertEquals($response::DEFAULT_CHARSET, $response->charset());
        static::assertEquals($response::DEFAULT_VERSION, $response->protocolVersion());

        $response = new Response('foo', 301, ['foo' => 'bar'], 'gbk', 1.1);
        static::assertEquals('foo', $response->getBody());
        static::assertEquals(301, $response->statusCode());
        static::assertEquals(['Foo' => ['bar']], $response->header->all());
        static::assertEquals('gbk', $response->charset());
        static::assertEquals(1.1, $response->protocolVersion());

        static::assertSame($response, $response->reset('bar', 302, ['hel' => 'wod'], 'utf-8', 1.0));
        static::assertEquals('bar', $response->getBody());
        static::assertEquals(302, $response->statusCode());
        static::assertEquals(['Hel' => ['wod']], $response->header->all());
        static::assertEquals('utf-8', $response->charset());
        static::assertEquals(1.0, $response->protocolVersion());
    }

    public function testWithRequest()
    {
        // 顺带测试下 isCacheable / isUnModified  这两个方法与 request 有关系
        $response = new Response();
        static::assertFalse($response->isUnModified());
        static::assertEquals(Response::DEFAULT_VERSION, $response->protocolVersion());

        // 未绑定 Request 时的 isCacheable
        static::assertFalse($response->isCacheable());
        static::assertFalse($response->isCacheable(true));

        $response->setETag('tag');
        static::assertTrue($response->isCacheable());
        static::assertTrue($response->isCacheable(true));

        $response->setETag(null);
        static::assertFalse($response->isCacheable());
        static::assertFalse($response->isCacheable(true));

        $response->setMaxAge(1000);
        static::assertTrue($response->isCacheable());
        static::assertTrue($response->isCacheable(true));

        $response->setStatus(500);
        static::assertTrue($response->isCacheable());
        static::assertFalse($response->isCacheable(true));

        $response->noStore();
        static::assertFalse($response->isCacheable());
        static::assertFalse($response->isCacheable(true));

        $response->setStatus(200)->setETag('tag')->header->clearCacheControl();
        static::assertTrue($response->isCacheable());
        static::assertTrue($response->isCacheable(true));

        // 绑定 Request
        $req = new Request();
        static::assertSame($response, $response->withRequest($req));
        static::assertSame($req, $response->request());

        // 绑定 Request 后的 protocolVersion
        static::assertEquals($req->protocolVersion(), $response->protocolVersion());
        $req->setProtocolVersion(2);
        static::assertEquals(2, $response->protocolVersion());

        // 绑定 Request 后的 isCacheable
        $req->setMethod('PUT');
        static::assertTrue($response->isCacheable());
        static::assertFalse($response->isCacheable(true));

        // isUnModified
        $req->setETags('tag');
        static::assertTrue($response->isUnModified());
        $req->setETags(null);
        static::assertFalse($response->isUnModified());

        static::assertSame($response, $response->setLastModified('2000-10-10'));
        static::assertFalse($response->isUnModified());
        $req->header->setDate('If-Modified-Since', '2000-10-10');
        static::assertTrue($response->isUnModified());

        static::assertSame($response, $response->withRequest(null));
        static::assertNull($response->request());
    }

    /**
     * @runInSeparateProcess
     */
    public function testWithCookie()
    {
        if (!class_exists(Cookie::class)) {
            static::markTestSkipped('Cookie class not exist, Skip testWithCookie');
        }
        // response 自动发送 Cookie 对象设置的 cookie value
        $cookie = new Cookie();
        $response = new Response();
        static::assertSame($response, $response->withCookie($cookie));
        static::assertSame($cookie, $response->cookie());
        static::assertEquals([], $response->getSentCookies());

        $cookie->add('foo', 'foo');
        $cookie->add('bar', 'bar');
        $sendCookies = $response->getSentCookies();
        static::assertCount(2, $sendCookies);
        static::assertArraySub(['name' => 'bar', 'value' => 'bar'], $sendCookies[0]);
        static::assertArraySub(['name' => 'foo', 'value' => 'foo'], $sendCookies[1]);
        $sendCookies = $response->getSentCookies(true);
        static::assertCount(2, $sendCookies);
        static::assertTrue(false !== strpos($sendCookies[0], 'bar=bar'));
        static::assertTrue(false !== strpos($sendCookies[1], 'foo=foo'));

        // 测试 session cookie
        if (!class_exists(Session::class)) {
            static::markTestSkipped('Session class not exist, Skip testWithCookie');
        }
        $currentConf = false !== filter_var(ini_get('session.use_cookies'), FILTER_VALIDATE_BOOLEAN);
        ini_set('session.use_cookies', true);

        // 不开启 session
        $req = new Request();
        $response->withRequest($req);
        static::assertCount(2, $response->getSentCookies());

        // 开启 session
        $req->withSession($session = new Session());
        $session->setIni([
            'save_handler' => 'memory',
            'name' => 'sessionId'
        ], true)->start();
        $sendCookies = $response->getSentCookies(true);
        static::assertCount(3, $sendCookies);
        static::assertTrue((bool) preg_match('/sessionId=([^;]+);/', $sendCookies[2], $matches));
        $session->__destruct();
        $cookieValue = $matches[1];

        // 处理新请求
        $req->reset([], [], []);
        $session->start();
        $sendCookies = $response->getSentCookies(true);
        static::assertCount(3, $sendCookies);
        static::assertTrue((bool) preg_match('/sessionId=([^;]+);/', $sendCookies[2], $matches));
        $session->__destruct();

        // session cookie 的 value 应该是重新生成的
        static::assertNotEquals($cookieValue, $matches[1]);

        // 处理新请求 (客户端已有 session cookie, 不应该再次发送 session cookie 了)
        $req->reset([], [], ['sessionId' => 'foo']);
        $session->start();
        $sendCookies = $response->getSentCookies(true);
        static::assertCount(2, $sendCookies);
        $session->__destruct();

        // 顺带测试下 header
        $response->header->set('Set-Cookie', 'biz=biz');
        $headers = $response->getSentHeaders(false);
        static::assertArrayHasKey('Set-Cookie', $headers);
        static::assertCount(1, $headers['Set-Cookie']);
        static::assertTrue(false !== strpos($headers['Set-Cookie'][0], 'biz=biz'));
        $headers = $response->getSentHeaders();
        static::assertArrayHasKey('Set-Cookie', $headers);
        static::assertCount(3, $headers['Set-Cookie']);
        static::assertTrue(false !== strpos($headers['Set-Cookie'][0], 'bar=bar'));
        static::assertTrue(false !== strpos($headers['Set-Cookie'][1], 'foo=foo'));
        static::assertTrue(false !== strpos($headers['Set-Cookie'][2], 'biz=biz'));

        ini_set('session.use_cookies', $currentConf);
    }

    protected static function assertArraySub($expected, $result)
    {
        static::assertTrue(
            empty(array_diff_assoc($expected, $result))
        );
    }

    public function testWithFilesystem()
    {
        if (!class_exists(Filesystem::class)) {
            static::markTestSkipped('Filesystem class not exist, Skip testSetFilesystem');
        }
        $response = new Response();
        static::assertNull($response->filesystem());
        static::assertNull($response->setFile('a', null)->filesystem());

        $filesystem = new Filesystem();
        static::assertSame($response, $response->withFilesystem($filesystem));
        static::assertSame($filesystem, $response->filesystem());
        static::assertSame($filesystem, $response->setFile('a', null)->filesystem());

        static::assertSame($response, $response->withFilesystem(null));
        static::assertNull($response->filesystem());
        static::assertNull($response->setFile('a', null)->filesystem());
    }

    public function testVersion()
    {
        $response = new Response();
        static::assertEquals(Response::DEFAULT_VERSION, $response->protocolVersion());
        static::assertSame($response, $response->setProtocolVersion('1.2'));
        static::assertEquals('1.2', $response->protocolVersion());
    }

    public function testStatusCode()
    {
        $response = new Response();
        static::assertEquals(200, $response->statusCode());
        static::assertSame($response, $response->setStatus(500));
        static::assertEquals(500, $response->statusCode());
    }

    public function testStatusCodeCheck()
    {
        $response = new Response();
        foreach (Response::$statusTexts as $code => $text) {
            $response->setStatus($code);
            if (200 === $code) {
                static::assertTrue($response->isOk());
            } else {
                static::assertFalse($response->isOk());
            }
            if (403 === $code) {
                static::assertTrue($response->isForbidden());
            } else {
                static::assertFalse($response->isForbidden());
            }
            if (404 === $code) {
                static::assertTrue($response->isNotFound());
            } else {
                static::assertFalse($response->isNotFound());
            }
            if (in_array($code, [204, 304])) {
                static::assertTrue($response->isEmpty());
            } else {
                static::assertFalse($response->isEmpty());
            }

            if ($code >= 100 && $code < 200) {
                static::assertTrue($response->isInformational());
            } else {
                static::assertFalse($response->isInformational());
            }
            if ($code >= 200 && $code < 300) {
                static::assertTrue($response->isSuccessful());
            } else {
                static::assertFalse($response->isSuccessful());
            }
            if ($code >= 300 && $code < 400) {
                static::assertTrue($response->isRedirection());
            } else {
                static::assertFalse($response->isRedirection());
            }
            if (in_array($code, [201, 301, 302, 303, 307, 308])) {
                static::assertTrue($response->isRedirect());
            } else {
                static::assertFalse($response->isRedirect());
            }
            if ($code >= 400 && $code < 500) {
                static::assertTrue($response->isClientError());
            } else {
                static::assertFalse($response->isClientError());
            }
            if ($code >= 500 && $code < 600) {
                static::assertTrue($response->isServerError());
            } else {
                static::assertFalse($response->isServerError());
            }
            static::assertFalse($response->isInvalid());
        }
    }

    public function testRedirect()
    {
        $response = new Response();
        static::assertFalse($response->isRedirect());
        $response->reset(null, 301, ['Location' => 'http://somewhere']);
        static::assertFalse($response->isRedirect('http://'));
        static::assertTrue($response->isRedirect('http://somewhere'));
        static::assertEquals(301, $response->statusCode());

        static::assertSame($response, $response->setRedirect('http://newsite'));
        static::assertTrue($response->isRedirect('http://newsite'));
        static::assertEquals(302, $response->statusCode());

        static::assertSame($response, $response->setRedirect('http://web', 301));
        static::assertTrue($response->isRedirect('http://web'));
        static::assertEquals(301, $response->statusCode());
    }

    public function testCharset()
    {
        $response = new Response();
        static::assertEquals(Response::DEFAULT_CHARSET, $response->charset());
        static::assertSame($response, $response->setCharset('gbk'));
        static::assertEquals('gbk', $response->charset());
    }

    public function testSetHeader()
    {
        $response = new Response();
        $response->setHeader('foo', 'foo');
        static::assertEquals('foo', $response->getHeader('foo'));
        $response->setHeader('foo', 'bar');
        static::assertEquals('bar', $response->getHeader('foo'));

        $response->setHeader('biz', 'foo');
        $response->setHeader('biz', 'bar', false);
        static::assertEquals('foo,bar', $response->getHeader('biz'));
    }

    public function testHeaderCacheControl()
    {
        $response = new Response();
        $response->setHeader('cache-control', 'no-cache,max-age=600,max-stale=500,only-if-cached');
        static::assertEquals(4, $response->header->countCacheControl());
        static::assertEquals([
            'no-cache' => 1,
            'max-age' => 600,
            'max-stale' => 500,
            'only-if-cached' => 1,
        ], $response->header->allCacheControl());

        static::assertFalse($response->header->hasCacheControl('no-store'));
        static::assertTrue($response->header->hasCacheControl('no-cache'));

        static::assertNull($response->header->getCacheControl('no-store'));
        static::assertTrue($response->header->getCacheControl('no-cache'));

        static::assertEquals('test', $response->header->getCacheControl('no-store','test'));
        static::assertEquals(600, $response->header->getCacheControl(['max-age', 'max-stale']));

        static::assertSame($response->header, $response->header->setCacheControl('no-store'));
        static::assertTrue($response->header->getCacheControl('no-store'));

        static::assertSame($response->header, $response->header->removeCacheControl('no-cache'));
        static::assertNull($response->header->getCacheControl('no-cache'));

        $response->header->removeCacheControl('max-age');
        $response->header->removeCacheControl(['max-stale','only-if-cached']);
        static::assertEquals('no-store', $response->getHeader('cache-control'));
        static::assertEquals(1, $response->header->countCacheControl());
        static::assertEquals([
            'no-store' => 1,
        ], $response->header->allCacheControl());

        $response->header->setCacheControl([
            'max-age' => 600,
            'max-stale' => 500,
        ]);
        static::assertEquals([
            'no-store' => 1,
            'max-age' => 600,
            'max-stale' => 500,
        ], $response->header->allCacheControl());

        $response->header->clearCacheControl();
        static::assertEquals(0, $response->header->countCacheControl());
        static::assertEquals([], $response->header->allCacheControl());
    }

    public function testDownloadHeader()
    {
        $response = new Response();
        static::assertFalse($response->isDownloadHeader());

        static::assertSame($response, $response->setDownloadHeader());
        static::assertTrue($response->isDownloadHeader());
        static::assertEquals('attachment', $response->getHeader('Content-Disposition'));
        static::assertEquals('application/octet-stream', $response->getHeader('Content-Type'));

        $response->setDownloadHeader('foo.jpg', 'image/jpeg', 500);
        static::assertEquals('attachment; filename="foo.jpg"', $response->getHeader('Content-Disposition'));
        static::assertEquals(500, $response->getHeader('Content-Length'));
    }

    public function testFormatSet()
    {
        $response = new Response();
        static::assertNull($response->format());
        static::assertSame($response, $response->setFormat('txt'));
        static::assertEquals('txt', $response->format());
        static::assertEquals(['text/plain'], $response->header->get('content-type'));

        $response->header->set('content-type', 'application/javascript');
        static::assertEquals('js', $response->format());
    }

    public function testDate()
    {
        $now = time();
        $response = new Response();
        $date = $response->date();
        static::assertInstanceOf(DateTimeInterface::class, $date);
        static::assertEquals($now, $date->getTimestamp());

        static::assertSame($response, $response->setDate($now + 50));
        static::assertEquals($now + 50, $response->date()->getTimestamp());
    }

    public function testAge()
    {
        $response = new Response();
        static::assertNull($response->header->get('age'));
        static::assertEquals([], $response->header->get('age', []));
        static::assertLessThan(2, $response->age());
        static::assertSame($response, $response->setAge(20));
        static::assertEquals([20], $response->header->get('age'));
        static::assertEquals(20, $response->age());
        static::assertSame($response, $response->setAge(null));
        static::assertFalse($response->header->has('age'));
    }

    public function testNoStore()
    {
        $response = new Response();
        static::assertSame($response, $response->noStore());
        static::assertTrue($response->header->hasCacheControl('no-cache'));
        static::assertTrue($response->header->hasCacheControl('no-store'));
    }

    public function testExpires()
    {
        $timeStr = '2038-01-18 12:00:00';
        $timeStamp = 2147428800;
        $response = new Response();
        static::assertNull($response->expires());

        static::assertSame($response, $response->setExpires($timeStr));
        $expires = $response->expires();
        static::assertInstanceOf('\DateTime', $expires);
        static::assertEquals($timeStamp, $expires->getTimestamp());
        static::assertEquals($timeStr, $expires->format('Y-m-d H:i:s'));
        static::assertEquals($response->getHeader('expires'), $expires->format('D, d M Y H:i:s') . ' GMT');

        static::assertSame($response, $response->setExpires('@'.$timeStamp));
        $expires = $response->expires();
        static::assertInstanceOf('\DateTime', $expires);
        static::assertEquals($timeStamp, $expires->getTimestamp());
        static::assertEquals($timeStr, $expires->format('Y-m-d H:i:s'));
        static::assertEquals($response->getHeader('expires'), $expires->format('D, d M Y H:i:s') . ' GMT');

        static::assertSame($response, $response->setExpires(new \DateTime($timeStr)));
        $expires = $response->expires();
        static::assertInstanceOf('\DateTime', $expires);
        static::assertEquals($timeStamp, $expires->getTimestamp());
        static::assertEquals($timeStr, $expires->format('Y-m-d H:i:s'));
        static::assertEquals($response->getHeader('expires'), $expires->format('D, d M Y H:i:s') . ' GMT');

        static::assertSame($response, $response->setExpires(false));
        static::assertFalse($response->header->has('expires'));
    }

    public function testMaxAge()
    {
        $response = new Response();
        static::assertEquals(0, $response->maxAge());

        $now = new \DateTime();
        $response->header->setDate('Date', $now);
        $response->header->setDate('Expires', '@'.($now->getTimestamp() + 600));
        static::assertEquals(600, $response->maxAge());

        $response->header->setCacheControl('max-age',800);
        static::assertEquals(800, $response->maxAge());

        $response->header->setCacheControl('s-maxage',1000);
        static::assertEquals(1000, $response->maxAge());

        static::assertSame($response, $response->setMaxAge(2000));
        static::assertEquals(1000, $response->maxAge());

        static::assertSame($response, $response->setMaxAge(2000, true));
        static::assertEquals(2000, $response->maxAge());
    }

    public function testPublicAndPrivate()
    {
        $response = new Response();
        static::assertFalse($response->isPublic());
        static::assertTrue($response->isPrivate());

        $response->header->setCacheControl('public');
        static::assertTrue($response->header->hasCacheControl('public'));
        static::assertFalse($response->header->hasCacheControl('private'));
        static::assertFalse($response->header->hasCacheControl('no-store'));
        static::assertTrue($response->isPublic());
        static::assertFalse($response->isPrivate());

        $response->header->setCacheControl('private');
        static::assertTrue($response->header->hasCacheControl('public'));
        static::assertTrue($response->header->hasCacheControl('private'));
        static::assertFalse($response->header->hasCacheControl('no-store'));
        static::assertFalse($response->isPublic());
        static::assertTrue($response->isPrivate());

        $response->header->setCacheControl('no-store');
        static::assertTrue($response->header->hasCacheControl('public'));
        static::assertTrue($response->header->hasCacheControl('private'));
        static::assertTrue($response->header->hasCacheControl('no-store'));
        static::assertFalse($response->isPublic());
        static::assertFalse($response->isPrivate());

        static::assertSame($response, $response->setPublic());
        static::assertTrue($response->isPublic());
        static::assertFalse($response->isPrivate());
        static::assertEquals(0, $response->maxAge());
        static::assertTrue($response->header->hasCacheControl('public'));
        static::assertFalse($response->header->hasCacheControl('private'));
        static::assertFalse($response->header->hasCacheControl('no-cache'));
        static::assertFalse($response->header->hasCacheControl('no-store'));

        $response->setPublic(500);
        static::assertEquals(500, $response->maxAge());

        static::assertSame($response, $response->noStore());
        static::assertFalse($response->isPublic());
        static::assertFalse($response->isPrivate());
        static::assertEquals(0, $response->maxAge());
        static::assertFalse($response->header->hasCacheControl('public'));
        static::assertFalse($response->header->hasCacheControl('private'));
        static::assertTrue($response->header->hasCacheControl('no-cache'));
        static::assertTrue($response->header->hasCacheControl('no-store'));

        static::assertSame($response, $response->setPrivate());
        static::assertFalse($response->isPublic());
        static::assertTrue($response->isPrivate());
        static::assertEquals(0, $response->maxAge());
        static::assertTrue($response->header->hasCacheControl('private'));
        static::assertFalse($response->header->hasCacheControl('public'));
        static::assertTrue($response->header->hasCacheControl('no-cache'));
        static::assertFalse($response->header->hasCacheControl('no-store'));

        $response->setPrivate(600);
        static::assertEquals(600, $response->maxAge());
    }

    public function testLastModified()
    {
        $timeStr = '2000-01-18 12:00:00';
        $timeStamp = 948196800;
        $response = new Response();
        static::assertNull($response->lastModified());

        static::assertSame($response, $response->setLastModified($timeStr));
        $expires = $response->lastModified();
        static::assertInstanceOf('\DateTime', $expires);
        static::assertEquals($timeStamp, $expires->getTimestamp());
        static::assertEquals($timeStr, $expires->format('Y-m-d H:i:s'));
        static::assertEquals($response->getHeader('Last-Modified'), $expires->format('D, d M Y H:i:s') . ' GMT');

        static::assertSame($response, $response->setLastModified('@'.$timeStamp));
        $expires = $response->lastModified();
        static::assertInstanceOf('\DateTime', $expires);
        static::assertEquals($timeStamp, $expires->getTimestamp());
        static::assertEquals($timeStr, $expires->format('Y-m-d H:i:s'));
        static::assertEquals($response->getHeader('Last-Modified'), $expires->format('D, d M Y H:i:s') . ' GMT');

        static::assertSame($response, $response->setLastModified(new \DateTime($timeStr)));
        $expires = $response->lastModified();
        static::assertInstanceOf('\DateTime', $expires);
        static::assertEquals($timeStamp, $expires->getTimestamp());
        static::assertEquals($timeStr, $expires->format('Y-m-d H:i:s'));
        static::assertEquals($response->getHeader('Last-Modified'), $expires->format('D, d M Y H:i:s') . ' GMT');

        static::assertSame($response, $response->setLastModified(false));
        static::assertFalse($response->header->has('Last-Modified'));
    }

    public function testEtag()
    {
        $response = new Response();
        static::assertNull($response->eTag());
        static::assertSame($response, $response->setETag('foo'));
        static::assertEquals('"foo"', $response->eTag());
        static::assertEquals('"foo"', $response->getHeader('etag'));
        $response->setETag('foo', true);
        static::assertEquals('W/"foo"', $response->eTag());
        static::assertEquals('W/"foo"', $response->getHeader('etag'));

        static::assertSame($response, $response->setETag(false));
        static::assertFalse($response->header->has('etag'));
    }

    public function testIsValidate()
    {
        $response = new Response();
        static::assertFalse($response->isValidate());

        $response->setETag('foo');
        static::assertTrue($response->isValidate());
        $response->header->remove('Etag');
        static::assertFalse($response->isValidate());

        $response->setLastModified(new \DateTime());
        static::assertTrue($response->isValidate());
        $response->header->remove('Last-Modified');
        static::assertFalse($response->isValidate());
    }

    public function testIsCacheable()
    {
        $response = new Response();
        static::assertFalse($response->isCacheable());

        $response->setLastModified(new \DateTime());
        static::assertTrue($response->isCacheable());
        $response->header->setCacheControl('no-store');
        static::assertFalse($response->isCacheable());
        $response->header->remove('Last-Modified');

        $response->setETag('foo');
        static::assertFalse($response->isCacheable());
        $response->header->removeCacheControl('no-store');
        static::assertTrue($response->isCacheable());
        $response->header->remove('etag');

        $response->setMaxAge(1000);
        static::assertTrue($response->isCacheable());
        $response->header->setCacheControl('no-store');
        static::assertFalse($response->isCacheable());

        $response->header->removeCacheControl('no-store');
        static::assertTrue($response->isCacheable());
        foreach (Response::$statusTexts as $code => $text) {
            $response->setStatus($code);
            if (in_array($code, [200, 203, 204, 206, 300, 301, 302, 404, 405, 410, 414, 501])) {
                static::assertTrue($response->isCacheable(true));
            } else {
                static::assertTrue($response->isCacheable());
                static::assertFalse($response->isCacheable(true));
            }
        }
    }

    public function testSetJson()
    {
        $response = new Response();
        $json = $response->setJson($data = ['foo' => 'bar']);
        static::assertInstanceOf('Tanbolt\Http\Response\Json', $json);
        static::assertSame($data, $json->data());
        static::assertSame($json, $response->getBody());
        static::assertEquals('{"foo":"bar"}', $response->getSendBody());
        static::assertArrayHasKey('Content-Type', $response->getSentHeaders());
        static::assertTrue(strpos($response->getHeader('content-type'), 'application/json') !== false);
        static::assertEquals('json', $response->format());

        $response = new Response();
        $response->setJson(['foo' => 'bar'],'call');
        static::assertInstanceOf('Tanbolt\Http\Response\Json', $response->getBody());
        static::assertTrue(strpos($response->getSendBody(), 'call({"foo":"bar"})') !== false);
        static::assertArrayHasKey('Content-Type', $response->getSentHeaders());
        static::assertTrue(strpos($response->getHeader('content-type'), 'text/javascript') !== false);
        static::assertEquals('js', $response->format());
    }

    public function testSetFile()
    {
        $response = new Response();

        // 本地文件
        $file = $response->setFile($data = __DIR__.'/Fixtures/File/image.jpg', true);
        static::assertInstanceOf('Tanbolt\Http\Response\File', $file);
        static::assertNull($file->filesystem());
        static::assertEquals($data, $file->file());
        static::assertEquals(Response\File::TYPE_LOCAL_PATH, $file->fileType());
        static::assertSame($file, $response->getBody());

        static::assertArrayHasKey('Content-Type', $response->getSentHeaders());
        static::assertEquals(file_get_contents(__DIR__.'/Fixtures/File/image.jpg'), $response->getSendBody());
        static::assertEquals('jpg', $response->format());
        static::assertTrue(strpos($response->getHeader('Content-Type'), 'image/jpeg') !== false);

        // 文件内容
        $file = $response->setFile($data = 'file_content', null);
        static::assertInstanceOf('Tanbolt\Http\Response\File', $file);
        static::assertNull($file->filesystem());
        static::assertEquals($data, $file->file());
        static::assertEquals(Response\File::TYPE_BINARY, $file->fileType());
        static::assertSame($file, $response->getBody());

        static::assertArrayHasKey('Content-Type', $response->getSentHeaders());
        static::assertEquals($data, $response->getSendBody());
        static::assertEquals('txt', $response->format());

        // resource
        $file = $response->setFile($data = fopen(__DIR__.'/Fixtures/File/foo.txt', 'rb'), null);
        static::assertInstanceOf('Tanbolt\Http\Response\File', $file);
        static::assertNull($file->filesystem());
        static::assertEquals($data, $file->file());
        static::assertEquals(Response\File::TYPE_RESOURCE, $file->fileType());

        // filesystem path
        if (!class_exists(Filesystem::class)) {
            static::markTestSkipped('Filesystem class not exist, Skip testSetFilesystem');
        }
        $filesystem = new Filesystem('domain', 'local', ['root' => __DIR__.'/Fixtures']);
        $file = $response->withFilesystem($filesystem)->setFile($data = 'File/foo.txt', false);
        static::assertInstanceOf('Tanbolt\Http\Response\File', $file);
        static::assertSame($filesystem, $file->filesystem());
        static::assertEquals($data, $file->file());
        static::assertEquals(Response\File::TYPE_SYSTEM_PATH, $file->fileType());
        static::assertSame($file, $response->getBody());

        // filesystem file
        $file = $response->withFilesystem(null)
            ->setFile($data = $filesystem->getObject('File/foo.txt'), false);
        static::assertInstanceOf('Tanbolt\Http\Response\File', $file);
        static::assertNull($file->filesystem());
        static::assertEquals($data, $file->file());
        static::assertEquals(Response\File::TYPE_SYSTEM_FILE, $file->fileType());
    }

    public function testSetBody()
    {
        $response = new Response();
        static::assertEmpty($response->getBody());
        static::assertSame($response, $response->setBody('foo'));
        static::assertEquals('foo', $response->getBody());
        $objectBody = new class {
            public function __toString()
            {
                return 'foo';
            }
        };
        static::assertSame($response, $response->setBody($objectBody));
        static::assertEquals($objectBody, $response->getBody());
    }

    public function testSendHeader()
    {
        $response = new Response(null, 200, ['hello' => 'world']);
        $response->header->add('foo', 'bar')->add('foo', 'bar2');
        $response->setHeader('content-type', 'text/plain');
        $response->setHeader('content-length', 10);
        $testHeader = $response->getSentHeaders();
        static::assertEquals(['bar', 'bar2'], $testHeader['Foo']);
        static::assertEquals(['world'], $testHeader['Hello']);

        $contentType = $testHeader['Content-Type'];
        static::assertCount(1, $contentType);
        $contentType = $contentType[0];
        static::assertTrue(strpos($contentType, 'text/plain') !== false);
        static::assertEquals([10], $testHeader['Content-Length']);
        static::assertEquals(
            sprintf('HTTP/%s %s %s', $response::DEFAULT_VERSION, 200, $response::codeText(200)),
            $testHeader['header']
        );

        $response = new Response(null, 100, ['hello' => 'world']);
        static::assertTrue($response->isInformational());
        $response->setHeader('foo', 'bar');
        $response->setHeader('content-type', 'text/plain');
        $response->setHeader('content-length', 100);

        $testHeader = $response->getSentHeaders();
        static::assertEquals(['bar'], $testHeader['Foo']);
        static::assertEquals(['world'], $testHeader['Hello']);
        static::assertTrue(isset($testHeader['Content-Type']));
        static::assertFalse(isset($testHeader['Content-Length']));
        static::assertEquals(
            sprintf('HTTP/%s %s %s', $response::DEFAULT_VERSION, 100, $response::codeText(100)),
            $testHeader['header']
        );
    }

    public function testSendBody()
    {
        $response = new Response('foo');
        static::assertEquals('foo', $response->getSendBody());

        $body = '';
        $response->sendBody(function ($buffer) use (&$body) {
            $body .= $buffer;
        });
        static::assertEquals('foo', $body);

        ob_start();
        $response->sendBody();
        $render = ob_get_clean();
        static::assertTrue(false !== strpos($render, 'foo'));
    }
}
