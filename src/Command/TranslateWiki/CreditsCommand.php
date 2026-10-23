<?php

// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\TranslateWiki;

use App\Service\TranslationCreditsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:translatewiki:credits',
    description: 'List and export the unique contributors who have helped translate the interface',
)]
class CreditsCommand extends Command
{
    public function __construct(
        private readonly TranslationCreditsService $creditsService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: table, markdown, json, csv', 'table')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Save output to a specific file (e.g. TRANSLATORS.md or translators.json)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = strtolower((string) ($input->getOption('format') ?? 'table'));
        $outputFile = $input->getOption('output');

        $translators = $this->creditsService->getTranslators();
        $stats = $this->creditsService->getStats();

        $content = match ($format) {
            'json' => json_encode([
                'stats' => $stats,
                'translators' => array_values($translators),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'markdown', 'md' => $this->generateMarkdown($translators, $stats),
            'csv' => $this->generateCsv($translators),
            default => null,
        };

        if ($content !== null) {
            if ($outputFile) {
                file_put_contents($outputFile, $content);
                $io->success(sprintf('Successfully exported %d translators to %s', count($translators), $outputFile));
            } else {
                $output->writeln($content);
            }
            return Command::SUCCESS;
        }

        // Default: Console Table
        $io->title(sprintf('Pollaris Translators (%d contributors across %d languages)', $stats['translatorsCount'], $stats['languagesCount']));

        $rows = [];
        $i = 1;
        foreach ($translators as $t) {
            $langNames = array_map(static fn(array $l): string => sprintf('%s (%s)', $l['name'], $l['code']), $t['languages']);
            $rows[] = [
                $i++,
                $t['username'],
                implode(', ', $langNames),
                $t['profileUrl'],
            ];
        }

        $table = new Table($output);
        $table
            ->setHeaders(['#', 'Translator', 'Languages', 'Translatewiki Profile'])
            ->setRows($rows);
        $table->render();

        return Command::SUCCESS;
    }

    /**
     * @param array<string, array{username: string, profileUrl: string, languages: array<int, array{code: string, name: string}>}> $translators
     * @param array{translatorsCount: int, languagesCount: int} $stats
     */
    private function generateMarkdown(array $translators, array $stats): string
    {
        $md = "# Pollaris Translation Contributors\n\n";
        $md .= sprintf(
            "Pollaris is translated by **%d contributors** across **%d languages** on [Translatewiki.net](https://translatewiki.net/wiki/Translating:Pollaris).\n\n",
            $stats['translatorsCount'],
            $stats['languagesCount']
        );
        $md .= "| # | Translator | Languages | Profile |\n";
        $md .= "|---|---|---|---|\n";

        $i = 1;
        foreach ($translators as $t) {
            $langs = array_map(static fn(array $l): string => $l['name'], $t['languages']);
            $md .= sprintf(
                "| %d | [%s](%s) | %s | [Profile](%s) |\n",
                $i++,
                $t['username'],
                $t['profileUrl'],
                implode(', ', $langs),
                $t['profileUrl']
            );
        }

        $md .= "\n## Project Acknowledgements\n\n";
        $md .= "- **Pollaris (Original):** Created by [Marien Fressinaud](https://framagit.org/pollaris/pollaris) & Framasoft (Licensed under GNU AGPL v3).\n";
        $md .= "- **Wikimedia Toolforge Edition:** Customised and maintained by [Daniel Yepez Garces](https://github.com/danielyepezgarces).\n";
        $md .= "- **Localization Management:** Hosted and managed by [Translatewiki.net](https://translatewiki.net/wiki/Translating:Pollaris).\n\n";
        $md .= "---\n*Generated automatically from Translatewiki localisation metadata.*\n";
        return $md;
    }

    /**
     * @param array<string, array{username: string, profileUrl: string, languages: array<int, array{code: string, name: string}>}> $translators
     */
    private function generateCsv(array $translators): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, ['username', 'languages', 'profile_url']);

        foreach ($translators as $t) {
            $langs = array_map(static fn(array $l): string => sprintf('%s [%s]', $l['name'], $l['code']), $t['languages']);
            fputcsv($handle, [
                $t['username'],
                implode('; ', $langs),
                $t['profileUrl'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }
}
