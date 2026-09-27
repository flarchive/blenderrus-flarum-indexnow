<?php

/*
 * This file is part of blenderrus/flarum-indexnow.
 *
 * Copyright (c) 2026 Grigoriy Skidan.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BlenderRUS\IndexNow\Console;

use BlenderRUS\IndexNow\IndexNow;
use Flarum\Console\AbstractCommand;
use Flarum\Discussion\Discussion;
use Flarum\User\Guest;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * php flarum indexnow:submit --all | <url>...
 */
class SubmitCommand extends AbstractCommand
{
    public function __construct(
        protected IndexNow $indexNow,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('indexnow:submit')
            ->setDescription('Submit forum addresses to IndexNow right away')
            ->addArgument('urls', InputArgument::IS_ARRAY, 'Addresses to submit')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Submit every discussion a guest can see');
    }

    protected function fire(): int
    {
        if (! $this->indexNow->isEnabled()) {
            $this->error('Set a valid IndexNow key in the extension settings first.');

            return self::FAILURE;
        }
        $urls = $this->input->getArgument('urls');
        if ($this->input->getOption('all')) {
            $query = Discussion::whereVisibleTo(new Guest())->orderBy('id');
            foreach ($query->lazy() as $discussion) {
                $urls[] = $this->indexNow->discussionUrl($discussion);
            }
        }
        if ([] === $urls) {
            $this->error('Nothing to submit: give addresses or --all.');

            return self::FAILURE;
        }

        foreach (array_chunk($urls, IndexNow::MAX_URLS) as $batch) {
            try {
                $status = $this->indexNow->submit($batch);
            } catch (GuzzleException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->info(sprintf('%d address(es) sent to %s: HTTP %d', count($batch), $this->indexNow->endpoint(), $status));
        }

        return self::SUCCESS;
    }
}
