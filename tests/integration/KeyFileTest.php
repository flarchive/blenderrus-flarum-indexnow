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

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class KeyFileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('blenderrus-indexnow');
    }

    #[Test]
    public function the_key_file_is_served(): void
    {
        $this->setting('blenderrus-indexnow.key', 'test-indexnow-key');

        $response = $this->send($this->request('GET', '/test-indexnow-key.txt'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('test-indexnow-key', (string) $response->getBody());
    }

    #[Test]
    public function another_key_is_not_found(): void
    {
        $this->setting('blenderrus-indexnow.key', 'test-indexnow-key');

        $this->assertSame(404, $this->send($this->request('GET', '/another-key-1234.txt'))->getStatusCode());
    }

    #[Test]
    public function nothing_is_served_without_a_key(): void
    {
        $this->setting('blenderrus-indexnow.key', '');

        $this->assertSame(404, $this->send($this->request('GET', '/test-indexnow-key.txt'))->getStatusCode());
    }

    #[Test]
    public function installing_the_extension_generates_a_key(): void
    {
        $key = $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->get('blenderrus-indexnow.key');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $key);
    }
}
