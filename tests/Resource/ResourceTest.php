<?php declare(strict_types=1);

namespace Becklyn\WebDav\Tests\Resource;

use Becklyn\WebDav\Resource\File;
use Becklyn\WebDav\Resource\Folder;
use Becklyn\WebDav\Resource\Resource;
use PHPUnit\Framework\TestCase;

/**
 * @author Marko Vujnovic <mv@becklyn.com>
 *
 * @since  2021-08-13
 *
 * @covers \Becklyn\WebDav\Resource\File
 * @covers \Becklyn\WebDav\Resource\Folder
 * @covers \Becklyn\WebDav\Resource\Resource
 *
 * @internal
 */
final class ResourceTest extends TestCase
{
    public function testCreateReturnsFolderIfGetContentLengthDavPropertyDoesNotExist() : void
    {
        $path = \uniqid();
        $lastModified = 'Sun, 08 Aug 2021 23:04:04 GMT';

        $data = [
            '{DAV:}getlastmodified' => $lastModified,
            '{DAV:}getcontenttype' => 'foobar',
        ];

        self::assertArrayNotHasKey('{DAV:}getcontentlength', $data);

        $folder = Resource::create($path, $data);
        self::assertInstanceOf(Folder::class, $folder);
        self::assertEquals($path, $folder->path());
        self::assertEquals(new \DateTimeImmutable($lastModified), $folder->lastModified());
    }

    public function testCreateReturnsFolderIfGetContentTypeDavPropertyContainsSubstringDirectory() : void
    {
        $path = \uniqid();
        $lastModified = 'Sun, 08 Aug 2021 23:04:04 GMT';

        $data = [
            '{DAV:}getlastmodified' => $lastModified,
            '{DAV:}getcontentlength' => 1234,
            '{DAV:}getcontenttype' => 'somedirectorything',
        ];

        self::assertStringContainsString('directory', $data['{DAV:}getcontenttype']);

        $folder = Resource::create($path, $data);
        self::assertInstanceOf(Folder::class, $folder);
        self::assertEquals($path, $folder->path());
        self::assertEquals(new \DateTimeImmutable($lastModified), $folder->lastModified());
    }

    public function testCreateReturnsFileIfBothGetContentLengthDavPropertyExistsAndGetContentTypeDavPropertyDoesNotContainSubstringDirectory() : void
    {
        $path = \uniqid();
        $lastModified = 'Sun, 08 Aug 2021 23:04:04 GMT';
        $contentType = \uniqid();
        $contentLength = \random_int(1, 1000);

        $data = [
            '{DAV:}getlastmodified' => $lastModified,
            '{DAV:}getcontentlength' => $contentLength,
            '{DAV:}getcontenttype' => $contentType,
        ];

        self::assertArrayHasKey('{DAV:}getcontentlength', $data);
        self::assertStringNotContainsString('directory', $data['{DAV:}getcontenttype']);

        $file = Resource::create($path, $data);
        self::assertInstanceOf(File::class, $file);
        self::assertEquals($path, $file->path());
        self::assertEquals(new \DateTimeImmutable($lastModified), $file->lastModified());
        self::assertEquals($contentLength, $file->contentLength());
        self::assertEquals($contentType, $file->contentType());
    }

    public function testCreateReturnsFileWithContentTypeNullIfContentLengthDavPropertyExistsAndGetContentTypeDavPropertyDoesNotExist() : void
    {
        $path = \uniqid();
        $lastModified = 'Sun, 08 Aug 2021 23:04:04 GMT';
        $contentLength = \random_int(1, 1000);

        $data = [
            '{DAV:}getlastmodified' => $lastModified,
            '{DAV:}getcontentlength' => $contentLength,
        ];

        self::assertArrayHasKey('{DAV:}getcontentlength', $data);
        self::assertArrayNotHasKey('{DAV:}getcontenttype', $data);

        $file = Resource::create($path, $data);
        self::assertInstanceOf(File::class, $file);
        self::assertEquals($path, $file->path());
        self::assertEquals(new \DateTimeImmutable($lastModified), $file->lastModified());
        self::assertEquals($contentLength, $file->contentLength());
        self::assertNull($file->contentType());
    }
}
