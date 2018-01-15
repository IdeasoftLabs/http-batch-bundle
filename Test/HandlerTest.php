<?php
namespace Ideasoft\HttpBatchBundle\Test;

use Ideasoft\HttpBatchBundle\Handler;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\AppKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HandlerTest extends WebTestCase
{
    /**
     * @var Handler $handler
     */
    private $handler;

    /**
     * @var Request $batchRequest
     */
    private $batchRequest;

    /**
     * @var string $boundary
     */
    private $boundary = "test-boundary";

    public function setUp()
    {
        parent::setUp();
        $sampleResponse = new Response(json_encode(["sample_response_key" => "sample_response_value"]), Response::HTTP_OK);
        $kernelMock = $this->getMockBuilder(AppKernel::class)->disableOriginalConstructor()->setMethods(["handle"])->getMock();
        $kernelMock->method("handle")->will($this->returnValue($sampleResponse));
        $this->batchRequest = new Request();
        $this->handler = new Handler($kernelMock);
    }

    public function testParseRequest()
    {
        $content = <<<EOT
--$this->boundary
Content-Type: application/http;version=1.1
Content-Transfer-Encoding: binary
Content-ID: <da8f53ab6e3d05fef27dd3392b0fec64-users@localhost>

GET http://localhost:8000/users
Host:localhost:8000
Authorization:Bearer TOKEN
--$this->boundary
Content-Type: application/http;version=1.1
Content-Transfer-Encoding: binary
Content-ID: <da8f53ab6e3d05fef27dd3392b0fec64-orders@localhost>

GET http://localhost:8000/users
Host:localhost:8000
Authorization:Bearer TOKEN
--$this->boundary
Content-Type: application/http;version=1.1
Content-Transfer-Encoding: binary
Content-ID: <da8f53ab6e3d05fef27dd3392b0fec64-orders@localhost>

GET http://localhost:8000/users
Host:localhost:8000
Authorization:Bearer TOKEN
--$this->boundary--
EOT;

        $request = new Request([], [], [], [], [], [], $content);
        $request->headers->set("Content-Type", 'multipart/batch; type="application/http;version=1.1"; boundary=' . $this->boundary);
        $request->headers->set('Authorization', 'Bearer TOKEN');

        /** @var Response $response */
        $response = $this->invokeRestrictedMethodAndProperties(
            $this->handler, "parseRequest", [$request],
            ["batchRequest" => $this->batchRequest, "boundary" => $this->boundary]);

        $data = current(explode("--" . $this->boundary . "--", $response->getContent()));
        $subResponses = array_values(
            array_filter(explode("--" . $this->boundary, $data), function ($input) {
                return !empty($input);
            })
        );
        $sampleSubResponseMessage = explode(PHP_EOL . PHP_EOL, $subResponses[0], 2)[1];
        $sampleResponse = \GuzzleHttp\Psr7\parse_response($sampleSubResponseMessage);
        $this->assertTrue($response->getStatusCode() == 200);
        $this->assertTrue(trim($sampleResponse->getBody()->getContents()) == json_encode(["sample_response_key" => "sample_response_value"]));

        $this->assertTrue(200 == $response->getStatusCode());
        $this->assertContains('sample_response_key', $response->getContent());
        $this->assertTrue(sizeof($subResponses) == 3);
    }

    public function testGetBatchHeader()
    {
        $request = new Request();
        $request->headers->set("Content-Type", 'multipart/batch; type="application/http;version=1.1"; boundary=' . $this->boundary);
        $request->headers->set('Authorization', 'Bearer TOKEN');
        $headerData = $this->invokeRestrictedMethodAndProperties(
            $this->handler, "getBatchHeader", [$request],
            ["batchRequest" => $this->batchRequest, "boundary" => $this->boundary]);

        $this->assertTrue(sizeof($headerData) == 2);
        $this->assertTrue(current($headerData['authorization']) == 'Bearer TOKEN');
        $this->assertTrue(current($headerData['content-type']) == 'multipart/batch; type="application/http;version=1.1"; boundary=' . $this->boundary);
    }

    public function testParseBoundary()
    {
        $contentType = 'multipart/batch; type=\"application/http;version=1.1\"; boundary=' . $this->boundary;
        $boundary = $this->invokeRestrictedMethodAndProperties(
            $this->handler, "parseBoundary", [$contentType],
            ["batchRequest" => $this->batchRequest, "boundary" => $this->boundary]);
        $this->assertTrue($boundary == $this->boundary);
    }

    public function testGetSubRequests()
    {
        $content = <<<EOT
--$this->boundary
Content-Type: application/http;version=1.1
Content-Transfer-Encoding: binary
Content-ID: <da8f53ab6e3d05fef27dd3392b0fec64-users@localhost>

GET http://localhost:8000/users
Host:localhost:8000
Authorization:Bearer TOKEN
--$this->boundary
Content-Type: application/http;version=1.1
Content-Transfer-Encoding: binary
Content-ID: <da8f53ab6e3d05fef27dd3392b0fec64-orders@localhost>

GET http://localhost:8000/users
Host:localhost:8000
Authorization:Bearer TOKEN
--$this->boundary
Content-Type: application/http;version=1.1
Content-Transfer-Encoding: binary
Content-ID: <da8f53ab6e3d05fef27dd3392b0fec64-orders@localhost>

GET http://localhost:8000/users
Host:localhost:8000
Authorization:Bearer TOKEN
--$this->boundary--
EOT;

        $batchRequest = new Request([], [], [], [], [], [], $content);
        $batchRequest->setMethod(Request::METHOD_POST);
        $subRequests = $this->invokeRestrictedMethodAndProperties(
            $this->handler, "getSubRequests", [$batchRequest],
            ["batchRequest" => $this->batchRequest, "boundary" => $this->boundary]);

        $this->assertTrue(sizeof($subRequests) == 3);
        foreach ($subRequests as $subRequest) {
            $this->assertInstanceOf('Symfony\Component\HttpFoundation\Request', $subRequest);
        }
    }

    public function testConvertGuzzleRequestToSymfonyRequest()
    {
        $guzzleRequest = new \GuzzleHttp\Psr7\Request(
            "POST", "http://test-url.com/test", ["Authorization" => "Bearer TOKEN"], "TestContent"
        );

        /** @var Request $symfonyRequest */
        $symfonyRequest = $this->invokeRestrictedMethodAndProperties(
            $this->handler, "convertGuzzleRequestToSymfonyRequest", [$guzzleRequest],
            ["batchRequest" => $this->batchRequest, "boundary" => $this->boundary]);

        $this->assertTrue($symfonyRequest->getContent() == $guzzleRequest->getBody()->getContents());
        $this->assertTrue($symfonyRequest->getMethod() == $guzzleRequest->getMethod());
        $this->assertTrue($symfonyRequest->headers->get("Authorization") == current($guzzleRequest->getHeader("Authorization")));
    }

    public function testGetBatchRequestResponse()
    {
        $req = new Request();
        $req->setMethod("GET");

        /** @var Response $batchRequestResponse */
        $batchRequestResponse = $this->invokeRestrictedMethodAndProperties(
            $this->handler, "getBatchRequestResponse", [[$req]],
            ["batchRequest" => $this->batchRequest, "boundary" => $this->boundary]);

        $this->assertEquals(Response::HTTP_OK, $batchRequestResponse->getStatusCode());
        $this->assertContains("--" . $this->boundary, $batchRequestResponse->getContent());
        $this->assertContains("--" . $this->boundary . "--", $batchRequestResponse->getContent());
    }

    public function testGenerateBatchResponseFromSubResponses()
    {
        $data = ["testKey" => "testValue"];
        $responses = [
            new Response(json_encode($data), Response::HTTP_OK, ["content-type" => "json"]),
            new Response("", Response::HTTP_NOT_FOUND)

        ];
        /** @var Response $batchResponse */
        $batchResponse = $this->invokeRestrictedMethodAndProperties(
            $this->handler, "generateBatchResponseFromSubResponses", [$responses],
            ["batchRequest" => $this->batchRequest, "boundary" => $this->boundary]);

        $this->assertContains("--" . $this->boundary, $batchResponse->getContent());
        $this->assertContains("--" . $this->boundary . "--", $batchResponse->getContent());
        $this->assertContains(json_encode($data), $batchResponse->getContent());
        $this->assertContains((string)Response::HTTP_NOT_FOUND, $batchResponse->getContent());
    }

    public function testGenerateSubResponseFromContent()
    {
        $data = ["testKey" => "testValue"];
        $response = new Response(json_encode($data), Response::HTTP_OK, ["content-type" => "json"]);
        $subResponse = $this->invokeRestrictedMethodAndProperties(
            $this->handler, "generateSubResponseFromContent", [$response], ["batchRequest" => $this->batchRequest]);

        $response = \GuzzleHttp\Psr7\parse_response(explode(PHP_EOL . PHP_EOL, $subResponse, 2)[1]);
        $this->assertTrue(json_encode($data) == $response->getBody()->getContents());
        $this->assertTrue(Response::HTTP_OK == $response->getStatusCode());
    }

    private function invokeRestrictedMethodAndProperties($object, $methodName, $args = [], $properties = [])
    {
        $reflectionClass = new \ReflectionClass(get_class($object));
        $method = $reflectionClass->getMethod($methodName);
        $method->setAccessible(true);
        foreach ($properties as $propertyKey => $value) {
            $prop = $reflectionClass->getProperty($propertyKey);
            $prop->setAccessible(true);
            $prop->setValue($object, $value);
        }
        return $method->invokeArgs($object, $args);
    }
}
