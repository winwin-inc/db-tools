<?php
namespace winwin\db\tools\commands;

use winwin\db\tools\schema\Schema;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use kuiper\helper\DataDumper;
use RuntimeException;
use SqlFormatter;

abstract class BaseSchemaCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this->addArgument('tables', InputArgument::IS_ARRAY, "Table names");
    }

    protected function formatSql(array $sqls)
    {
        return implode(";\n", array_map(function ($sql) {
            return SqlFormatter::format($sql, false);
        }, $sqls)) . ";\n";
    }

    protected function createSchema(InputInterface $input, $source)
    {
        $tables = $input->getArgument('tables');
        if (isset($source)) {
            $data = DataDumper::loadFile($source);
            if (!is_array($data)) {
                throw new RuntimeException("Data in file $source is invalid");
            }
            return Schema::createSchema($data, $tables);
        } else {
            return Schema::createSchema($this->getConnection($input), $tables);
        }
    }
}
