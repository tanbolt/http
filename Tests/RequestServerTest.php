<?php

use PHPUnit\Framework\TestCase;
use Tanbolt\Http\Request\Server;

class RequestServerTest extends TestCase
{
    public function testBasic()
    {
        $server = new Server();
        static::assertEquals(0, $server->count());
        static::assertEquals([], $server->keys());
        static::assertEquals([], $server->all());
        static::assertFalse($server->has('foo'));
        static::assertNull($server->get('foo'));

        $arr = ['foo' => 'bar', 'hello' => 'world', 'ni' => 'hao'];
        $server = new Server($arr);
        static::assertEquals(3, $server->count());
        static::assertEquals(['foo', 'hello', 'ni'], $server->keys());
        static::assertEquals($arr, $server->all());

        static::assertTrue($server->has('foo'));
        static::assertFalse($server->has('none'));
        static::assertFalse($server->has(['none', 'foo']));
        static::assertTrue($server->has(['none', 'foo'], true));
        static::assertEquals('bar', $server->get(['none', 'foo']));

        static::assertSame($server, $server->set('ni', 'ok'));
        static::assertEquals('ok', $server->get('ni'));
        static::assertSame($server, $server->setIf('ni', 'ok2'));
        static::assertEquals('ok', $server->get('ni'));

        static::assertSame($server, $server->remove('foo'));
        static::assertEquals(['hello', 'ni'], $server->keys());
        static::assertSame($server, $server->clear());
        static::assertCount(0, $server->all());
    }

    /**
     * @dataProvider getServerHeader
     * @param $server
     * @param $headers
     */
    public function testGetHeaders($server, $headers)
    {
        $parameter = new Server($server);
        static::assertEquals($headers, $parameter->headers());
    }

    public function getServerHeader()
    {
        $digestPhpCgi = 'Digest username="foo", realm="acme", nonce="'.md5('secret').'", uri="/protected, qop="auth"';

        return [

            [
                [
                    'PHP_AUTH_USER' => 'foo'
                ],
                [
                    'AUTHORIZATION' => 'Basic '.base64_encode('foo:'),
                    'PHP_AUTH_USER' => 'foo',
                    'PHP_AUTH_PW' => '',
                ]
            ],

            [
                [
                    'HTTP_AUTHORIZATION' => 'Basic '.base64_encode('foo:bar')
                ],
                [
                    'AUTHORIZATION' => 'Basic '.base64_encode('foo:bar'),
                    'PHP_AUTH_USER' => 'foo',
                    'PHP_AUTH_PW' => 'bar',
                ]
            ],

            [
                [
                    'HTTP_AUTHORIZATION' => 'Basic '.base64_encode('foo:')
                ],
                [
                    'AUTHORIZATION' => 'Basic '.base64_encode('foo:'),
                    'PHP_AUTH_USER' => 'foo',
                    'PHP_AUTH_PW' => '',
                ]
            ],

            [
                [
                    'REDIRECT_HTTP_AUTHORIZATION' => 'Basic '.base64_encode('username:pass:word')
                ],
                [
                    'AUTHORIZATION' => 'Basic '.base64_encode('username:pass:word'),
                    'PHP_AUTH_USER' => 'username',
                    'PHP_AUTH_PW' => 'pass:word',
                ]
            ],

            [
                [
                    'HTTP_AUTHORIZATION' => $digestPhpCgi
                ],
                [
                    'AUTHORIZATION' => $digestPhpCgi,
                    'PHP_AUTH_DIGEST' => $digestPhpCgi,
                ]
            ],

            [
                [
                    'SOME_SERVER_VARIABLE' => 'value',
                    'SOME_SERVER_VARIABLE2' => 'value',
                    'ROOT' => 'value',
                    'HTTP_CONTENT_TYPE' => 'text/html',
                    'HTTP_CONTENT_LENGTH' => '0',
                    'HTTP_ETAG' => 'asdf',
                    'PHP_AUTH_USER' => 'foo',
                    'PHP_AUTH_PW' => 'bar',
                ],
                [
                    'CONTENT_TYPE' => 'text/html',
                    'CONTENT_LENGTH' => '0',
                    'ETAG' => 'asdf',
                    'AUTHORIZATION' => 'Basic '.base64_encode('foo:bar'),
                    'PHP_AUTH_USER' => 'foo',
                    'PHP_AUTH_PW' => 'bar',
                ]
            ],
        ];
    }
}
