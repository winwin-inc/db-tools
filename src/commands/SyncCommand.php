<?php

declare(strict_types=1);

namespace winwin\db\tools\commands;

use Doctrine\DBAL\Schema\Comparator;
use PDOException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use winwin\db\tools\schema\Schema;

class SyncCommand extends BaseSchemaCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('sync')
            ->setDescription('Update database schema')
            ->addOption('purge', null, InputOption::VALUE_NONE, 'Remove not present tables')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Exit abnormal if changed')
            ->addOption('target', '-t', InputOption::VALUE_REQUIRED, 'Database schema diff to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $input->getOption('target');
        $tables = $input->getArgument('tables');
        $purge = $input->getOption('purge');
        if (empty($target)) {
            throw new \InvalidArgumentException("The '--target' option is required");
        }
        $toSchema = $this->createSchema($input, $target);
        if (!$purge && empty($tables)) {
            $tables = array_map(static function ($table): string {
                return $table->getName();
            }, $toSchema->getTables());
        }
        $currentSchema = $this->createSchema($input, null, $tables);
        $comparator = new Comparator();
        $diff = $comparator->compare($currentSchema, $toSchema);
        $sqls = Schema::toSql($diff, $db = $this->getConnection($input));

        if (empty($sqls)) {
            $output->writeln('<info>No changes</info>');

            return 0;
        }
        $sql = $this->formatSql($sqls);
        if ($input->getOption('check')) {
            $output->writeln("Sql to change: \n".$sql);

            return 1;
        }
        if ($input->isInteractive()) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion("{$sql}\nContinue execute above sql? (y/n) [n] ", false);
            if (!$helper->ask($input, $output, $question)) {
                return 0;
            }
        }
        foreach ($sqls as $stmt) {
            try {
                $db->executeUpdate($stmt);
                $errorInfo = $db->errorInfo();
                if (isset($errorInfo[1])) {
                    $output->writeln(sprintf("<error>Fail to execute '%s': %s</error>", $stmt, json_encode($errorInfo)));
                }
            } catch (PDOException $e) {
                $output->writeln(sprintf("<error>Fail to execute '%s': %s</error>", $stmt, $e->getMessage().' '.json_encode($e->errorInfo)));
            }
        }

        return 0;
    }
}
