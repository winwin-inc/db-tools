<?php
namespace winwin\db\tools\commands;

use winwin\db\tools\schema\Schema;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use kuiper\helper\DataDumper;
use RuntimeException;
use SqlFormatter;

abstract class BaseSchemaCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this->addOption('engine', null, InputOption::VALUE_REQUIRED, "Table storage engine");
        $this->addArgument('tables', InputArgument::IS_ARRAY, "Table names");
    }

    protected function formatSql(array $sqls)
    {
        return implode(";\n", array_map(function ($sql) {
            return SqlFormatter::format($sql, false);
        }, $sqls)) . ";\n";
    }

    protected function createSchema(InputInterface $input, $source, $tables = null)
    {
        if ($tables === null) {
            $tables = $input->getArgument('tables');
        }
        if (isset($source)) {
            $data = DataDumper::loadFile($source);
            if (!is_array($data)) {
                throw new RuntimeException("Data in file $source is invalid");
            }
            if ($input->getOption('engine')) {
                $engine = $input->getOption('engine');
                foreach ($data as $table => &$defs) {
                    if (empty($defs['options']['engine'])) {
                        $defs['options']['engine'] = $engine;
                    }
                }
            }
            return Schema::createSchema($data, $tables);
        } else {
            return Schema::createSchema($this->getConnection($input), $tables);
        }
    }
}
