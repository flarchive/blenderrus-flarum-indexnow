<?php

/*
 * This file is part of blenderrus/flarum-indexnow.
 *
 * Copyright (c) 2026 Grigoriy Skidan.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BlenderRUS\IndexNow\Tests\unit;

use BlenderRUS\IndexNow\IndexNow;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IndexNowTest extends TestCase
{
    /** @param array<string, string|null> $settings */
    private function indexNow(array $settings): IndexNow
    {
        $repository = Mockery::mock(SettingsRepositoryInterface::class);
        $repository->shouldReceive('get')->andReturnUsing(fn (string $key) => $settings[$key] ?? null);

        return new IndexNow($repository, Mockery::mock(UrlGenerator::class), Mockery::mock(SlugManager::class), new Client());
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    /** @return array<string, array{string|null, bool}> */
    public static function keys(): array
    {
        return [
            'hex' => ['0123456789abcdef0123456789abcdef', true],
            'letters, digits and dashes' => ['My-Key-2026', true],
            'eight characters' => ['abcdefgh', true],
            'too short' => ['abcdefg', false],
            'too long' => [str_repeat('a', 129), false],
            'other characters' => ['abc_def.ghi', false],
            'surrounding spaces are ignored' => ['  abcdefgh  ', true],
            'empty' => ['', false],
            'missing' => [null, false],
        ];
    }

    #[Test]
    #[DataProvider('keys')]
    public function submissions_are_on_only_with_a_valid_key(?string $key, bool $enabled): void
    {
        $this->assertSame($enabled, $this->indexNow(['blenderrus-indexnow.key' => $key])->isEnabled());
    }

    #[Test]
    public function each_kind_of_change_has_its_own_switch(): void
    {
        $indexNow = $this->indexNow([
            'blenderrus-indexnow.key' => 'abcdefgh',
            'blenderrus-indexnow.submit_replies' => '1',
            'blenderrus-indexnow.submit_edits' => '0',
        ]);

        $this->assertTrue($indexNow->submits('replies'));
        $this->assertFalse($indexNow->submits('edits'));
        $this->assertFalse($this->indexNow(['blenderrus-indexnow.submit_replies' => '1'])->submits('replies'), 'no key, nothing is submitted');
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function endpoints(): array
    {
        return [
            'default' => [[], IndexNow::DEFAULT_ENDPOINT],
            'preset' => [['blenderrus-indexnow.endpoint' => 'https://yandex.com/indexnow'], 'https://yandex.com/indexnow'],
            'custom' => [['blenderrus-indexnow.endpoint' => 'custom', 'blenderrus-indexnow.custom_endpoint' => ' https://example.com/indexnow '], 'https://example.com/indexnow'],
            'custom without an address' => [['blenderrus-indexnow.endpoint' => 'custom'], IndexNow::DEFAULT_ENDPOINT],
        ];
    }

    /** @param array<string, string> $settings */
    #[Test]
    #[DataProvider('endpoints')]
    public function the_endpoint_comes_from_the_settings(array $settings, string $expected): void
    {
        $this->assertSame($expected, $this->indexNow($settings)->endpoint());
    }
}
