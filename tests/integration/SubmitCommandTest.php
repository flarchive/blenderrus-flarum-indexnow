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

use Carbon\Carbon;
use Flarum\Testing\integration\ConsoleTestCase;
use PHPUnit\Framework\Attributes\Test;

class SubmitCommandTest extends ConsoleTestCase
{
    use MocksSearchEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('blenderrus-indexnow');
        $this->setting('blenderrus-indexnow.key', 'test-indexnow-key');

        $this->prepareDatabase([
            'discussions' => [
                ['id' => 1, 'title' => 'First', 'slug' => 'first', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1],
                ['id' => 2, 'title' => 'Second', 'slug' => 'second', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 1, 'first_post_id' => 2, 'comment_count' => 1],
                ['id' => 3, 'title' => 'Hidden', 'slug' => 'hidden', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 1, 'first_post_id' => 3, 'comment_count' => 1, 'hidden_at' => Carbon::now()],
            ],
            'posts' => [
                ['id' => 1, 'number' => 1, 'discussion_id' => 1, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>One</p></t>'],
                ['id' => 2, 'number' => 1, 'discussion_id' => 2, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Two</p></t>'],
                ['id' => 3, 'number' => 1, 'discussion_id' => 3, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Three</p></t>'],
            ],
        ]);
    }

    #[Test]
    public function all_public_discussions_are_submitted(): void
    {
        $this->mockSearchEngine(200);

        $output = $this->runCommand(['command' => 'indexnow:submit', '--all' => true]);

        $this->assertStringContainsString('2 address(es) sent', $output);
        $this->assertSame(['http://localhost/d/1-first', 'http://localhost/d/2-second'], $this->submittedBody(0)['urlList']);
    }

    #[Test]
    public function given_addresses_are_submitted(): void
    {
        $this->mockSearchEngine(202);

        $output = $this->runCommand(['command' => 'indexnow:submit', 'urls' => ['http://localhost/', 'http://localhost/tags']]);

        $this->assertStringContainsString('HTTP 202', $output);
        $this->assertSame(['http://localhost/', 'http://localhost/tags'], $this->submittedBody(0)['urlList']);
    }

    #[Test]
    public function without_a_key_nothing_is_sent(): void
    {
        $this->setting('blenderrus-indexnow.key', '');
        $this->mockSearchEngine();

        $output = $this->runCommand(['command' => 'indexnow:submit', '--all' => true]);

        $this->assertStringContainsString('valid IndexNow key', $output);
        $this->assertCount(0, $this->submissions);
    }
}
