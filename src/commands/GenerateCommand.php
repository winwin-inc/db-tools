<?php

namespace winwin\db\tools\commands;

use winwin\db\tools\schema\Schema;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use kuiper\helper\DataDumper;
use kuiper\helper\Text;
use RuntimeException;
use InvalidArgumentException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class GenerateCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName("generate")
            ->setDescription("Generate model of database table")
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'model namespace')
            ->addOption('prefix', '-p', InputOption::VALUE_REQUIRED, 'table prefix')
            ->addOption('output', '-o', InputOption::VALUE_REQUIRED, 'output file name')
            ->addOption('annotation', '-a', null, 'use annotation')
            ->addArgument('table', InputArgument::REQUIRED, "model for the table name");
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = $input->getArgument('table');
        $prefix = $input->getOption('prefix');
        $namespace = $input->getOption('namespace');
        $outputfile = $input->getOption('output');
        if (Text::startsWith($table, $prefix)) {
            $className = Text::camelize(substr($table, strlen($prefix)));
        } else {
            $className = Text::camelize($table);
        }
        $db = $this->getConnection($input);
        $schema = Schema::createSchema($db, [$table]);
        $table = $schema->getTable($table);
        $columns = [];
        $isAnnotationEnabled = $input->getOption('annotation');
        foreach ($table->getColumns() as $name => $column) {
            $camelcase = Text::camelize($name);
            $type = $this->getType($column->getType(), $isAnnotationEnabled);
            $columns[] = [
                'varName' => lcfirst($camelcase),
                'varType' => $type,
                'methodName' => $camelcase,
                'paramType' => $type[0] == '\\' ? $type . ' ' : '',
                'isCreatedAt' => $name == 'create_time',
                'isUpdatedAt' => $name == 'update_time',
                'isAutoincrement' => $column->getAutoincrement(),
            ];
        }

        $context = [
            'className' => $className,
            'namespace' => $namespace,
            'columns' => $columns,
        ];

        if ($outputfile) {
            if (is_dir($outputfile) || Text::endsWith($outputfile, '/')) {
                $outputfile = rtrim($outputfile, '/')."/${className}.php";
            }
            $dir = dirname($outputfile);
            if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
                throw new \RuntimeException("cannot create directory $dir");
            }
            ob_start();
            $this->render($input, $context);
            file_put_contents($outputfile, ob_get_clean());
            $output->writeln("<info>Write to $outputfile</>");
        } else {
            $this->render($input, $context);
        }
    }

    private function render(InputInterface $input, array $context)
    {
        if ($input->getOption('annotation')) {
            $page = __DIR__.'/views/annotated-model.php';
        } else {
            $page = __DIR__.'/views/model.php';
        }
        extract($context);
        include($page);
    }

    private function getType($type, $isAnnotationEnabled)
    {
        $typemap = [
            'int',
            'integer' => 'int',
            'string',
            'tinyint' => 'int',
        ];
        if ($isAnnotationEnabled) {
            $typemap = array_merge($typemap, [
                'datetime' => '\DateTime',
                'time' => '\DateTime',
                'date' => '\DateTime',
            ]);
        }
        $type = $type->getName();
        if (in_array($type, $typemap)) {
            return $type;
        } elseif (array_key_exists($type, $typemap)) {
            return $typemap[$type];
        } else {
            return 'string';
        }
    }
}
