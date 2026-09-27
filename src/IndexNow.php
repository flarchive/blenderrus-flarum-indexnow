<?php

/*
 * This file is part of blenderrus/flarum-indexnow.
 *
 * Copyright (c) 2026 Grigoriy Skidan.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BlenderRUS\IndexNow;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * The extension's settings and the IndexNow request itself (https://www.indexnow.org/documentation).
 */
class IndexNow
{
    public const DEFAULT_ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** The most addresses the protocol accepts in one request. */
    public const MAX_URLS = 10000;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected UrlGenerator $url,
        protected SlugManager $slugs,
        protected Client $http,
    ) {
    }

    public function key(): string
    {
        return trim((string) $this->settings->get('blenderrus-indexnow.key'));
    }

    /** Submissions are on while the key is valid: 8 to 128 characters of a-z, A-Z, 0-9 and "-". */
    public function isEnabled(): bool
    {
        return 1 === preg_match('/^[A-Za-z0-9-]{8,128}$/', $this->key());
    }

    /** Whether changes of this kind are submitted: "discussions", "replies", "edits" or "removals". */
    public function submits(string $kind): bool
    {
        return $this->isEnabled() && (bool) $this->settings->get("blenderrus-indexnow.submit_$kind");
    }

    public function endpoint(): string
    {
        $endpoint = trim((string) $this->settings->get('blenderrus-indexnow.endpoint'));
        if ('custom' === $endpoint) {
            $endpoint = trim((string) $this->settings->get('blenderrus-indexnow.custom_endpoint'));
        }

        return '' !== $endpoint && 'custom' !== $endpoint ? $endpoint : self::DEFAULT_ENDPOINT;
    }

    public function discussionUrl(Discussion $discussion): string
    {
        return $this->url->to('forum')->route('discussion', [
            'id' => $this->slugs->forResource(Discussion::class)->toSlug($discussion),
        ]);
    }

    /** Where the key file is served; given to the engines so that a forum in a subdirectory works too. */
    public function keyLocation(): string
    {
        return $this->url->to('forum')->route('blenderrus-indexnow.key', ['key' => $this->key()]);
    }

    /**
     * Sends the addresses and stores the outcome in the "last_result" setting shown in the admin panel.
     *
     * @param list<string> $urls at most MAX_URLS addresses on the forum's host
     * @return int 200, or 202 while the engine is still checking a new key
     * @throws GuzzleException on a network error or a 4xx/5xx answer
     */
    public function submit(array $urls): int
    {
        try {
            $status = $this->http->post($this->endpoint(), [
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'json' => [
                    'host' => parse_url($this->url->to('forum')->base(), PHP_URL_HOST),
                    'key' => $this->key(),
                    'keyLocation' => $this->keyLocation(),
                    'urlList' => array_values($urls),
                ],
                'timeout' => 10,
            ])->getStatusCode();
        } catch (GuzzleException $e) {
            $this->record($e instanceof RequestException ? $e->getResponse()?->getStatusCode() : null, count($urls), $e->getMessage());

            throw $e;
        }
        $this->record($status, count($urls), null);

        return $status;
    }

    protected function record(?int $status, int $count, ?string $error): void
    {
        $this->settings->set('blenderrus-indexnow.last_result', json_encode([
            'time' => Carbon::now()->toIso8601String(),
            'endpoint' => $this->endpoint(),
            'status' => $status,
            'count' => $count,
            'error' => null === $error ? null : mb_substr($error, 0, 500),
        ]));
    }
}
