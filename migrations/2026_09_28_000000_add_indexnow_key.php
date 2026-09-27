<?php

/*
 * This file is part of blenderrus/flarum-indexnow.
 *
 * Copyright (c) 2026 Grigoriy Skidan.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Flarum\Database\Migration;

// Every forum gets its own random key on install; it can be replaced in the settings.
return Migration::addSettings([
    'blenderrus-indexnow.key' => bin2hex(random_bytes(16)),
]);
