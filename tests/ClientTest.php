<?php

namespace Tests;

use Exception;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Client as Guzzle;
use Marketcall\BlacklistallianceClient\Client;
use Marketcall\BlacklistallianceClient\Exceptions\InvalidApiKey;
use Marketcall\BlacklistallianceClient\Exceptions\IllegalResponse;
use Marketcall\BlacklistallianceClient\Exceptions\InvalidPhoneNumber;

class ClientTest extends TestCase
{
    /** @test */
    public function it_sends_correct_request_to_endpoint_on_lookup()
    {
        $container = [];
        $history = Middleware::history($container);

        $guzzleMock = new MockHandler([new Response(200, [], '{ "status": "success" }')]);

        $stack = HandlerStack::create($guzzleMock);
        $stack->push($history);

        $guzzle = new Guzzle(['handler' => $stack]);

        $client = new Client('http://endpoint.example', 'api-key', $guzzle);

        $client->lookup('11231234567');

        $this->assertCount(1, $container);

        /** @var Request $request */
        $request = $container[0]['request'];
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals(
            'http://endpoint.example/standard/api/v1/Lookup/key/api-key/response/json/phone/11231234567',
            (string) $request->getUri()
        );
    }

    /** @test */
    public function it_correctly_handles_good_response()
    {
        $response = file_get_contents(__DIR__.'/stubs/good-response.json');

        $guzzleMock = new MockHandler([new Response(200, [], $response)]);

        $stack = HandlerStack::create($guzzleMock);

        $guzzle = new Guzzle(['handler' => $stack]);

        $client = new Client('http://endpoint.example', 'api-key', $guzzle);

        $result = $client->lookup('11231234567');

        $this->assertEquals('88510c0a-c026-43bc-a418-4d146dd53a2d', $result['sid']);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Good', $result['message']);
        $this->assertEquals('none', $result['code']);
        $this->assertEquals('1569839960607840000', $result['offset']);
        $this->assertEquals('0', $result['results']);
        $this->assertEquals('0', $result['wireless']);
    }

    /** @test */
    public function it_correctly_handles_blacklisted_response()
    {
        $response = file_get_contents(__DIR__.'/stubs/blacklisted-response.json');

        $guzzleMock = new MockHandler([new Response(200, [], $response)]);

        $stack = HandlerStack::create($guzzleMock);

        $guzzle = new Guzzle(['handler' => $stack]);

        $client = new Client('http://endpoint.example', 'api-key', $guzzle);

        $result = $client->lookup('11231234567');

        $this->assertEquals('d3cf8ed3-a5f6-4998-8117-21328e4d771f', $result['sid']);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Blacklisted', $result['message']);
        $this->assertEquals('plaintiff_primary', $result['code']);
        $this->assertEquals('1569840053617862912', $result['offset']);
        $this->assertEquals('1', $result['results']);
        $this->assertEquals('0', $result['wireless']);
    }

    /** @test */
    public function it_throws_on_invalid_api_key_response()
    {
        $response = file_get_contents(__DIR__.'/stubs/invalid-api-key-response.json');

        $guzzleMock = new MockHandler([new Response(200, [], $response)]);

        $stack = HandlerStack::create($guzzleMock);

        $guzzle = new Guzzle(['handler' => $stack]);

        $client = new Client('http://endpoint.example', 'api-key', $guzzle);

        try {
            $client->lookup('11231234567');
        } catch (Exception $e) {
            $this->assertInstanceOf(InvalidApiKey::class, $e);
            $this->assertMatchesRegularExpression('/Access denied for your API key and IP/', $e->getMessage());
            return;
        }

        $this->fail('Expected InvalidApiKey exception was not thrown.');
    }

    /** @test */
    public function it_throws_on_invalid_phone_number_response()
    {
        $response = file_get_contents(__DIR__.'/stubs/invalid-phone-number-response.json');

        $guzzleMock = new MockHandler([new Response(200, [], $response)]);

        $stack = HandlerStack::create($guzzleMock);

        $guzzle = new Guzzle(['handler' => $stack]);

        $client = new Client('http://endpoint.example', 'api-key', $guzzle);

        try {
            $client->lookup('11231234567');
        } catch (Exception $e) {
            $this->assertInstanceOf(InvalidPhoneNumber::class, $e);
            $this->assertEquals('No valid phone number was found please try again', $e->getMessage());
            return;
        }

        $this->fail('Expected InvalidApiKey exception was not thrown.');
    }

    /** @test */
    public function it_throws_on_empty_response()
    {
        $guzzleMock = new MockHandler([new Response(200, [], null)]);

        $stack = HandlerStack::create($guzzleMock);

        $guzzle = new Guzzle(['handler' => $stack]);

        $client = new Client('http://endpoint.example', 'api-key', $guzzle);

        try {
            $client->lookup('11231234567');
        } catch (Exception $e) {
            $this->assertInstanceOf(IllegalResponse::class, $e);
            $this->assertEquals('Response body is empty', $e->getMessage());
            return;
        }

        $this->fail('Expected IllegalResponse exception was not thrown.');
    }

    /** @test */
    public function it_throws_on_malformed_response()
    {
        $guzzleMock = new MockHandler([new Response(200, [], '{')]);

        $stack = HandlerStack::create($guzzleMock);

        $guzzle = new Guzzle(['handler' => $stack]);

        $client = new Client('http://endpoint.example', 'api-key', $guzzle);

        try {
            $client->lookup('11231234567');
        } catch (Exception $e) {
            $this->assertInstanceOf(IllegalResponse::class, $e);
            $this->assertEquals('Error when decoding json response: Syntax error', $e->getMessage());
            return;
        }

        $this->fail('Expected IllegalResponse exception was not thrown.');
    }

}