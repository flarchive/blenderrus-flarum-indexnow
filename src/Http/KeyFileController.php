<?php

/*
 * This file is part of blenderrus/flarum-indexnow.
 *
 * Copyright (c) 2026 Grigoriy Skidan.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BlenderRUS\IndexNow\Http;

use BlenderRUS\IndexNow\IndexNow;
use Flarum\Http\Exception\RouteNotFoundException;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serves /{key}.txt: the engines download it to check that submissions come from the forum's owner.
 */
class KeyFileController implements RequestHandlerInterface
{
    public function __construct(
        protected IndexNow $indexNow,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $key = $this->indexNow->key();
        if (! $this->indexNow->isEnabled() || ($request->getQueryParams()['key'] ?? null) !== $key) {
            throw new RouteNotFoundException();
        }

        return new TextResponse($key, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
