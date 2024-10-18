<?php

use PHPUnit\Framework\TestCase;
use Tanbolt\Http\Request\Header;

class RequestHeaderTest extends TestCase
{
    public function testConstruct()
    {
        $parameter = new Header();
        static::assertEquals(0, $parameter->count());
        static::assertEquals([], $parameter->all());
        static::assertEquals([], $parameter->keys());
        static::assertFalse($parameter->has('foo'));

        $arr = ['foo' => 'bar', 'hello' => ['world', 'universe']];
        $parameter = new Header($arr);
        static::assertEquals(2, $parameter->count());
        static::assertEquals(['Foo' => ['bar'], 'Hello' => ['world', 'universe']], $parameter->all());
        static::assertEquals(['Foo', 'Hello'], $parameter->keys());
        static::assertTrue($parameter->has('foo'));
        static::assertFalse($parameter->has('none'));
    }

    public function testReset()
    {
        $parameter = new Header(['foo' => 'bar']);
        $parameter->set('hello', 'world');

        static::assertEquals(2, $parameter->count());
        static::assertEquals(['Foo' => ['bar'], 'Hello' => ['world']], $parameter->all());
        static::assertEquals(['Foo', 'Hello'], $parameter->keys());

        static::assertSame($parameter, $parameter->reset([
            'bar' => 'biz',
            'hello' => 'biz',
            'ni' => 'hao',
        ]));

        static::assertEquals(3, $parameter->count());
        static::assertEquals(['Bar' => ['biz'], 'Hello' => ['biz'], 'Ni' => ['hao']], $parameter->all());
        static::assertEquals(['Bar', 'Hello', 'Ni'], $parameter->keys());
    }

    public function testSetAndGet()
    {
        $parameter = new Header();

        static::assertSame($parameter, $parameter->set('foo', 'bar'));
        static::assertTrue($parameter->has('foo'));
        static::assertEquals(['bar'], $parameter->get('foo'));
        static::assertEquals(['bar'], $parameter->get('Foo'));
        static::assertEquals('bar', $parameter->getLine('foo'));
        static::assertEquals('bar', $parameter->getLine('fOo'));
        static::assertEquals('bar', $parameter->getFirst('foo'));
        static::assertEquals('bar', $parameter->getFirst('fOO'));
        static::assertEquals('bar', $parameter->getLast('foo'));
        static::assertEquals('bar', $parameter->getLast('FOO'));

        static::assertEquals(1, $parameter->count());
        static::assertEquals(['Foo' => ['bar']], $parameter->all());
        static::assertEquals(['Foo'], $parameter->keys());

        static::assertSame($parameter, $parameter->set('foo', ['bar', 'biz']));
        static::assertTrue($parameter->has('foo'));
        static::assertEquals(['bar', 'biz'], $parameter->get('foo'));
        static::assertEquals('bar,biz', $parameter->getLine('foo'));
        static::assertEquals('bar', $parameter->getFirst('foo'));
        static::assertEquals('biz', $parameter->getLast('foo'));

        static::assertEquals(1, $parameter->count());
        static::assertEquals(['Foo' => ['bar','biz']], $parameter->all());
        static::assertEquals(['Foo'], $parameter->keys());

        static::assertFalse($parameter->has('bar'));
        static::assertSame($parameter, $parameter->set([
            'bar' => 'biz',
            'hello' => ['world', 'sun', 'universe'],
        ]));

        static::assertTrue($parameter->has('bar'));
        static::assertEquals(['biz'], $parameter->get('bar'));
        static::assertEquals('biz', $parameter->getLine('bar'));
        static::assertEquals('biz', $parameter->getFirst('bar'));
        static::assertEquals('biz', $parameter->getLast('bar'));

        static::assertTrue($parameter->has('hello'));
        static::assertEquals(['world', 'sun', 'universe'], $parameter->get('hello'));
        static::assertEquals('world,sun,universe', $parameter->getLine('hello'));
        static::assertEquals('world', $parameter->getFirst('hello'));
        static::assertEquals('universe', $parameter->getLast('hello'));

        static::assertEquals(3, $parameter->count());
        static::assertEquals(
            ['Foo' => ['bar', 'biz'], 'Bar' => ['biz'], 'Hello' => ['world', 'sun', 'universe']],
            $parameter->all()
        );
        static::assertEquals(['Foo', 'Bar', 'Hello'], $parameter->keys());

        static::assertSame($parameter, $parameter->setIf('foo', 'biz'));
        static::assertEquals(['bar', 'biz'], $parameter->get('foo'));

        static::assertSame($parameter, $parameter->setIf('ni', 'hao'));
        static::assertEquals(['hao'], $parameter->get('ni'));

        static::assertSame($parameter, $parameter->setIf([
            'foo' => 'a',
            'ni' => 'b',
            'c' => 'c',
        ]));

        static::assertEquals(5, $parameter->count());
        static::assertEquals(
            ['Foo' => ['bar', 'biz'], 'Bar' => ['biz'], 'Hello' => ['world', 'sun', 'universe'], 'Ni' => ['hao'], 'C' => ['c']],
            $parameter->all()
        );
        static::assertEquals(['Foo', 'Bar', 'Hello', 'Ni', 'C'], $parameter->keys());

        static::assertEquals(['bar', 'biz'], $parameter->get('foo'));
        static::assertEquals(['hao'], $parameter->get('ni'));
        static::assertEquals(['c'], $parameter->get('c'));

        static::assertNull($parameter->get('none'));
        static::assertEquals([], $parameter->get('none', []));
        static::assertEquals(['NONE'], $parameter->get('none', ['NONE']));
        static::assertNull($parameter->getLine('none'));
        static::assertEquals('NONE', $parameter->getLine('none', 'NONE'));
        static::assertNull($parameter->getFirst('none'));
        static::assertEquals('NONE', $parameter->getFirst('none', 'NONE'));
        static::assertNull($parameter->getLast('none'));
        static::assertEquals('NONE', $parameter->getLast('none', 'NONE'));

        static::assertNull($parameter->get(['none', 'dd']));
        static::assertNull($parameter->getLine(['none', 'dd']));

        static::assertEquals(['hao'], $parameter->get(['none', 'ni']));
        static::assertEquals('hao', $parameter->getFirst(['none', 'ni']));

        static::assertEquals(['bar', 'biz'], $parameter->get(['foo', 'ni']));
        static::assertEquals('biz', $parameter->getLast(['foo', 'ni']));
    }

    public function testAdd()
    {
        $parameter = new Header(['foo' => 'bar']);
        static::assertEquals(['bar'], $parameter->get('foo'));
        static::assertSame($parameter, $parameter->add('foo', 'biz'));
        static::assertEquals(['bar','biz'], $parameter->get('foo'));
        static::assertSame($parameter, $parameter->add('foo', ['a','b']));
        static::assertEquals(['bar','biz', 'a', 'b'], $parameter->get('foo'));
    }

    public function testRemove()
    {
        $parameter = new Header();
        $parameter->set('foo', 'bar');
        static::assertTrue($parameter->has('foo'));
        static::assertEquals(['bar'], $parameter->get('foo'));

        static::assertEquals(1, $parameter->count());
        static::assertEquals(['Foo' => ['bar']], $parameter->all());
        static::assertEquals(['Foo'], $parameter->keys());

        static::assertSame($parameter,  $parameter->remove('foo'));
        static::assertNull($parameter->get('foo'));
        static::assertFalse($parameter->has('foo'));

        static::assertCount(0, $parameter);
        static::assertEquals([], $parameter->keys());
        static::assertEquals([], $parameter->all());

        $parameter->set([
            'foo' => 'bar',
            'hello' => 'world',
            'ni' => 'hao',
        ]);
        static::assertEquals(['bar'], $parameter->get('foo'));
        static::assertEquals(['world'], $parameter->get('hello'));
        static::assertEquals(['hao'], $parameter->get('ni'));

        static::assertEquals(3, $parameter->count());
        static::assertEquals(['Foo' => ['bar'], 'Hello' => ['world'], 'Ni' => ['hao']], $parameter->all());
        static::assertEquals(['Foo', 'Hello', 'Ni'], $parameter->keys());

        static::assertSame($parameter, $parameter->remove(['foo', 'hello']));
        static::assertNull($parameter->get('foo'));
        static::assertNull($parameter->get('hello'));
        static::assertEquals(['hao'], $parameter->get('ni'));

        static::assertEquals(1, $parameter->count());
        static::assertEquals(['Ni' => ['hao']], $parameter->all());
        static::assertEquals(['Ni'], $parameter->keys());
    }

    public function testClear()
    {
        $parameter = new Header();
        $parameter->set('foo', 'bar');
        static::assertEquals(['bar'], $parameter->get('foo'));

        static::assertEquals(1, $parameter->count());
        static::assertEquals(['Foo' => ['bar']], $parameter->all());
        static::assertEquals(['Foo'], $parameter->keys());

        static::assertSame($parameter, $parameter->clear());
        static::assertEquals(0, $parameter->count());
        static::assertEquals([], $parameter->all());
        static::assertEquals([], $parameter->keys());

        $parameter->set([
            'foo' => 'bar',
            'hello' => 'world',
            'ni' => 'hao',
        ]);
        static::assertEquals(['Foo' => ['bar'], 'Hello' => ['world'], 'Ni' => ['hao']], $parameter->all());

        static::assertSame($parameter, $parameter->clear());
        static::assertEquals(0, $parameter->count());
        static::assertEquals([], $parameter->all());
        static::assertEquals([], $parameter->keys());
    }


    public function testSetGetData()
    {
        $parameter = new Header();
        // Header setData getData Method
        $timeStr = '2038-01-18 12:00:00';
        $timeStamp = 2147428800;
        $parameter->setDate('foo', '@'.$timeStamp);
        static::assertInstanceOf(DateTimeInterface::class, $parameter->getDate('foo'));
        static::assertEquals($timeStr, $parameter->getDate('foo')->format('Y-m-d H:i:s'));

        $parameter->setDate('foo', $timeStamp);
        static::assertInstanceOf(DateTimeInterface::class, $parameter->getDate('foo'));
        static::assertEquals($timeStr, $parameter->getDate('foo')->format('Y-m-d H:i:s'));

        $parameter->setDate('foo', $timeStr);
        static::assertInstanceOf(DateTimeInterface::class, $parameter->getDate('foo'));
        static::assertEquals($timeStamp, $parameter->getDate('foo')->getTimestamp());

        $parameter->setDate('foo', new DateTime($timeStr));
        static::assertInstanceOf(DateTimeInterface::class, $parameter->getDate('foo'));
        static::assertEquals($timeStamp, $parameter->getDate('foo')->getTimestamp());

        $defDate = $parameter->getDate('bar', $timeStamp);
        static::assertInstanceOf(DateTimeInterface::class, $defDate);
        static::assertEquals($timeStamp, $defDate->getTimestamp());

        $defDate = $parameter->getDate('bar', $timeStr);
        static::assertInstanceOf(DateTimeInterface::class, $defDate);
        static::assertEquals($timeStamp, $defDate->getTimestamp());
    }

    public function testCacheControlBasic()
    {
        $parameter = new Header(['Cache-Control' => 'no-cache,max-age=600,max-stale=500,only-if-cached']);
        static::assertEquals(4, $parameter->countCacheControl());
        static::assertEquals([
            'no-cache' => 1,
            'max-age' => 600,
            'max-stale' => 500,
            'only-if-cached' => 1,
        ], $parameter->allCacheControl());

        static::assertTrue($parameter->hasCacheControl('no-cache'));
        static::assertTrue($parameter->getCacheControl('no-cache'));
        static::assertTrue($parameter->hasCacheControl('MAX_AGE'));
        static::assertEquals(600, $parameter->getCacheControl('MAX_AGE'));
        static::assertTrue($parameter->hasCacheControl('Max-Stale'));
        static::assertEquals(500, $parameter->getCacheControl('Max-Stale'));

        static::assertFalse($parameter->hasCacheControl('no-store'));
        static::assertNull($parameter->getCacheControl('no-store'));
        static::assertEquals('test', $parameter->getCacheControl('no-store','test'));
        static::assertEquals(500, $parameter->getCacheControl(['no-store', 'max-stale']));
        static::assertEquals(600, $parameter->getCacheControl(['max-age', 'max-stale']));

        $parameter->set('cache-control', 'no-cache');
        static::assertTrue($parameter->hasCacheControl('no-cache'));
        static::assertTrue($parameter->getCacheControl('no-cache'));
        static::assertFalse($parameter->hasCacheControl('MAX_AGE'));
        static::assertFalse($parameter->hasCacheControl('Max-Stale'));
        static::assertFalse($parameter->hasCacheControl('no-store'));
    }

    public function testCacheControlSetAndGet()
    {
        $parameter = new Header();

        static::assertNull($parameter->get('cache-control'));
        static::assertSame($parameter, $parameter->setCacheControl('no-cache'));
        static::assertTrue($parameter->hasCacheControl('no-cache'));
        static::assertTrue($parameter->getCacheControl('no-cache'));
        static::assertEquals(['no-cache'], $parameter->get('cache-control'));

        static::assertSame($parameter, $parameter->removeCacheControl('no-cache'));
        static::assertFalse($parameter->hasCacheControl('no-cache'));
        static::assertNull($parameter->getCacheControl('no-cache'));
        static::assertNull($parameter->get('cache-control'));

        $parameter->setCacheControl('no-store');
        static::assertEquals(['no-store'], $parameter->get('cache-control'));

        $parameter->setCacheControl('Max_age', 500);
        static::assertTrue($parameter->hasCacheControl('max-age'));
        static::assertEquals(500, $parameter->getCacheControl('Max-Age'));
        static::assertEquals(['max-age=500,no-store'], $parameter->get('cache-control'));

        $parameter->setCacheControl([
            'max-age' => 600,
            'max-stale' => 500,
        ]);
        static::assertEquals(600, $parameter->getCacheControl('Max-Age'));
        static::assertEquals(3, $parameter->countCacheControl());
        static::assertEquals([
            'no-store' => 1,
            'max-age' => 600,
            'max-stale' => 500,
        ], $parameter->allCacheControl());
        static::assertEquals(['max-age=600,max-stale=500,no-store'], $parameter->get('cache-control'));
        static::assertSame($parameter, $parameter->clearCacheControl());
        static::assertEquals(0, $parameter->countCacheControl());
        static::assertEquals([], $parameter->allCacheControl());
        static::assertNull($parameter->get('cache-control'));
    }
}
