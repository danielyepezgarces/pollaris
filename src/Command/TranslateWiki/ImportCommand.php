<?php

// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command\TranslateWiki;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:translatewiki:import',
    description: 'Import JSON translations from Translatewiki to Symfony YAML',
)]
class ImportCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $translationsDir = $this->projectDir . '/translations';
        $importDir = $this->projectDir . '/translatewiki';

        if (!is_dir($importDir)) {
            $io->error('The translatewiki directory does not exist. Run app:translatewiki:export first.');
            return Command::FAILURE;
        }

        $files = glob($importDir . '/*.json');
        $count = 0;

        foreach ($files as $file) {
            $filename = basename($file);
            if (!preg_match('/^([a-zA-Z0-9_-]+)\.([a-zA-Z0-9_-]+)\.json$/', $filename, $matches)) {
                continue;
            }

            $domain = $matches[1];
            $locale = $matches[2];

            $jsonContent = file_get_contents($file);
            $data = json_decode($jsonContent, true);

            if (!is_array($data)) {
                $io->warning(sprintf('Skipping %s: Invalid JSON.', $filename));
                continue;
            }

            $yamlContent = Yaml::dump($data, 4, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

            $importPath = $translationsDir . '/' . $domain . '+intl-icu.' . $locale . '.yaml';
            file_put_contents($importPath, $yamlContent);
            
            $io->writeln(sprintf('Imported %s to %s', $filename, basename($importPath)));
            $count++;
        }

        $io->success(sprintf('Successfully imported %d translation files.', $count));

        return Command::SUCCESS;
    }
}
