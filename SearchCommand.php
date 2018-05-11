<?php

namespace AppBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Command\DoctrineCommand;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SearchIndexBuildCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('search:index:build');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $model = $this->getContainer()->get('searchService');
        
        $output->writeln('Removing existing index.');
        $model->clearIndex();

        $output->writeln(sprintf('Start indexing website <info>%s</info>.', $model->getHost()));
        $output->writeln('Site will be downloaded with command:');
        $output->writeln(sprintf('<info>%s</info>.', $model->getWgetCommand()));
        $model->buildIndex();

        $output->writeln(sprintf('Pages of <info>%s</info> was downloaded and processed.', $model->getHost()));

        $output->writeln('Done.');
    }
}

