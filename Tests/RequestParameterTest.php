<?php

use PHPUnit\Framework\TestCase;
use Tanbolt\Http\Request\Parameter;

class RequestParameterTest extends TestCase
{
    public function testConstruct()
    {
        $parameter = new Parameter();
        static::assertEquals(0, $parameter->count());
        static::assertEquals([], $parameter->keys());
        static::assertEquals([], $parameter->all());
        static::assertFalse($parameter->has('foo'));

        $arr = ['foo' => 'bar', 'hello' => 'world'];
        $parameter = new Parameter($arr);
        static::assertEquals(2, $parameter->count());
        static::assertEquals($arr, $parameter->all());
        static::assertEquals(['foo', 'hello'], $parameter->keys());
        static::assertTrue($parameter->has('foo'));
        static::assertFalse($parameter->has('none'));
    }

    public function testReset()
    {
        $parameter = new Parameter(['foo' => 'bar']);
        $parameter->set('hello', 'world');

        static::assertEquals(2, $parameter->count());
        static::assertEquals(['foo' => 'bar', 'hello' => 'world'], $parameter->all());
        static::assertEquals(['foo', 'hello'], $parameter->keys());

        static::assertSame($parameter, $parameter->reset([
            'bar' => 'biz',
            'hello' => 'biz',
            'ni' => 'hao',
        ]));

        static::assertEquals(3, $parameter->count());
        static::assertEquals(['bar' => 'biz', 'hello' => 'biz', 'ni' => 'hao'], $parameter->all());
        static::assertEquals(['bar', 'hello', 'ni'], $parameter->keys());
    }

    public function testSetAndGet()
    {
        $parameter = new Parameter();

        static::assertSame($parameter, $parameter->set('foo', 'biz'));
        static::assertTrue($parameter->has('foo'));
        static::assertEquals('biz', $parameter->get('foo'));

        static::assertEquals(1, $parameter->count());
        static::assertEquals(['foo' => 'biz'], $parameter->all());
        static::assertEquals(['foo'], $parameter->keys());

        static::assertSame($parameter, $parameter->set('foo', 'bar'));
        static::assertTrue($parameter->has('foo'));
        static::assertEquals('bar', $parameter->get('foo'));

        static::assertEquals(1, $parameter->count());
        static::assertEquals(['foo' => 'bar'], $parameter->all());
        static::assertEquals(['foo'], $parameter->keys());

        static::assertFalse($parameter->has('bar'));
        static::assertSame($parameter, $parameter->set([
            'bar' => 'biz',
            'hello' => 'world',
        ]));
        static::assertTrue($parameter->has('bar'));
        static::assertEquals('biz', $parameter->get('bar'));
        static::assertTrue($parameter->has('hello'));
        static::assertEquals('world', $parameter->get('hello'));

        static::assertTrue($parameter->has(['foo', 'bar']));
        static::assertFalse($parameter->has(['foo', 'none']));
        static::assertTrue($parameter->has(['foo', 'none'], true));

        static::assertEquals(3, $parameter->count());
        static::assertEquals(['foo' => 'bar', 'bar' => 'biz', 'hello' => 'world'], $parameter->all());
        static::assertEquals(['foo', 'bar', 'hello'], $parameter->keys());

        static::assertSame($parameter, $parameter->setIf('foo', 'biz'));
        static::assertEquals('bar', $parameter->get('foo'));

        static::assertSame($parameter, $parameter->setIf('ni', 'hao'));
        static::assertEquals('hao', $parameter->get('ni'));

        static::assertSame($parameter, $parameter->setIf([
            'foo' => 'a',
            'ni' => 'b',
            'c' => 'c',
        ]));

        static::assertEquals(5, $parameter->count());
        static::assertEquals(['foo' => 'bar', 'bar' => 'biz', 'hello' => 'world', 'ni' => 'hao', 'c' => 'c'], $parameter->all());
        static::assertEquals(['foo', 'bar', 'hello', 'ni', 'c'], $parameter->keys());

        static::assertEquals('bar', $parameter->get('foo'));
        static::assertEquals('hao', $parameter->get('ni'));
        static::assertEquals('c', $parameter->get('c'));

        static::assertNull($parameter->get('none'));
        static::assertEquals('NONE', $parameter->get('none', 'NONE'));

        static::assertNull($parameter->get(['none', 'dd']));
        static::assertEquals('hao', $parameter->get(['none', 'ni']));
        static::assertEquals('bar', $parameter->get(['foo', 'ni']));
    }

    public function testRemove()
    {
        $parameter = new Parameter();
        $parameter->set('foo', 'bar');
        static::assertTrue(isset($parameter['foo']));
        static::assertEquals('bar', $parameter['foo']);

        static::assertCount(1, $parameter);
        static::assertEquals(1, $parameter->count());
        static::assertEquals(['foo' => 'bar'], $parameter->all());
        static::assertEquals(['foo'], $parameter->keys());

        static::assertSame($parameter,  $parameter->remove('foo'));
        static::assertFalse($parameter->has('foo'));
        static::assertNull($parameter->get('foo'));

        static::assertEquals(0, $parameter->count());
        static::assertEquals([], $parameter->all());
        static::assertEquals([], $parameter->keys());

        $parameter->set([
            'foo' => 'bar',
            'hello' => 'world',
            'ni' => 'hao',
        ]);
        static::assertEquals('bar', $parameter['foo']);
        static::assertEquals('world', $parameter['hello']);
        static::assertEquals('hao', $parameter['ni']);

        static::assertEquals(3, $parameter->count());
        static::assertEquals(['foo' => 'bar', 'hello' => 'world', 'ni' => 'hao'], $parameter->all());
        static::assertEquals(['foo', 'hello', 'ni'], $parameter->keys());

        static::assertSame($parameter, $parameter->remove(['foo', 'hello']));
        static::assertNull($parameter->get('foo'));
        static::assertNull($parameter->get('hello'));
        static::assertEquals('hao', $parameter->get('ni'));

        static::assertEquals(1, $parameter->count());
        static::assertEquals(['ni' => 'hao'], $parameter->all());
        static::assertEquals(['ni'], $parameter->keys());
    }

    public function testClear()
    {
        $parameter = new Parameter();
        $parameter->set('foo', 'bar');
        static::assertEquals('bar', $parameter->get('foo'));

        static::assertEquals(1, $parameter->count());
        static::assertEquals(['foo' => 'bar'], $parameter->all());
        static::assertEquals(['foo'], $parameter->keys());

        static::assertSame($parameter, $parameter->clear());
        static::assertEquals(0, $parameter->count());
        static::assertEquals([], $parameter->all());
        static::assertEquals([], $parameter->keys());

        $parameter->set([
            'foo' => 'bar',
            'hello' => 'world',
            'ni' => 'hao',
        ]);
        static::assertEquals('bar', $parameter->get('foo'));
        static::assertEquals('world', $parameter->get('hello'));
        static::assertEquals('hao', $parameter->get('ni'));

        static::assertEquals(3, $parameter->count());
        static::assertEquals(['foo' => 'bar', 'hello' => 'world', 'ni' => 'hao'], $parameter->all());
        static::assertEquals(['foo', 'hello', 'ni'], $parameter->keys());

        static::assertSame($parameter, $parameter->clear());
        static::assertEquals(0, $parameter->count());
        static::assertEquals([], $parameter->all());
        static::assertEquals([], $parameter->keys());
    }
}
