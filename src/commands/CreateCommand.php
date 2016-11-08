<?php
namespace winwin\db\tools\commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use winwin\db\tools\schema\Schema;
use winwin\db\tools\Db;
use kuiper\helper\DataDumper;
use kuiper\helper\Arrays;
use Doctrine\DBAL\Schema\Comparator;
use SqlFormatter;
use PDOException;

class CreateCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('create')
            ->setDescription("Create database and schema")
            ->addOption('root-connection', null, InputOption::VALUE_REQUIRED, "Connection dsn for root");
    }

    protected function getRootConnection(InputInterface $input)
    {
        $dsn = $input->getOption('root-connection');
        if (empty($dsn)) {
            $this->loadEnv($input);
            $prefix = $input->getOption('env-prefix');
            $dsn = $this->getEnv($prefix.'DB_ROOT_DSN');
            if (empty($dsn)) {
                $dsn = [];
                $dsn['driver'] = strtolower($this->getEnv($prefix.'DB_DRIVER', 'mysql'));
                $dsn['username'] = 'root';
                $dsn['password'] = $this->getEnv('MYSQL_ENV_MYSQL_ROOT_PASSWORD', $this->getEnv($prefix.'DB_ROOT_PASS', ''));
                
                if ($dsn['driver'] === 'mysql') {
                    $dsn['host'] = $this->getEnv('MYSQL_PORT_3306_TCP_ADDR', $this->getEnv($prefix.'DB_HOST', 'localhost'));
                    $dsn['port'] = $this->getEnv('MYSQL_PORT_3306_TCP_PORT', $this->getEnv($prefix.'DB_PORT', 3306));
                    $dsn['charset'] = $this->getEnv($prefix.'DB_CHARSET');
                    $dsn['unix_socket'] = $this->getEnv($prefix.'DB_SOCKET');
                }
            }
        }
        return Db::getConnection($dsn);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $db = $this->getRootConnection($input);
        $prefix = $input->getOption('env-prefix');
        $dsn = $this->getEnv($prefix.'DB_DSN');
        if ($dsn) {
            $url = parse_url($dsn);
            $dbname = ltrim($dsn, $url['path']);
            $username = Arrays::fetch($url, 'user');
            $password = Arrays::fetch($url, 'pass');
            parse_str(Arrays::fetch($url, 'query'), $query);
            $charset = Arrays::fetch($query, 'charset');
        } else {
            $dbname = $this->getEnv($prefix.'DB_NAME');
            $username = $this->getEnv($prefix.'DB_USER');
            $password = $this->getEnv($prefix.'DB_PASS');
            $charset = $this->getEnv($prefix.'DB_CHARSET');
        }
        if (in_array($dbname, $db->getSchemaManager()->listDatabases())) {
            $output->writeln("<info>Database $dbname already exists</>");
            return;
        }
        $sqls = [
            sprintf("CREATE database IF NOT EXISTS $dbname%s;", $charset ? ' DEFAULT CHARACTER SET utf8' : ''),
            "GRANT ALL ON $dbname.* TO $username@'%' IDENTIFIED BY '$password';",
            "GRANT ALL ON $dbname.* TO $username@'localhost' IDENTIFIED BY '$password';",
            "FLUSH PRIVILEGES;"
        ];
        if ($input->isInteractive()) {
            $helper = $this->getHelper("question");
            $question = new ConfirmationQuestion(implode("\n", $sqls) . "\nContinue execute above sql? (y/n) [n] ", false);
            if (!$helper->ask($input, $output, $question)) {
                return;
            }
        }
        foreach ($sqls as $sql) {
            $db->executeUpdate($sql);
        }
    }
}
