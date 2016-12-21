<?php
namespace Ideasoft\HttpBatchBundle;

use Ideasoft\HttpBatchBundle\Message\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;

class Handler
{
    private $boundary;

    /** @var  Kernel */
    private $kernel;


    /** @var  Request */
    private $batchRequest;

    public function __construct(\AppKernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public function handle(Request $request)
    {
        $this->batchRequest = $request;
        return $this->parseRequest($request);
    }

    private function parseRequest(Request $request)
    {
        $this->getBatchHeader($request);
        $subRequests = $this->getSubRequests($request);
        return $this->getBatchRequest($subRequests);

    }

    private function getBatchHeader(Request $request)
    {
        $headers = $request->headers->all();
        $contentType = $request->headers->get("content-type");
        $this->parseBoundary($contentType);
        return $headers;
    }

    private function parseBoundary($contentType)
    {
        if (!$contentType) {
            throw new \HttpHeaderException("Content-type can not be found in header");
        }
        $contentTypeData = explode(";", $contentType);

        foreach ($contentTypeData as $data) {
            $contentTypePart = explode("=", $data);
            if (sizeof($contentTypePart) == 2 && trim($contentTypePart[0]) == "boundary") {
                $this->boundary = trim($contentTypePart[1]);
                break;
            }
        }
    }

    private function getSubRequests(Request $request)
    {
        $subRequests = [];
        $content = explode("--" . $this->boundary . "--", $request->getContent())[0];
        $subRequestsAsString = explode("--" . $this->boundary, $content);
        array_map(function ($data) {
            trim($data);
        }, $subRequestsAsString);
        $subRequestsAsString = array_filter($subRequestsAsString, function ($data) {
            return strlen($data) > 0;
        });
        $subRequestsAsString = array_values($subRequestsAsString);
        foreach ($subRequestsAsString as $item) {
            $item = explode(PHP_EOL . PHP_EOL, $item, 2);
            $requestString = $item[1];

            $subRequests[] = $this->convertGuzzleRequestToSymfonyRequest(\GuzzleHttp\Psr7\parse_request($requestString));

        }

        return $subRequests;
    }

    private function convertGuzzleRequestToSymfonyRequest(\GuzzleHttp\Psr7\Request $guzzleRequest)
    {
        $url = $guzzleRequest->getUri()->getPath();
        $method = $guzzleRequest->getMethod();
        parse_str($guzzleRequest->getUri()->getQuery(), $params);
        $cookies = [];
        $files = [];
        $server = [];
        $content = $guzzleRequest->getBody();

        $symfonyRequest = Request::create($url, $method, $params, $cookies, $files, $server, $content);
        return $symfonyRequest;
    }

    private function getBatchRequest(array $subRequests)
    {
        $subResponses = [];
        foreach ($subRequests as $subRequest) {
            $subResponses[] = $this->kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
        }
        return $this->generateBatchResponseFromSubResponses($subResponses);
    }

    private function generateBatchResponseFromSubResponses($subResponses)
    {
        $response = new Response();
        $version = $response->getProtocolVersion();
        $contentType = 'multipart/batch;type="application/http;type=' . $version . '";boundary=' . $this->boundary;
        $response->headers->set("Content-Type", $contentType);

        $contentForSubResponses = [];
        foreach ($subResponses as $subResponse) {
            $contentForSubResponses[] = $this->generateSubResponseFromContent($subResponse);
        }
        $content = "--" . $this->boundary . PHP_EOL;
        $content .= implode(PHP_EOL . "--" . $this->boundary . PHP_EOL, $contentForSubResponses) . PHP_EOL;
        $content .= "--" . $this->boundary . "--" . PHP_EOL;
        $response->setContent($content);
        return $response;
    }

    private function generateSubResponseFromContent(\Symfony\Component\HttpFoundation\Response $response)
    {
        $content = '';
        $content .= 'Content-Type: application/http;version=' . $response->getProtocolVersion() . PHP_EOL;
        $content .= 'Content-Transfer-Encoding: binary';
        $content .= 'In-Reply-To: <' . $this->boundary . '@' . $this->batchRequest->getHost() . '>' . PHP_EOL;
        $content .= PHP_EOL;
        $content .= "HTTP/" . $response->getProtocolVersion() . " " . $response->getStatusCode() . " " . Response::$statusTexts[$response->getStatusCode()] . PHP_EOL;
        foreach ($response->headers->allPreserveCase() as $key => $value) {
            $content .= sprintf("%s:%s" . PHP_EOL, $key, implode(",", $value));
        }
        $content .= PHP_EOL;
        $content .= $response->getContent();
        return $content;

    }
}