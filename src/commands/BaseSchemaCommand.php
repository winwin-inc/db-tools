<?php
namespace winwin\db\tools\commands;

use Doctrine\DBAL\Connection;
use winwin\db\tools\DataDumper;
use winwin\db\tools\schema\Schema;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use RuntimeException;
use SqlFormatter;

abstract class BaseSchemaCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('engine', null, InputOption::VALUE_REQUIRED, "Table storage engine");
        $this->addArgument('tables', InputArgument::IS_ARRAY, "Table names");
    }

    protected function formatSql(array $sqls): string
    {
        return implode(";\n", array_map(static function ($sql): string {
            return SqlFormatter::format($sql, false);
        }, $sqls)) . ";\n";
    }

    /**
     * @param InputInterface $input
     * @param string $source
     * @param string[]|null $tables
     * @return \Doctrine\DBAL\Schema\Schema
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function createSchema(InputInterface $input, ?string $source, ?array $tables = null): \Doctrine\DBAL\Schema\Schema
    {
        if ($tables === null) {
            $tables = $input->getArgument('tables');
        }
        if (isset($source)) {
            $data = $this->loadDefinitionsFromFile($input, $source);
            return Schema::createSchema($data, $tables);
        } else {
            return Schema::createSchema($this->getConnection($input), $tables);
        }
    }

    /**
     * @param InputInterface $input
     * @param string $file
     * @return array
     */
    protected function loadDefinitionsFromFile(InputInterface $input, string $file): array
    {
        $data = DataDumper::loadFile($file);
        if (!is_array($data)) {
            throw new RuntimeException("Data in file $file is invalid");
        }
        if ($input->getOption('engine')) {
            $engine = $input->getOption('engine');
            foreach ($data as $table => &$defs) {
                if (empty($defs['options']['engine'])) {
                    $defs['options']['engine'] = $engine;
                }
            }
        }
        return $data;
    }
}
