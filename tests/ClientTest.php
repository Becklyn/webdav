<?php declare(strict_types=1);

namespace Becklyn\WebDav\Tests;

use Becklyn\WebDav\Client;
use Becklyn\WebDav\Config;
use Becklyn\WebDav\HttpException;
use Becklyn\WebDav\Resource\File;
use Becklyn\WebDav\Resource\Folder;
use Becklyn\WebDav\ResourceNotAFolderException;
use Becklyn\WebDav\ResourceNotFoundException;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sabre\DAV\Client as SabreClient;
use Sabre\HTTP\ClientHttpException;
use Sabre\HTTP\Response;

/**
 * @author Marko Vujnovic <mv@becklyn.com>
 *
 * @since  2021-08-13
 *
 * @covers \Becklyn\WebDav\Client
 * @covers \Becklyn\WebDav\Config
 *
 * @internal
 */
final class ClientTest extends TestCase
{
    use ProphecyTrait;

    private ObjectProphecy|SabreClient $sabreClient;

    protected function setUp() : void
    {
        $this->sabreClient = $this->prophesize(SabreClient::class);
    }

    private function getFixture() : Client
    {
        $client = new Client(new Config('foo', 'bar', 'baz'));

        $reflection = new \ReflectionClass(Client::class);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($client, $this->sabreClient->reveal());

        return $client;
    }

    public function testListFolderContentsReturnsArrayOfResourcesInFolderIfSabreClientPropfindReturnsArrayStartingWithTheListedFolder() : void
    {
        $queriedFolderPath = \uniqid();
        $expectedResource1 = "{$queriedFolderPath}/sample_file.csv";
        $expectedResource2 = "{$queriedFolderPath}/sample_folder";

        $sabrePropfindResult = [
            "/absolute/path/to/{$queriedFolderPath}" => [
                '{DAV:}getcontenttype' => 'httpd/unix-directory',
                '{DAV:}getlastmodified' => 'Sun, 15 Aug 2021 23:04:03 GMT',
            ],
            "/absolute/path/to/{$expectedResource1}" => [
                '{DAV:}getcontentlength' => '244456',
                '{DAV:}getcontenttype' => 'text/csv',
                '{DAV:}getlastmodified' => 'Thu, 12 Aug 2021 23:04:03 GMT',
            ],
            "/absolute/path/to/{$expectedResource2}" => [
                '{DAV:}getcontenttype' => 'httpd/unix-directory',
                '{DAV:}getlastmodified' => 'Sun, 15 Aug 2021 23:04:03 GMT',
            ],
        ];

        $this->sabreClient->propFind($queriedFolderPath, [
            '{DAV:}getcontentlength',
            '{DAV:}getcontenttype',
            '{DAV:}getlastmodified',
        ], 1)->willReturn($sabrePropfindResult);

        $client = $this->getFixture();
        $results = $client->listFolderContents($queriedFolderPath);

        self::assertCount(2, $results);
        self::assertInstanceOf(File::class, $results[0]);
        self::assertStringEndsWith($expectedResource1, $results[0]->path());
        self::assertInstanceOf(Folder::class, $results[1]);
        self::assertStringEndsWith($expectedResource2, $results[1]->path());
    }

    public function testListFolderContentsThrowsResourceNotFoundExceptionWithCode404IfSabreClientThrowsClientHttpExceptionWithStatus404() : void
    {
        $expectedSabreClientException = new ClientHttpException(new Response(404));

        $this->sabreClient->propFind(Argument::any(), [
            '{DAV:}getcontentlength',
            '{DAV:}getcontenttype',
            '{DAV:}getlastmodified',
        ], 1)->willThrow($expectedSabreClientException);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionCode(404);

        $client = $this->getFixture();
        $client->listFolderContents(\uniqid());
    }

    public function testListFolderContentsThrowsHttpExceptionWithSameMessageAndCodeAsClientHttpExceptionThrownBySabreClientIfItIsStatusOtherThan404() : void
    {
        $expectedStatus = 500;
        $expectedSabreClientException = new ClientHttpException(new Response($expectedStatus));

        $this->sabreClient->propFind(Argument::any(), [
            '{DAV:}getcontentlength',
            '{DAV:}getcontenttype',
            '{DAV:}getlastmodified',
        ], 1)->willThrow($expectedSabreClientException);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode($expectedStatus);
        $this->expectExceptionMessage($expectedSabreClientException->getMessage());

        $client = $this->getFixture();
        $client->listFolderContents(\uniqid());
    }

    public function testListFolderContentsThrowsResourceNotAFolderExceptionIfSabreClientPropfindReturnsArrayStartingWithAFile() : void
    {
        $queriedFolderPath = \uniqid();

        $sabrePropfindResult = [
            "/absolute/path/to/some/file" => [
                '{DAV:}getcontentlength' => '244456',
                '{DAV:}getcontenttype' => 'text/csv',
                '{DAV:}getlastmodified' => 'Thu, 12 Aug 2021 23:04:03 GMT',
            ],
        ];

        $this->sabreClient->propFind($queriedFolderPath, [
            '{DAV:}getcontentlength',
            '{DAV:}getcontenttype',
            '{DAV:}getlastmodified',
        ], 1)->willReturn($sabrePropfindResult);

        $this->expectException(ResourceNotAFolderException::class);

        $client = $this->getFixture();
        $client->listFolderContents($queriedFolderPath);
    }

    public function testGetFileContentsReturnsContentsOfFileIfSabreClientGetRequestReturnsItInCode200ResponseBody() : void
    {
        $queriedFilePath = \uniqid();
        $expectedFileContents = \uniqid();

        $sabreGetResponse = [
            'statusCode' => 200,
            'body' => $expectedFileContents,
        ];

        $this->sabreClient->request('GET', $queriedFilePath)->willReturn($sabreGetResponse);

        $client = $this->getFixture();
        $result = $client->getFileContents($queriedFilePath);
        self::assertSame($expectedFileContents, $result);
    }

    public function testGetFileContentsThrowsResourceNotFoundExceptionWithCode404IfSabreClientReturnsResponseWithCode404() : void
    {
        $sabreGetResponse = [
            'statusCode' => 404,
        ];

        $this->sabreClient->request('GET', Argument::any())->willReturn($sabreGetResponse);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionCode(404);

        $client = $this->getFixture();
        $client->getFileContents(\uniqid());
    }

    public function testGetFileContentsThrowsHttpExceptionWithSameCodeAsSabreClientResponseIfItIsOtherThan200And404() : void
    {
        $sabreGetResponse = [
            'statusCode' => 500,
        ];

        $this->sabreClient->request('GET', Argument::any())->willReturn($sabreGetResponse);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode($sabreGetResponse['statusCode']);

        $client = $this->getFixture();
        $client->getFileContents(\uniqid());
    }
}
