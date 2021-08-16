<?php declare(strict_types=1);

namespace Becklyn\WebDav\Resource;

/**
 * @author Marko Vujnovic <mv@becklyn.com>
 *
 * @since  2021-08-13
 */
class File extends Resource
{
    protected function __construct(string $path, \DateTimeImmutable $lastModified, protected ?string $contentType, protected int $contentLength)
    {
        parent::__construct($path, $lastModified);
    }

    public function contentType() : ?string
    {
        return $this->contentType;
    }

    public function contentLength() : int
    {
        return $this->contentLength;
    }
}
