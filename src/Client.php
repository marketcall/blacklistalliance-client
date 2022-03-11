<?php

namespace Marketcall\BlacklistallianceClient;

use Exception;
use GuzzleHttp\Client as Guzzle;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\ClientException;
use Marketcall\BlacklistallianceClient\Exceptions\InvalidApiKey;
use Marketcall\BlacklistallianceClient\Exceptions\IllegalResponse;
use Marketcall\BlacklistallianceClient\Exceptions\InvalidPhoneNumber;
use Marketcall\BlacklistallianceClient\Exceptions\BlacklistAllianceClientException;

class Client
{

    /**
     * @var string
     */
    private $endpoint;
    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var Guzzle
     */
    protected $client;

    public function __construct(string $endpoint, string $apiKey, Guzzle $client)
    {
        $this->endpoint = $endpoint;
        $this->apiKey = $apiKey;
        $this->client = $client;
    }

    public function lookup(string $phone): array
    {
        $uri = sprintf('/standard/api/v1/Lookup/key/%s/phone/%s/response/json',
            $this->apiKey,
            $phone
        );

        return $this->get($uri);
    }

    protected function get($uri): array
    {
        try {
            $response = $this->client->request('get', $this->endpoint . $uri, ['headers' => $this->headers()]);
        } catch (ClientException $e) {
            throw new BlacklistAllianceClientException($e->getMessage(), $e->getCode(), $e); // temp
        } catch (Exception $e) {
            throw new BlacklistAllianceClientException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->parseResponse($response);
    }

    protected function headers(array $overrides = []): array
    {
        return array_merge([
            'User-Agent' => 'marketcall-blacklisted-client/1.0',
            'Accept' => 'application/json',
        ], $overrides);
    }

    protected function parseResponse(ResponseInterface $response): array
    {
        $body = $response->getBody()->getContents();

        if (!$body) {
            throw new IllegalResponse('Response body is empty');
        }

        $content = json_decode($body, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new IllegalResponse('Error when decoding json response: ' . json_last_error_msg());
        }

        if ($content['status'] !== 'success') {
            throw $this->generateClientExceptionFromResponse($content);
        }

        return $content;
    }

    protected function generateClientExceptionFromResponse(array $response): Exception
    {
        $message = $response['message'];

        if ($message === 'No valid phone number was found please try again') {
            return new InvalidPhoneNumber($message);
        }

        if (preg_match('/Access denied for your API key and IP/', $message) === 1) {
            return new InvalidApiKey($message);
        }

        return new Exception($message);
    }
}
