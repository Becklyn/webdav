<?php

namespace Becklyn\WebDav\Tests\Resource;

use Becklyn\WebDav\Resource\File;
use Becklyn\WebDav\Resource\Folder;
use Becklyn\WebDav\Resource\Resource;
use PHPUnit\Framework\TestCase;

/**
 * @author Marko Vujnovic <mv@becklyn.com>
 * @since  2021-08-13
 *
 * @covers \Becklyn\WebDav\Resource\Resource
 * @covers \Becklyn\WebDav\Resource\File
 * @covers \Becklyn\WebDav\Resource\Folder
 */
class ResourceTest extends TestCase
{
    public function testCreateReturnsFolderIfGetContentLengthDavPropertyDoesNotExist(): void
    {
        $path = uniqid();
        $lastModified = 'Sun, 08 Aug 2021 23:04:04 GMT';

        $data = [
            '{DAV:}getlastmodified' => $lastModified,
            '{DAV:}getcontenttype' => 'foobar',
        ];

        $this->assertArrayNotHasKey('{DAV:}getcontentlength', $data);

        $folder = Resource::create($path, $data);
        $this->assertInstanceOf(Folder::class, $folder);
        $this->assertEquals($path, $folder->path());
        $this->assertEquals(new \DateTimeImmutable($lastModified), $folder->lastModified());
    }

    public function testCreateReturnsFolderIfGetContentTypeDavPropertyContainsSubstringDirectory(): void
    {
        $path = uniqid();
        $lastModified = 'Sun, 08 Aug 2021 23:04:04 GMT';

        $data = [
            '{DAV:}getlastmodified' => $lastModified,
            '{DAV:}getcontentlength' => 1234,
            '{DAV:}getcontenttype' => 'somedirectorything',
        ];

        $this->assertStringContainsString('directory', $data['{DAV:}getcontenttype']);

        $folder = Resource::create($path, $data);
        $this->assertInstanceOf(Folder::class, $folder);
        $this->assertEquals($path, $folder->path());
        $this->assertEquals(new \DateTimeImmutable($lastModified), $folder->lastModified());
    }

    public function testCreateReturnsFileIfBothGetContentLengthDavPropertyExistsAndGetContentTypeDavPropertyDoesNotContainSubstringDirectory(): void
    {
        $path = uniqid();
        $lastModified = 'Sun, 08 Aug 2021 23:04:04 GMT';
        $contentType = uniqid();
        $contentLength = random_int(1, 1000);

        $data = [
            '{DAV:}getlastmodified' => $lastModified,
            '{DAV:}getcontentlength' => $contentLength,
            '{DAV:}getcontenttype' => $contentType,
        ];

        $this->assertArrayHasKey('{DAV:}getcontentlength', $data);
        $this->assertStringNotContainsString('directory', $data['{DAV:}getcontenttype']);

        $file = Resource::create($path, $data);
        $this->assertInstanceOf(File::class, $file);
        $this->assertEquals($path, $file->path());
        $this->assertEquals(new \DateTimeImmutable($lastModified), $file->lastModified());
        $this->assertEquals($contentLength, $file->contentLength());
        $this->assertEquals($contentType, $file->contentType());
    }

    public function testCreateReturnsFileWithContentTypeNullIfContentLengthDavPropertyExistsAndGetContentTypeDavPropertyDoesNotExist(): void
    {
        $path = uniqid();
        $lastModified = 'Sun, 08 Aug 2021 23:04:04 GMT';
        $contentLength = random_int(1, 1000);

        $data = [
            '{DAV:}getlastmodified' => $lastModified,
            '{DAV:}getcontentlength' => $contentLength,
        ];

        $this->assertArrayHasKey('{DAV:}getcontentlength', $data);
        $this->assertArrayNotHasKey('{DAV:}getcontenttype', $data);

        $file = Resource::create($path, $data);
        $this->assertInstanceOf(File::class, $file);
        $this->assertEquals($path, $file->path());
        $this->assertEquals(new \DateTimeImmutable($lastModified), $file->lastModified());
        $this->assertEquals($contentLength, $file->contentLength());
        $this->assertNull($file->contentType());
    }
}
