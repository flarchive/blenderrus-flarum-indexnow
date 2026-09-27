# IndexNow for Flarum

[![MIT license](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Latest Stable Version](https://img.shields.io/packagist/v/blenderrus/flarum-indexnow.svg)](https://packagist.org/packages/blenderrus/flarum-indexnow)
[![Total Downloads](https://img.shields.io/packagist/dt/blenderrus/flarum-indexnow.svg)](https://packagist.org/packages/blenderrus/flarum-indexnow)
[![Backend](https://github.com/BlenderRUS/flarum-indexnow/actions/workflows/backend.yml/badge.svg)](https://github.com/BlenderRUS/flarum-indexnow/actions/workflows/backend.yml)

A [Flarum](https://flarum.org) extension that tells search engines about new, changed and removed discussions
through the [IndexNow](https://www.indexnow.org) protocol, so they are recrawled within minutes instead of waiting
for the next visit of a crawler. IndexNow is supported by Microsoft Bing, Yandex, Seznam, Naver, Yep and others;
every submission is shared between all of them.

## Features

- Submits a discussion when it is started, gets a reply, is edited, renamed, retagged, approved (with
  [flarum/approval](https://github.com/flarum/approval)), hidden, restored or deleted.
- Submits only what guests can see: discussions in restricted tags, hidden or awaiting approval stay private.
  A discussion that is being hidden or deleted is submitted if it was public until then, so engines drop it sooner.
- Generates a key on install and serves it at `https://your-forum/<key>.txt`. The key location is sent with every
  request, so a forum installed in a subdirectory works too.
- Settings in the admin panel: the key, where to send (IndexNow.org, Bing, Yandex, Seznam, Naver, Yep, Internet
  Archive, Amazonbot or any other address), a switch for each kind of change and the result of the last submission.
- A console command to submit all public discussions at once, e.g. right after installing.
- A failing search engine never breaks posting: errors are logged and shown in the settings.
- English and Russian translations.

## Requirements

- Flarum 2.0 or later
- PHP 8.3 or later

## Installation

```sh
composer require blenderrus/flarum-indexnow
```

Then enable **IndexNow** in the admin panel. A key is generated when the extension is enabled; the settings page
shows the address of the key file.

To tell the engines about the discussions you already have:

```sh
php flarum indexnow:submit --all
```

## Updating

```sh
composer update blenderrus/flarum-indexnow
php flarum migrate
php flarum cache:clear
```

## How it works

Every change queues a job for its discussion. The job checks that a guest can see the discussion and sends its
address to the selected endpoint:

```http
POST https://api.indexnow.org/indexnow
Content-Type: application/json; charset=utf-8

{"host": "forum.example.com", "key": "…", "keyLocation": "https://forum.example.com/….txt",
 "urlList": ["https://forum.example.com/d/42-some-discussion"]}
```

The engine downloads the key file once to check that the submission comes from the site's owner: the first answer
is `202 Accepted`, later ones `200 OK`.

Jobs run on Flarum's queue. With the default `sync` driver they are sent while the post is being saved, which adds
the engine's response time (usually well under a second) to posting. A background queue — for example the database
driver with `php flarum schedule:run` in cron — keeps posting fast.

## Console

```sh
php flarum indexnow:submit --all                        # every discussion a guest can see
php flarum indexnow:submit https://forum.example.com/   # any addresses on the forum's host
```

Addresses are sent in batches of 10,000, the protocol's limit per request.

## Development

```sh
composer install
composer test:setup          # SQLite by default; see DB_* variables of flarum/testing for MySQL or PostgreSQL
composer test
composer analyse:phpstan

cd js
npm ci
npm run build                # commit js/dist together with the sources
```

## По-русски

Расширение для Flarum 2 сообщает поисковикам по протоколу IndexNow о новых, изменённых, скрытых и удалённых темах —
Яндекс, Bing и другие участники протокола переобходят их за минуты. Отправляются только темы, которые видят гости.
Установка: `composer require blenderrus/flarum-indexnow`, затем включить расширение в админке — ключ создаётся сам,
в настройках можно выбрать поисковик (например, Яндекс), включить или выключить виды изменений и посмотреть результат
последней отправки. Уже существующие темы отправляет `php flarum indexnow:submit --all`. Интерфейс переведён на русский.

## Links

- [Packagist](https://packagist.org/packages/blenderrus/flarum-indexnow)
- [GitHub](https://github.com/BlenderRUS/flarum-indexnow)
- [IndexNow documentation](https://www.indexnow.org/documentation)

## License

[MIT](LICENSE) © 2026 Grigoriy Skidan
