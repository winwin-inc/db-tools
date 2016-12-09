<?php
namespace winwin\db\tools\commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use winwin\db\tools\schema\Schema;
use kuiper\helper\DataDumper;
use Doctrine\DBAL\Schema\Comparator;
use SqlFormatter;
use PDOException;

class SyncCommand extends BaseSchemaCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('sync')
            ->setDescription("Update database schema")
            ->addOption('purge', null, InputOption::VALUE_NONE, "Remove not present tables")
            ->addOption('target', '-t', InputOption::VALUE_REQUIRED, "Database schema diff to");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $target = $input->getOption('target');
        $tables = $input->getArgument('tables');
        $purge = $input->getOption('purge');
        if (!$target) {
            throw new \InvalidArgumentException("The '--target' option is required");
        }
        $toSchema = $this->createSchema($input, $target);
        if (!$purge && empty($tables)) {
            $tables = array_map(function ($table) {
                return $table->getName();
            }, $toSchema->getTables());
        }
        $currentSchema = $this->createSchema($input, null, $tables);
        $comparator = new Comparator();
        $diff = $comparator->compare($currentSchema, $toSchema);
        $sqls = Schema::toSql($diff, $db = $this->getConnection($input));
        
        if (empty($sqls)) {
            $output->writeln("<info>No changes</>");
            return;
        } 
        $sql = $this->formatSql($sqls);
        if ($input->isInteractive()) {
            $helper = $this->getHelper("question");
            $question = new ConfirmationQuestion("{$sql}\nContinue execute above sql? (y/n) [n] ", false);
            if (!$helper->ask($input, $output, $question)) {
                return;
            }
        }
        foreach ($sqls as $sql) {
            try {
                $db->executeUpdate($sql);
                $errorInfo = $db->errorInfo();
                if ($errorInfo && isset($errorInfo[1])) {
                    $output->writeln(sprintf("<error>Fail to execute '%s': %s</>", $sql, json_encode($errorInfo)));
                }
            } catch (PDOException $e) {
                $output->writeln(sprintf("<error>Fail to execute '%s': %s</>", $sql, $e->getMessage() . " " . json_encode($e->errorInfo())));
            }
        }
    }
}
