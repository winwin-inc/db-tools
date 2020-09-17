<?php

declare(strict_types=1);

namespace winwin\db\tools\generator;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use function kuiper\helper\env;
use kuiper\web\view\PhpView;
use winwin\db\tools\schema\Schema;
use winwin\db\tools\Text;

class EntityGenerator
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ClassLoader
     */
    private $loader;

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string|null
     */
    private $tablePrefix;

    /**
     * @var string|null
     */
    private $className;

    /**
     * @var PhpView
     */
    private $view;

    /**
     * EntityGenerator constructor.
     *
     * @param Connection  $connection
     * @param ClassLoader $loader
     * @param string      $table
     */
    public function __construct(Connection $connection, ClassLoader $loader, string $namespace, string $table)
    {
        $this->connection = $connection;
        $this->loader = $loader;
        $this->namespace = $namespace;
        $this->table = $table;
        $this->view = new PhpView(__DIR__.'/../views');
    }

    /**
     * @return string|null
     */
    public function getTablePrefix(): ?string
    {
        if (null === $this->tablePrefix) {
            $this->tablePrefix = env('DB_TABLE_PREFIX', '');
        }

        return $this->tablePrefix;
    }

    /**
     * @param string|null $tablePrefix
     */
    public function setTablePrefix(?string $tablePrefix): void
    {
        $this->tablePrefix = $tablePrefix;
    }

    public function getClassName(): string
    {
        if (null !== $this->className) {
            $table = $this->table;
            if (0 === strpos($this->table, $this->getTablePrefix())) {
                $table = substr($table, strlen($this->getTablePrefix()));
            }
            $this->className = Text::camelCase($table);
        }

        return $this->className;
    }

    /**
     * @return false|string
     */
    public function getFile()
    {
        return $this->loader->findFile($this->namespace.'\\'.$this->getClassName());
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function generate(): string
    {
        $file = $this->getFile();
        if (false === $file) {
            return $this->view->render('entity', [
                'className' => $this->getClassName(),
                'namespace' => $this->namespace,
                'table' => $this->table,
                'columns' => $this->getColumns(),
            ]);
        } else {
        }
    }

    /**
     * @param Connection $db
     * @param string     $table
     *
     * @return Column[]
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    private function getColumns(): array
    {
        $columns = [];
        $schema = Schema::createSchema($$this->connection, [$table]);
        $table = $schema->getTable($table);
        foreach ($table->getColumns() as $name => $column) {
            $camelcase = Text::camelCase($name);
            $type = $this->getType($column->getType(), $isAnnotationEnabled);
            $columns[] = [
                'name' => $name,
                'dbType' => $column->getType()->getName(),
                'varName' => lcfirst($camelcase),
                'varType' => $type,
                'javaType' => $this->getJavaType($column->getType()),
                'methodName' => $camelcase,
                'paramType' => '\\' === $type[0] ? $type.' ' : '',
                'isCreatedAt' => 'create_time' === $name,
                'isUpdatedAt' => 'update_time' === $name,
                'isAutoincrement' => $column->getAutoincrement(),
                'varCast' => in_array($type, ['int', 'string', 'double', 'float'], true) ? "($type) " : '',
            ];
        }
    }

    private function getType(Type $type, bool $isAnnotationEnabled): string
    {
        $typeMap = [
            'integer' => 'int',
            'bigint' => 'int',
            'string',
            'tinyint' => 'bool',
            'float',
            'double',
        ];
        if ($isAnnotationEnabled) {
            $typeMap = array_merge($typeMap, [
                'datetime' => '\DateTime',
                'time' => '\DateTime',
                'date' => '\DateTime',
            ]);
        }
        $typeName = $type->getName();
        if (in_array($typeName, $typeMap, true)) {
            return $typeName;
        } elseif (array_key_exists($typeName, $typeMap)) {
            return $typeMap[$typeName];
        } else {
            return 'string';
        }
    }
}
