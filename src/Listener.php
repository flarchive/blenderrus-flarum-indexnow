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

use BlenderRUS\IndexNow\Job\SubmitDiscussion;
use Flarum\Approval\Event\PostWasApproved;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event as DiscussionEvent;
use Flarum\Post\Event as PostEvent;
use Flarum\Post\Post;
use Flarum\Tags\Event\DiscussionWasTagged;
use Flarum\User\Guest;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Queue;

/**
 * Queues a submission when a discussion's public page appears, changes or goes away.
 *
 * Whether a guest can see the discussion is checked by the job, once the post counters are up to date. A discussion
 * that is being hidden or deleted is checked here instead, while the database still shows it as it was.
 */
class Listener
{
    public function __construct(
        protected IndexNow $indexNow,
        protected Queue $queue,
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        // A new discussion or reply, or one that has just been approved (flarum/approval).
        $events->listen(PostEvent\Posted::class, fn (PostEvent\Posted $event) => $this->posted($event->post));
        $events->listen(PostWasApproved::class, fn (PostWasApproved $event) => $this->posted($event->post));

        foreach ([PostEvent\Revised::class, PostEvent\Hidden::class, PostEvent\Restored::class, PostEvent\Deleted::class] as $event) {
            $events->listen($event, fn (PostEvent\Revised|PostEvent\Hidden|PostEvent\Restored|PostEvent\Deleted $event) => $this->changed('edits', $event->post->discussion_id));
        }
        $events->listen(DiscussionEvent\Renamed::class, fn (DiscussionEvent\Renamed $event) => $this->changed('edits', $event->discussion->id));
        $events->listen(DiscussionWasTagged::class, fn (DiscussionWasTagged $event) => $this->changed('edits', $event->discussion->id));
        $events->listen(DiscussionEvent\Restored::class, fn (DiscussionEvent\Restored $event) => $this->changed('removals', $event->discussion->id));

        // Saving comes after hide() has set hidden_at on the model and before the row is written.
        $events->listen(DiscussionEvent\Saving::class, function (DiscussionEvent\Saving $event): void {
            $discussion = $event->discussion;
            if ($discussion->exists && null !== $discussion->hidden_at && $discussion->isDirty('hidden_at')) {
                $this->removed($discussion);
            }
        });
        $events->listen(DiscussionEvent\Deleting::class, fn (DiscussionEvent\Deleting $event) => $this->removed($event->discussion));
    }

    protected function posted(Post $post): void
    {
        // The number is an integer again once the post is saved and refreshed, which is before its events fire.
        $this->changed(1 === $post->number ? 'discussions' : 'replies', $post->discussion_id);
    }

    protected function changed(string $kind, int $discussionId): void
    {
        if ($this->indexNow->submits($kind)) {
            $this->queue->push(new SubmitDiscussion($discussionId));
        }
    }

    protected function removed(Discussion $discussion): void
    {
        if ($this->indexNow->submits('removals') && Discussion::whereVisibleTo(new Guest())->whereKey($discussion->id)->exists()) {
            $this->queue->push(new SubmitDiscussion($discussion->id, $this->indexNow->discussionUrl($discussion)));
        }
    }
}
