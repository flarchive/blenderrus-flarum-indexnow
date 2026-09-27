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
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SubmissionTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use MocksSearchEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('blenderrus-indexnow');
        $this->setting('blenderrus-indexnow.key', 'test-indexnow-key');
        $this->setting('blenderrus-indexnow.endpoint', 'https://yandex.com/indexnow');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'discussions' => [
                ['id' => 1, 'title' => 'Public discussion', 'slug' => 'public-discussion', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1],
                ['id' => 2, 'title' => 'Hidden discussion', 'slug' => 'hidden-discussion', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 2, 'comment_count' => 1, 'hidden_at' => Carbon::now()],
            ],
            'posts' => [
                ['id' => 1, 'number' => 1, 'discussion_id' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>First post</p></t>'],
                ['id' => 2, 'number' => 1, 'discussion_id' => 2, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Hidden post</p></t>'],
            ],
        ]);
    }

    private function reply(int $discussionId): int
    {
        $response = $this->send($this->request('POST', '/api/posts', [
            'authenticatedAs' => 1,
            'json' => ['data' => [
                'attributes' => ['content' => 'A reply'],
                'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => (string) $discussionId]]],
            ]],
        ]));

        return $response->getStatusCode();
    }

    #[Test]
    public function a_new_discussion_is_submitted_with_the_key_and_its_location(): void
    {
        $this->mockSearchEngine(202);

        $response = $this->send($this->request('POST', '/api/discussions', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['title' => 'Fresh news', 'content' => 'Something happened']]],
        ]));

        $this->assertSame(201, $response->getStatusCode());
        $id = json_decode((string) $response->getBody(), true)['data']['id'];
        $this->assertCount(1, $this->submissions);
        $this->assertSame('https://yandex.com/indexnow', (string) $this->submissions[0]['request']->getUri());
        $this->assertSame('application/json; charset=utf-8', $this->submissions[0]['request']->getHeaderLine('Content-Type'));
        $this->assertSame([
            'host' => 'localhost',
            'key' => 'test-indexnow-key',
            'keyLocation' => 'http://localhost/test-indexnow-key.txt',
            'urlList' => ["http://localhost/d/$id-fresh-news"],
        ], $this->submittedBody(0));

        $last = json_decode($this->app()->getContainer()->make(SettingsRepositoryInterface::class)->get('blenderrus-indexnow.last_result'), true);
        $this->assertSame(202, $last['status']);
        $this->assertSame(1, $last['count']);
        $this->assertNull($last['error']);
    }

    #[Test]
    public function a_reply_is_submitted(): void
    {
        $this->mockSearchEngine();

        $this->assertSame(201, $this->reply(1));

        $this->assertSame(['http://localhost/d/1-public-discussion'], $this->submittedBody(0)['urlList']);
    }

    #[Test]
    public function a_switched_off_kind_of_change_is_not_submitted(): void
    {
        $this->setting('blenderrus-indexnow.submit_replies', '0');
        $this->mockSearchEngine();

        $this->assertSame(201, $this->reply(1));

        $this->assertCount(0, $this->submissions);
    }

    #[Test]
    public function new_discussions_and_replies_have_separate_switches(): void
    {
        $this->setting('blenderrus-indexnow.submit_discussions', '0');
        $this->mockSearchEngine();

        $response = $this->send($this->request('POST', '/api/discussions', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['title' => 'Not submitted', 'content' => 'Its first post']]],
        ]));
        $this->assertSame(201, $response->getStatusCode());
        $this->assertCount(0, $this->submissions);

        $this->assertSame(201, $this->reply(1));
        $this->assertCount(1, $this->submissions);
    }

    #[Test]
    public function a_discussion_guests_cannot_see_is_not_submitted(): void
    {
        $this->mockSearchEngine();

        $this->assertSame(201, $this->reply(2));

        $this->assertCount(0, $this->submissions);
    }

    #[Test]
    public function a_public_discussion_is_submitted_when_it_is_hidden(): void
    {
        $this->mockSearchEngine();

        $response = $this->send($this->request('PATCH', '/api/discussions/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['isHidden' => true]]],
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $this->submissions);
        $this->assertSame(['http://localhost/d/1-public-discussion'], $this->submittedBody(0)['urlList']);
    }

    #[Test]
    public function a_public_discussion_is_submitted_when_it_is_deleted(): void
    {
        $this->mockSearchEngine();

        $response = $this->send($this->request('DELETE', '/api/discussions/1', ['authenticatedAs' => 1]));

        $this->assertSame(204, $response->getStatusCode());
        $this->assertCount(1, $this->submissions);
        $this->assertSame(['http://localhost/d/1-public-discussion'], $this->submittedBody(0)['urlList']);
    }

    #[Test]
    public function a_discussion_that_was_already_hidden_is_not_submitted_when_deleted(): void
    {
        $this->mockSearchEngine();

        $response = $this->send($this->request('DELETE', '/api/discussions/2', ['authenticatedAs' => 1]));

        $this->assertSame(204, $response->getStatusCode());
        $this->assertCount(0, $this->submissions);
    }

    #[Test]
    public function a_failing_search_engine_does_not_break_posting(): void
    {
        $this->mockSearchEngine(500);

        $this->assertSame(201, $this->reply(1));

        $last = json_decode($this->app()->getContainer()->make(SettingsRepositoryInterface::class)->get('blenderrus-indexnow.last_result'), true);
        $this->assertSame(500, $last['status']);
        $this->assertNotEmpty($last['error']);
    }

    #[Test]
    public function nothing_is_submitted_without_a_key(): void
    {
        $this->setting('blenderrus-indexnow.key', '');
        $this->mockSearchEngine();

        $this->assertSame(201, $this->reply(1));

        $this->assertCount(0, $this->submissions);
    }
}
