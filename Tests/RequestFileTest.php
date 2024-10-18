<?php

use Tanbolt\Http\Request\File;
use PHPUnit\Framework\TestCase;
use Tanbolt\Http\Request\UploadedFile;

class RequestFileTest extends TestCase
{
    public function testBasic()
    {
        $file = new File();
        static::assertEquals(0, $file->count());
        static::assertEquals([], $file->all());
        static::assertEquals([], $file->keys());
        static::assertFalse($file->has('foo'));

        $arr = [];
        foreach (['foo', 'hello', 'ni'] as $key) {
            $arr[$key] = self::makeFileArray($key);
        }

        $multi = [];
        $ma = self::makeFileArray('ma');
        $mb = self::makeFileArray('mb');
        foreach ($ma as $k=>$v) {
            $multi[$k] = [
                'ma' => $v,
                'mb' => $mb[$k],
            ];
        }
        $arr['multi'] = $multi;

        $file = new File($arr);
        static::assertEquals(4, $file->count());
        static::assertEquals(['foo', 'hello', 'ni', 'multi'], $file->keys());
        $allFiles = $file->all();
        foreach ($allFiles as $key => $upload) {
            if ('multi' === $key) {
                static::assertTrue(is_array($upload));
                static::assertEquals(['ma', 'mb'], array_keys($upload));
                static::assertInstanceOf(UploadedFile::class, $upload['ma']);
                static::assertEquals($ma, $upload['ma']->file());
                static::assertInstanceOf(UploadedFile::class, $upload['mb']);
                static::assertEquals($mb, $upload['mb']->file());
            } else {
                static::assertInstanceOf(UploadedFile::class, $upload);
                static::assertEquals($arr[$key], $upload->file());
            }
        }

        static::assertTrue($file->has('foo'));
        static::assertFalse($file->has('none'));
        static::assertFalse($file->has(['none', 'foo']));
        static::assertTrue($file->has(['none', 'foo'], true));
        static::assertEquals($arr['foo'], $file->get(['none', 'foo'])->file());

        static::assertSame($file, $file->set('ni', $ok = self::makeFileArray('ok')));
        static::assertEquals($ok, $file->get('ni')->file());
        static::assertSame($file, $file->setIf('ni', self::makeFileArray('ok')));
        static::assertEquals($ok, $file->get('ni')->file());

        static::assertSame($file, $file->remove('foo'));
        static::assertEquals(['hello', 'ni', 'multi'], $file->keys());
        static::assertSame($file, $file->clear());
        static::assertCount(0, $file->all());
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

    public function testFileSetException()
    {
        $file = new File();
        try {
            $file->set('fileNone', 'none');
            static::fail('It should throw exception when set a error file');
        } catch (Exception $e) {
            static::assertTrue(true);
        }
    }

    public function testUploadFile()
    {
        if (!ini_get('file_uploads')) {
            static::markTestSkipped('file_uploads is disabled in php.ini, Skip testFileMethod');
        }

        $image =  [
            'name' => '/Fixtures/image.jpg',
            'tmp_name' => realpath(__DIR__.'/Fixtures/File/image.jpg'),
            'size' => filesize(__DIR__.'/Fixtures/File/image.jpg'),
            'type' => 'error',
            'error' => 0,
        ];

        $file = new UploadedFile($image);
        $file->test(true);
        static::assertEquals($image, $file->file());
        static::assertEquals($image['tmp_name'], $file->tmpName());
        static::assertEquals('image.jpg', $file->clientName());
        static::assertEquals($image['size'], $file->clientSize());
        static::assertEquals($image['type'], $file->clientType());
        static::assertEquals('jpg', $file->clientExtension());
        static::assertEquals('image/jpeg', $file->mimeType());
        static::assertEquals('jpg', $file->guessExtension());
        static::assertTrue($file->isType('jpg,jpeg,gif'));
        static::assertTrue($file->isType(['jpg','gif']));
        static::assertTrue($file->isImage());
        static::assertFalse($file->isDoc());
        static::assertFalse($file->isMedia());
        static::assertFalse($file->isFlash());
        static::assertFalse($file->isCompressedFile());

        $path = $file->saveToLocal(__DIR__.'/Fixtures/temp');
        static::assertTrue(@is_writable($path));
        static::assertTrue(@unlink($path));

        $path = $file->saveToLocal(__DIR__.'/Fixtures/temp', true);
        static::assertEquals(realpath(__DIR__.'/Fixtures/temp/image.jpg'), realpath($path));
        static::assertTrue(@is_writable($path));
        static::assertTrue(@unlink($path));

        $path = $file->saveToLocal(__DIR__.'/Fixtures/temp', '666');
        static::assertEquals(realpath(__DIR__.'/Fixtures/temp/666.jpg'), realpath($path));
        static::assertTrue(@is_writable($path));
        static::assertTrue(@unlink($path));

        $path = $file->saveToLocal(__DIR__.'/Fixtures/temp', '666', 'png');
        static::assertEquals(realpath(__DIR__.'/Fixtures/temp/666.png'), realpath($path));
        static::assertTrue(@is_writable($path));
        static::assertTrue(@unlink($path));

        static::assertTrue(@rmdir(__DIR__.'/Fixtures/temp'));

        $image_txt = [
            'name' => 'E:\\Fixtures\\image_txt.jpg',
            'tmp_name' => realpath(__DIR__.'/Fixtures/File/image_txt.jpg'),
            'size' => filesize(__DIR__.'/Fixtures/File/image_txt.jpg'),
            'type' => 'unknown',
            'error' => null,
        ];
        $file = new UploadedFile($image_txt);
        $file->test(true);
        static::assertEquals($image_txt, $file->file());
        static::assertEquals($image_txt['tmp_name'], $file->tmpName());
        static::assertEquals('image_txt.jpg', $file->clientName());
        static::assertEquals($image_txt['size'], $file->clientSize());
        static::assertEquals($image_txt['type'], $file->clientType());
        static::assertEquals('text/plain', $file->mimeType());
        static::assertEquals('txt', $file->guessExtension());
        static::assertFalse($file->isImage());
        static::assertTrue($file->isImage(false));
        static::assertFalse($file->isDoc());
        static::assertFalse($file->isDoc(false));
    }

}
