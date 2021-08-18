<?php declare(strict_types=1);

namespace Becklyn\WebDav;

use Becklyn\WebDav\Resource\File;
use Becklyn\WebDav\Resource\Resource;
use Sabre\DAV\Client as SabreClient;
use Sabre\HTTP\ClientHttpException;

/**
 * @author Marko Vujnovic <mv@becklyn.com>
 *
 * @since  2021-08-13
 */
class Client
{
    private SabreClient $client;

    public function __construct(Config $config)
    {
        $this->client = new SabreClient([
            'baseUri' => $config->baseUri(),
            'userName' => $config->username(),
            'password' => $config->password(),
        ]);
    }

    /**
     * @throws HttpException
     * @throws ResourceNotAFolderException
     * @throws ResourceNotFoundException
     *
     * @return array<int, Resource>
     */
    public function listFolderContents(string $path) : array
    {
        try {
            $contents = $this->client->propfind($path, [
                '{DAV:}getcontentlength',
                '{DAV:}getcontenttype',
                '{DAV:}getlastmodified',
            ], 1);
        } catch (ClientHttpException $e) {
            if (404 === $e->getHttpStatus()) {
                throw new ResourceNotFoundException("The resource {$path} was not found", 404, $e);
            }
            throw new HttpException($e->getMessage(), (int) $e->getCode(), $e);
        }

        $folderProcessed = false;
        $resources = [];

        foreach ($contents as $resourcePath => $data) {
            $resource = Resource::create($resourcePath, $data);

            if (!$folderProcessed) {
                $folderProcessed = true;

                if ($resource instanceof File) {
                    throw new ResourceNotAFolderException("The resource '{$resourcePath}' is not a folder");
                }

                continue;
            }

            $resources[] = $resource;
        }

        return $resources;
    }

    /**
     * @throws HttpException
     * @throws ResourceNotFoundException
     */
    public function getFileContents(string|File $file) : string
    {
        $path = $file instanceof File ? $file->path() : $file;
        $response = $this->client->request('GET', $path);

        if (200 !== $response['statusCode']) {
            if (404 === $response['statusCode']) {
                throw new ResourceNotFoundException("The resource {$path} was not found", 404);
            }

            throw new HttpException("HTTP code {$response['statusCode']} received", (int) $response['statusCode']);
        }

        return $response['body'];
    }
}
