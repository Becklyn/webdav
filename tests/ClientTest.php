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

    /**
     * @dataProvider provideSabreClientHttpExceptionAndExpectedBecklynHttpException
     */
    public function testListFolderContentsThrowsAppropriateHttpExceptionDependingOnClientHttpExceptionThrownBySabreClient(
        ClientHttpException $sabreClientException,
        string $expectedExceptionClass,
        int $expectedExceptionCode,
        ?string $expectedExceptionMessage
    ): void {
        $this->sabreClient->propFind(Argument::any(), [
            '{DAV:}getcontentlength',
            '{DAV:}getcontenttype',
            '{DAV:}getlastmodified',
        ], 1)->willThrow($sabreClientException);

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionCode($expectedExceptionCode);
        if ($expectedExceptionMessage !== null) {
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $client = $this->getFixture();
        $client->listFolderContents(\uniqid());
    }

    public function provideSabreClientHttpExceptionAndExpectedBecklynHttpException () : iterable
    {
        yield 'throws ResourceNotFoundException with code 404 if sabre client throws ClientHttpException with status 404' => [
            'sabre client exception' => new ClientHttpException(new Response(404)),
            'expected becklyn exception' => ResourceNotFoundException::class,
            'expected exception code' => 404,
            'expected exception message' => null
        ];

        $e = new ClientHttpException(new Response(500));

        yield 'throws HttpException with same message and code as ClientHttpException thrown by sabre client if it is status other than 404' => [
            'sabre client exception' => $e,
            'expected becklyn exception' => HttpException::class,
            'expected exception code' => $e->getCode(),
            'expected exception message' => $e->getMessage()
        ];
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

    /**
     * @dataProvider provideSabreClientResponseAndExpectedHttpException
     */
    public function testGetFileContentsThrowsAppropriateHttpExceptionDependingOnStatusCodeInSabreClientResponse(
        array $sabreResponse,
        string $expectedExceptionClass,
        int $expectedExceptionCode
    ): void {
        $this->sabreClient->request('GET', Argument::any())->willReturn($sabreResponse);

        $this->expectException($expectedExceptionClass);
        $this->expectExceptionCode($expectedExceptionCode);

        $client = $this->getFixture();
        $client->getFileContents(\uniqid());
    }

    public function provideSabreClientResponseAndExpectedHttpException () : iterable
    {
        yield 'throws ResourceNotFoundException with code 404 if sabre client returns 404 response' => [
            'sabre response' => [
                'statusCode' => 404,
            ],
            'expected exception class' => ResourceNotFoundException::class,
            'expected exception code' => 404
        ];

        $code = 500;

        yield 'throws HttpException with same code as sabre client response if it is other than 200 and 404' => [
            'sabre response' => [
                'statusCode' => $code,
            ],
            'expected exception class' => HttpException::class,
            'expected exception code' => $code
        ];
    }
}
