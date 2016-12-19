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
            ->addArgument('table', InputArgument::REQUIRED, "model for the table name");
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = $input->getArgument('table');
        $className = Text::camelize($table);
        $db = $this->getConnection($input);
        $schema = Schema::createSchema($db, [$table]);
        $table = $schema->getTable($table);
        $columns = [];
        foreach ($table->getColumns() as $name => $column) {
            $camelcase = Text::camelize($name);
            $columns[] = [
                'varName' => lcfirst($camelcase),
                'varType' => $this->getType($column->getType()),
                'methodName' => $camelcase,
            ];
        }
        include(__DIR__.'/views/model.php');
    }

    private function getType($type)
    {
        $typemap = ['int', 'integer' => 'int', 'string', 'tinyint' => 'int'];
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
