<?php declare(strict_types=1);

namespace Becklyn\WebDav\Resource;

/**
 * @author Marko Vujnovic <mv@becklyn.com>
 *
 * @since  2021-08-13
 */
class Resource
{
    protected function __construct(
        protected string $path,
        protected \DateTimeImmutable $lastModified,
    ) {}

    public function path() : string
    {
        return $this->path;
    }

    public function lastModified() : \DateTimeImmutable
    {
        return $this->lastModified;
    }

    public function name() : string
    {
        $explosion = \explode('/', $this->path);
        return end($explosion);
    }

    public static function create (string $path, array $data) : Folder|File
    {
        if (!\array_key_exists('{DAV:}getcontentlength', $data) ||
            (\array_key_exists('{DAV:}getcontenttype', $data) && \str_contains($data['{DAV:}getcontenttype'], 'directory'))
        ) {
            return new Folder($path, new \DateTimeImmutable($data['{DAV:}getlastmodified']));
        }

        return new File(
            $path,
            new \DateTimeImmutable($data['{DAV:}getlastmodified']),
            $data['{DAV:}getcontenttype'] ?? null,
            (int) $data['{DAV:}getcontentlength']
        );
    }
}
