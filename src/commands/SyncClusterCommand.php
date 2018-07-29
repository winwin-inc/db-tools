<?php

namespace winwin\db\tools\commands;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use PDOException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use winwin\db\tools\Db;
use winwin\db\tools\schema\PatternMatcher;
use winwin\db\tools\schema\Schema;
use winwin\db\tools\schema\Table;

class SyncClusterCommand extends BaseSchemaCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('sync:cluster')
            ->setDescription("Update database cluster schema")
            ->addOption('target', '-t', InputOption::VALUE_REQUIRED, "Database schema diff to");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $target = $input->getOption('target');
        $tables = $input->getArgument('tables');
        if (!$target) {
            throw new \InvalidArgumentException("The '--target' option is required");
        }

        $pattern = new PatternMatcher();
        $definitions = $this->loadDefinitionsFromFile($input, $target);
        foreach ($definitions as $name => $table) {
            $pattern->add('#^' . $name . '$#', $table);
            if ((empty($tables) || in_array($table, $tables)) && isset($table['options']["pattern"])) {
                $pattern->add('#^' . $table['options']["pattern"] .'$#', $table);
            }
        }

        foreach ($this->getClusterConnections($input) as $db) {
            $currentSchema = Schema::createSchema($db, $pattern);
            $toSchema = new \Doctrine\DBAL\Schema\Schema();
            foreach ($definitions as $name => $definition) {
                Table::fromArray($toSchema->createTable($name), $definition);
            }
            foreach ($currentSchema->getTables() as $table) {
                if (!$toSchema->hasTable($table->getName()) && $pattern->match($table->getName())) {
                    Table::fromArray($toSchema->createTable($table->getName()), $pattern->get($table->getName()));
                }
            }
            $comparator = new Comparator();
            $diff = $comparator->compare($currentSchema, $toSchema);
            $sqls = Schema::toSql($diff, $db);

            if (empty($sqls)) {
                $output->writeln("<info>No changes</>");
                continue;
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

    /**
     * @param InputInterface $input
     * @return Connection[]
     */
    private function getClusterConnections($input)
    {
        $this->loadEnv($input);
        $prefix = $input->getOption('env-prefix');
        foreach (range(1, 1000) as $index) {
            $dsn = $this->getEnv($prefix . 'DB'.$index.'_DSN');
            if (empty($dsn)) {
                $host = $this->getEnv($prefix. 'DB' . $index . '_HOST');
                if (empty($host)) {
                    return [];
                }

                $dsn = [];
                $dsn["driver"] = strtolower($this->getEnv($prefix."DB_DRIVER", "mysql"));
                $dsn["username"] = $this->getEnv($prefix."DB{$index}_USER", $this->getEnv($prefix."DB_USER"));
                $dsn["password"] = $this->getEnv($prefix."DB{$index}_PASS", $this->getEnv($prefix."DB_PASS"));
                $dsn["dbname"] = $this->getEnv($prefix."DB{$index}_NAME", $this->getEnv($prefix."DB_NAME"));
                if ($dsn["driver"] === "mysql") {
                    $dsn["host"] = $host;
                    $dsn["port"] = $this->getEnv($prefix."DB{$index}_PORT", self::DEFAULT_PORT);
                    $dsn["charset"] = $this->getEnv($prefix."DB_CHARSET", self::DEFAULT_CHARSET);
                    $dsn["unix_socket"] = $this->getEnv($prefix."DB{$index}_SOCKET", $this->getEnv($prefix."DB_SOCKET"));
                }
            }
            yield Db::getConnection($dsn);
        }
    }
}
