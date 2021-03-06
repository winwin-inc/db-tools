<?php
namespace winwin\db\tools\commands;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use winwin\db\tools\Db;

class CreateCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('create')
            ->setDescription("Create database and schema")
            ->addOption('root-connection', null, InputOption::VALUE_REQUIRED, "Connection dsn for root");
    }

    protected function getRootConnection(InputInterface $input): Connection
    {
        $dsn = $input->getOption('root-connection');
        if (empty($dsn)) {
            $this->loadEnv($input);
            $prefix = $input->getOption('env-prefix');
            $dsn = $this->getEnv($prefix.'DB_ROOT_DSN');
            if (empty($dsn)) {
                $dsn = $this->getDsnFromEnv($prefix);
                $dsn['username'] = 'root';
                $dsn['password'] = $this->getEnv('MYSQL_ENV_MYSQL_ROOT_PASSWORD', $this->getEnv($prefix.'DB_ROOT_PASS', ''));
                unset($dsn['dbname']);
            }
        }
        return Db::getConnection($dsn);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $db = $this->getRootConnection($input);
        $prefix = $input->getOption('env-prefix');
        $dsn = $this->getEnv($prefix.'DB_DSN');
        if (!empty($dsn)) {
            $url = parse_url($dsn);
            $dbname = ltrim($dsn, $url['path']);
            $username = $url['user'] ?? null;
            $password = $url['pass'] ?? null;
            parse_str($url['query'] ?? '', $query);
            $charset = $query['charset'] ?? null;
        } else {
            $dsn = $this->getDsnFromEnv($prefix);
            $dbname = $dsn['dbname'];
            $username = $dsn['username'];
            $password = $dsn['password'];
            $charset = $dsn['charset'];
        }
        if (in_array($dbname, $db->getSchemaManager()->listDatabases(), true)) {
            $output->writeln("<info>Database $dbname already exists</>");
            return 0;
        }
        $sqls = [
            sprintf("CREATE database IF NOT EXISTS $dbname%s;", $charset ? ' DEFAULT CHARACTER SET ' . $charset : ''),
            "CREATE USER $username IDENTIFIED WITH mysql_native_password BY '$password';",
            "GRANT ALL ON $dbname.* TO $username@'%';",
            "FLUSH PRIVILEGES;"
        ];
        if ($input->isInteractive()) {
            $helper = $this->getHelper("question");
            $question = new ConfirmationQuestion(implode("\n", $sqls) . "\nContinue execute above sql? (y/n) [n] ", false);
            if (!$helper->ask($input, $output, $question)) {
                return 0;
            }
        }
        foreach ($sqls as $sql) {
            $db->executeUpdate($sql);
        }
        return 0;
    }
}
