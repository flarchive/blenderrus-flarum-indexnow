<?php

/*
 * This file is part of blenderrus/flarum-indexnow.
 *
 * Copyright (c) 2026 Grigoriy Skidan.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BlenderRUS\IndexNow\Tests\integration;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Replaces the HTTP client with one that answers with the given statuses and records the requests.
 */
trait MocksSearchEngine
{
    /** @var list<array{request: \Psr\Http\Message\RequestInterface}> */
    protected array $submissions = [];

    protected function mockSearchEngine(int ...$statuses): void
    {
        $stack = HandlerStack::create(new MockHandler(array_map(
            fn (int $status) => new Response($status),
            $statuses ?: array_fill(0, 10, 200),
        )));
        $stack->push(Middleware::history($this->submissions));

        $this->app()->getContainer()->instance(Client::class, new Client(['handler' => $stack]));
    }

    /** @return array<string, mixed> */
    protected function submittedBody(int $index): array
    {
        return json_decode((string) $this->submissions[$index]['request']->getBody(), true);
    }
}
