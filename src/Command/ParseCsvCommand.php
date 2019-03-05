<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ParseCsvCommand extends Command
{
    protected static $defaultName = 'app:parse-csv';

    protected function configure()
    {
        $this
            ->setDescription('This script reads the CSV file, parse the contents and then insert the data into a MySQL database table')
            ->addArgument('test', InputArgument::OPTIONAL, 'This will perform everything the normal import does, but not insert the data into the database')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $testMode = $input->getArgument('test');

        if ($testMode) {
            $io->note(sprintf('You passed an argument: %s', $testMode));
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');
    }
}
