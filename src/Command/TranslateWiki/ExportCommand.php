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
    name: 'app:translatewiki:export',
    description: 'Export Symfony YAML translations to JSON for Translatewiki',
)]
class ExportCommand extends Command
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
        $exportDir = $this->projectDir . '/translatewiki';

        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        $files = glob($translationsDir . '/*+intl-icu.*.yaml');
        $count = 0;

        foreach ($files as $file) {
            $filename = basename($file);
            if (!preg_match('/^([a-zA-Z0-9_-]+)\+intl-icu\.([a-zA-Z0-9_-]+)\.yaml$/', $filename, $matches)) {
                continue;
            }

            $domain = $matches[1];
            $locale = $matches[2];

            $yamlContent = Yaml::parseFile($file);
            
            // Convert to JSON
            $jsonContent = json_encode($yamlContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $exportPath = $exportDir . '/' . $domain . '.' . $locale . '.json';
            file_put_contents($exportPath, $jsonContent);
            
            $io->writeln(sprintf('Exported %s to %s', $filename, basename($exportPath)));
            $count++;
        }

        $io->success(sprintf('Successfully exported %d translation files.', $count));

        return Command::SUCCESS;
    }
}
