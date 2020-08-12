<?php
namespace winwin\db\tools\commands;

use Doctrine\DBAL\Schema\Comparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use winwin\db\tools\schema\Schema;

class DiffCommand extends BaseSchemaCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('diff')
            ->setDescription("Show differences between source with target")
            ->addOption('source', '-s', InputOption::VALUE_REQUIRED, "Database schema diff from")
            ->addOption('target', '-t', InputOption::VALUE_REQUIRED, "Database schema diff to");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $this->createSchema($input, $input->getOption('source'));
        $target = $this->createSchema($input, $input->getOption('target'));
        $comparator = new Comparator();
        $diff = $comparator->compare($source, $target);
        echo $this->formatSql(Schema::toSql($diff, $this->getConnection($input)));

        return 0;
    }
}
