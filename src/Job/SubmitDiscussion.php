<?php

/*
 * This file is part of blenderrus/flarum-indexnow.
 *
 * Copyright (c) 2026 Grigoriy Skidan.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BlenderRUS\IndexNow\Job;

use BlenderRUS\IndexNow\IndexNow;
use Flarum\Discussion\Discussion;
use Flarum\Queue\AbstractJob;
use Flarum\User\Guest;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Submits one discussion address. Runs on the forum's queue, or inside the request with the default "sync" driver.
 */
class SubmitDiscussion extends AbstractJob
{
    /**
     * @param int $discussionId submitted only if a guest can see the discussion when the job runs
     * @param string|null $url the address of a public discussion that is being hidden or deleted: submitted as is
     */
    public function __construct(
        protected int $discussionId,
        protected ?string $url = null,
    ) {
        parent::__construct();
    }

    public function handle(IndexNow $indexNow, LoggerInterface $logger): void
    {
        if (! $indexNow->isEnabled()) {
            return;
        }
        $url = $this->url;
        if (null === $url) {
            $discussion = Discussion::whereVisibleTo(new Guest())->find($this->discussionId);
            if (null === $discussion) {
                return;
            }
            $url = $indexNow->discussionUrl($discussion);
        }

        try {
            $indexNow->submit([$url]);
        } catch (GuzzleException $e) {
            // A search engine hint must never break posting: with the sync queue this runs inside the request.
            $logger->warning('IndexNow submission failed: '.$e->getMessage(), ['url' => $url]);
        }
    }
}
