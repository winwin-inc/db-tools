<?php
namespace winwin\db\tools;

use Doctrine\DBAL\DriverManager;
use InvalidArgumentException;
use PDO;

abstract class Db
{
    /**
     * creates database connect by dsn
     *
     * @param string|array|PDO $dsn
     * @return \Doctrine\DBAL\Connection
     */
    public static function getConnection($dsn)
    {
        if (is_array($dsn)) {
            $options = [];
            $driver = isset($dsn['driver']) ? $dsn['driver'] : (isset($dsn['adapter']) ? $dsn['adapter'] : 'mysql');
            if (strpos($driver, 'pdo_') === false) {
                $driver = 'pdo_' . strtolower($driver);
            }
            $options['driver'] = $driver;
                // phalcon params
            if ($driver === 'pdo_sqlite') {
                if ($dsn['dbname'] === ':memory:') {
                    $options['memory'] = true;
                } else {
                    $options['path'] = $dsn['dbname'];
                }
            } elseif ($driver === 'pdo_mysql') {
                $options['user'] = isset($dsn['username']) ? $dsn['username'] : 'root';
                foreach (['password', 'host', 'port', 'dbname', 'unix_socket', 'charset'] as $name) {
                    if (isset($dsn[$name])) {
                        $options[$name] = $dsn[$name];
                    }
                }
                if (isset($options['persistent'])) {
                    $dsn['options'][PDO::ATTR_PERSISTENT] = true;
                }
            }
            $options['driverOptions'] = isset($dsn['options']) ? $dsn['options'] : [];
            $options['driverOptions'][PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        } elseif (is_string($dsn)) {
            $options = ['url' => $dsn];
        } elseif ($dsn instanceof PDO) {
            $options = ['pdo' => $dsn];
        }
        return DriverManager::getConnection($options);
    }
}
