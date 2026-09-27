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

use Flarum\Extend;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Settings())
        ->default('blenderrus-indexnow.endpoint', IndexNow::DEFAULT_ENDPOINT)
        ->default('blenderrus-indexnow.custom_endpoint', '')
        ->default('blenderrus-indexnow.submit_discussions', true)
        ->default('blenderrus-indexnow.submit_replies', true)
        ->default('blenderrus-indexnow.submit_edits', true)
        ->default('blenderrus-indexnow.submit_removals', true),

    (new Extend\Routes('forum'))
        ->get('/{key:[A-Za-z0-9-]{8,128}}.txt', 'blenderrus-indexnow.key', Http\KeyFileController::class),

    (new Extend\Event())
        ->subscribe(Listener::class),

    (new Extend\Console())
        ->command(Console\SubmitCommand::class),
];
