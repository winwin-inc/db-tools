<?php
namespace winwin\db\tools\commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use winwin\db\tools\DataDumper;
use winwin\db\tools\schema\Schema;

class SchemaCommand extends BaseSchemaCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('schema')
            ->setDescription("Exports database schema")
            ->addOption('source', '-s', InputOption::VALUE_REQUIRED, "Database schema source")
            ->addOption('format', '-f', InputOption::VALUE_REQUIRED, "Output format, support sql|yaml|json|php", 'yaml');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $source = $input->getOption('source');
        $format = $input->getOption('format');
        $schema = $this->createSchema($input, $source);
        if ($format === "sql") {
            echo $this->formatSql(Schema::toSql($schema, $this->getConnection($input)));
        } elseif ($format === 'columns') {
            echo DataDumper::dump(array_map(function($table) {
                return array_keys($table['columns']);
            }, Schema::toArray($schema)), 'json');
        } else {
            echo DataDumper::dump(Schema::toArray($schema), $format);
        }
    }
}
