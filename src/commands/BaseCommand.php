<?php
namespace winwin\db\tools\commands;

use Doctrine\DBAL\Connection;
use Dotenv\Dotenv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use winwin\db\tools\Db;

/**
 *
 * 环境变量：
 *
 *  - DB_DRIVER  mysql|sqlite
 *  - DB_DSN
 *  - DB_ROOT_DSN
 *  - DB_ROOT_PASS
 *  - DB_PASS | DB_PASSWORD
 *  - DB_NAME | DB_DATABASE
 *  - DB_USER | DB_USERNAME
 *  - DB_HOST
 *  - DB_PORT
 *  - MYSQL_PORT_3306_TCP_ADDR
 *  - MYSQL_PORT_3306_TCP_PORT
 *  - MYSQL_ENV_MYSQL_ROOT_PASSWORD
 */
abstract class BaseCommand extends Command
{
    public const DEFAULT_HOST = '127.0.0.1';
    public const DEFAULT_PORT = 3306;
    public const DEFAULT_CHARSET = 'utf8';
    
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var boolean
     */
    private $envLoaded;
    
    protected function configure(): void
    {
        $this->addOption('connection', '-c', InputOption::VALUE_REQUIRED, "Connection dsn");
        $this->addOption('env', '-e', InputOption::VALUE_REQUIRED, 'Environment file');
        $this->addOption('env-prefix', null, InputOption::VALUE_REQUIRED, 'Environment name prefix');
    }

    protected function getEnv(string $name, ?string $default = null): ?string
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

    protected function loadEnv(InputInterface $input): void
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
                Dotenv::createImmutable(dirname($envFile), basename($envFile))->load();
            } elseif (is_dir($envFile)) {
                Dotenv::createImmutable($envFile)->safeLoad();
            } else {
                throw new \RuntimeException("Cannot load env: file $envFile does not exist");
            }
        }
        $this->envLoaded = true;
    }

    protected function getDsnFromEnv(?string $prefix): array
    {
        $dsn = [];
        $dsn['driver'] = strtolower($this->getEnv($prefix.'DB_DRIVER', 'mysql'));
        $dsn['username'] = $this->getEnv($prefix.'DB_USERNAME', $this->getEnv($prefix.'DB_USER'));
        $dsn['password'] = $this->getEnv($prefix.'DB_PASSWORD', $this->getEnv($prefix.'DB_PASS'));
        $dsn['dbname'] = $this->getEnv($prefix.'DB_DATABASE', $this->getEnv($prefix.'DB_NAME'));
        if ($dsn['driver'] === 'mysql') {
            $dsn['host'] = $this->getEnv('MYSQL_PORT_3306_TCP_ADDR', $this->getEnv($prefix.'DB_HOST', self::DEFAULT_HOST));
            $dsn['port'] = (int) $this->getEnv('MYSQL_PORT_3306_TCP_PORT', $this->getEnv($prefix.'DB_PORT', (string) self::DEFAULT_PORT));
            $dsn['charset'] = $this->getEnv($prefix.'DB_CHARSET', self::DEFAULT_CHARSET);
            $dsn['unix_socket'] = $this->getEnv($prefix.'DB_SOCKET');
        }
        return $dsn;
    }
    
    protected function getConnection(InputInterface $input): Connection
    {
        if ($this->connection !== null) {
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
