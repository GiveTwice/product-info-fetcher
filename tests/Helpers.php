<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

function getFixture(string $path): string
{
    return file_get_contents(__DIR__.'/Fixtures/'.$path);
}

function createMockClient(array &$history, array $responses = []): Client
{
    if (empty($responses)) {
        $responses = [new Response(200, [], '<html><head></head><body></body></html>')];
    }

    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push(Middleware::history($history));

    return new Client(['handler' => $handlerStack]);
}
