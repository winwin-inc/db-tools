<?php
namespace winwin\db\tools\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Dotenv\Dotenv;
use winwin\db\tools\Db;
use PDO;

/**
 *
 * 环境变量：
 *
 *  - DB_DRIVER  mysql|sqlite
 *  - DB_DSN
 *  - DB_ROOT_DSN
 *  - DB_ROOT_PASS
 *  - DB_PASS
 *  - DB_NAME
 *  - DB_HOST
 *  - MYSQL_PORT_3306_TCP_ADDR
 *  - MYSQL_PORT_3306_TCP_PORT
 *  - MYSQL_ENV_MYSQL_ROOT_PASSWORD
 */
abstract class BaseCommand extends Command
{
    const DEFAULT_HOST = '127.0.0.1';
    const DEFAULT_PORT = 3306;
    const DEFAULT_CHARSET = 'utf8';
    
    /**
     * @var \Doctrine\DBAL\Driver\Connection
     */
    private $connection;

    /**
     * @var boolean
     */
    private $envLoaded;
    
    protected function configure()
    {
        $this->addOption('connection', '-c', InputOption::VALUE_REQUIRED, "Connection dsn");
        $this->addOption('env', '-e', InputOption::VALUE_REQUIRED, 'Environment file');
        $this->addOption('env-prefix', null, InputOption::VALUE_REQUIRED, 'Environment name prefix');
    }

    protected function getEnv($name, $default = null)
    {
        if (array_key_exists($name, $_ENV)) {
            return $_ENV[$name];
        } elseif (array_key_exists($name, $_SERVER)) {
            return $_SERVER[$name];
        } else {
            $value = getenv($name);
            return $value === false ? $default : $value;
        }
    }

    protected function loadEnv(InputInterface $input)
    {
        if ($this->envLoaded) {
            return;
        }
        $envFile = $input->getOption('env');
        if (!$envFile && file_exists('.env')) {
            $envFile = getcwd();
        }
        if ($envFile) {
            if (is_file($envFile)) {
                (new Dotenv(dirname($envFile), basename($envFile)))->load();
            } elseif (is_dir($envFile)) {
                (new Dotenv($envFile))->load();
            } else {
                throw new RuntimeException("");
            }
        }
        $this->envLoaded = true;
    }

    protected function getDsnFromEnv($prefix)
    {
        $dsn = [];
        $dsn['driver'] = strtolower($this->getEnv($prefix.'DB_DRIVER', 'mysql'));
        $dsn['username'] = $this->getEnv($prefix.'DB_USER');
        $dsn['password'] = $this->getEnv($prefix.'DB_PASS');
        $dsn['dbname'] = $this->getEnv($prefix.'DB_NAME');
        if ($dsn['driver'] === 'mysql') {
            $dsn['host'] = $this->getEnv('MYSQL_PORT_3306_TCP_ADDR', $this->getEnv($prefix.'DB_HOST', self::DEFAULT_HOST));
            $dsn['port'] = $this->getEnv('MYSQL_PORT_3306_TCP_PORT', $this->getEnv($prefix.'DB_PORT', self::DEFAULT_PORT));
            $dsn['charset'] = $this->getEnv($prefix.'DB_CHARSET', self::DEFAULT_CHARSET);
            $dsn['unix_socket'] = $this->getEnv($prefix.'DB_SOCKET');
        }
        return $dsn;
    }
    
    protected function getConnection(InputInterface $input)
    {
        if ($this->connection) {
            return $this->connection;
        }
        $dsn = $input->getOption('connection');
        if (empty($dsn)) {
            $this->loadEnv($input);
            $prefix = $input->getOption('env-prefix');
            $dsn = $this->getEnv($prefix.'DB_DSN');
            if (empty($dsn)) {
                $dsn = $this->getDsnFromEnv($prefix);
            }
        }
        return $this->connection = Db::getConnection($dsn);
    }
}
