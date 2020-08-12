<?php

namespace winwin\db\tools\commands;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use winwin\db\tools\schema\Schema;
use winwin\db\tools\Text;

class GenerateCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName("generate")
            ->setDescription("Generate model of database table")
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'model namespace')
            ->addOption("ads", null, InputOption::VALUE_NONE, "aliyun ads")
            ->addOption("java", null, InputOption::VALUE_NONE, "aliyun ads")
            ->addOption('prefix', '-p', InputOption::VALUE_REQUIRED, 'table prefix')
            ->addOption('output', '-o', InputOption::VALUE_REQUIRED, 'output file name')
            ->addOption('annotation', '-a', null, 'use annotation')
            ->addArgument('table', InputArgument::REQUIRED, "model for the table name");
    }
 
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = $input->getArgument('table');
        $prefix = $input->getOption('prefix');
        $namespace = $input->getOption('namespace');
        $outputFile = $input->getOption('output');
        if (strpos($table, $prefix) === 0) {
            $className = Text::camelCase(substr($table, strlen($prefix)));
        } else {
            $className = Text::camelCase($table);
        }
        $db = $this->getConnection($input);
        $columns = [];
        $isAnnotationEnabled = $input->getOption('annotation');
        foreach ($this->getColumns($db, $table, $input) as $name => $column) {
            $camelcase = Text::camelCase($name);
            $type = $this->getType($column->getType(), $isAnnotationEnabled);
            $columns[] = [
                'name' => $name,
                'dbType' => $column->getType()->getName(),
                'varName' => lcfirst($camelcase),
                'varType' => $type,
                'javaType' => $this->getJavaType($column->getType()),
                'methodName' => $camelcase,
                'paramType' => $type[0] === '\\' ? $type . ' ' : '',
                'isCreatedAt' => $name === 'create_time',
                'isUpdatedAt' => $name === 'update_time',
                'isAutoincrement' => $column->getAutoincrement(),
                'varCast' => in_array($type, ['int', 'string', 'double', 'float'], true) ? "($type) " : ''
            ];
        }

        $context = [
            'className' => $className,
            'namespace' => $namespace,
            'table' => $table,
            'columns' => $columns,
        ];

        if ($outputFile) {
            if (is_dir($outputFile) || $outputFile[strlen($outputFile) - 1] === '/') {
                $outputFile = rtrim($outputFile, '/')."/${className}.php";
            }
            $dir = dirname($outputFile);
            if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException("cannot create directory $dir");
            }
            ob_start();
            $this->render($input, $context);
            file_put_contents($outputFile, ob_get_clean());
            $output->writeln("<info>Write to $outputFile</>");
        } else {
            $this->render($input, $context);
        }
        return 0;
    }

    /**
     * @param Connection $db
     * @param string $table
     * @param InputInterface $input
     * @return Column[]
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    private function getColumns(Connection $db, string $table, InputInterface $input): array
    {
        if ($input->getOption("ads")) {
            $rows = $db->query("desc $table")
                ->fetchAll(\PDO::FETCH_ASSOC);
            $columns = [];
            $adsTypes = [
                'boolean' => Types::BOOLEAN,
                'tinyint' => Types::INTEGER,
                'smallint' => Types::INTEGER,
                'int' => Types::INTEGER,
                'bigint' => Types::INTEGER,
                'double' => Types::FLOAT,
                'float' => Types::FLOAT,
                'date' => Types::DATE_MUTABLE,
                'timestamp' => Types::DATETIME_MUTABLE,
            ];
            foreach ($rows as $row) {
                if (isset($adsTypes[$row['DATA_TYPE']])) {
                    $type = Type::getType($adsTypes[$row['DATA_TYPE']]);
                } else {
                    $type = Type::getType("string");
                }
                $columns[$row['COLUMN_NAME']] = new Column($row['COLUMN_NAME'], $type);
            }
            return $columns;
        } else {
            $schema = Schema::createSchema($db, [$table]);
            $table = $schema->getTable($table);
            return $table->getColumns();
        }
    }

    private function render(InputInterface $input, array $context): void
    {
        if ($input->getOption("ads")) {
            $page = __DIR__ .'/views/ads-model.php';
        } elseif ($input->getOption("java")) {
            $page = __DIR__ .'/views/jpa-model.php';
        } elseif ($input->getOption('annotation')) {
            $page = __DIR__.'/views/annotated-model.php';
        } else {
            $page = __DIR__.'/views/model.php';
        }
        extract($context);
        include($page);
    }

    private function getType(Type $type, bool $isAnnotationEnabled): string
    {
        $typeMap = [
            'integer' => 'int',
            'bigint' => 'int',
            'string',
            'tinyint' => 'bool',
            'float',
            'double'
        ];
        if ($isAnnotationEnabled) {
            $typeMap = array_merge($typeMap, [
                'datetime' => '\DateTime',
                'time' => '\DateTime',
                'date' => '\DateTime',
            ]);
        }
        $typeName = $type->getName();
        if (in_array($typeName, $typeMap, true)) {
            return $typeName;
        } elseif (array_key_exists($typeName, $typeMap)) {
            return $typeMap[$typeName];
        } else {
            return 'string';
        }
    }

    private function getJavaType(Type $type): string
    {
        $typeMap = [
            'integer' => 'int',
            'bigint' => 'int',
            'string' => 'String',
            'tinyint' => 'bool',
            'float' => 'double',
            'double' => 'double',
            'datetime' => 'java.util.Date',
            'time' => 'java.util.Date',
            'date' => 'java.util.Date',
        ];
        $typeName = $type->getName();
        if (in_array($typeName, $typeMap, true)) {
            return $type;
        } elseif (array_key_exists($typeName, $typeMap)) {
            return $typeMap[$typeName];
        } else {
            return 'String';
        }
    }
}
