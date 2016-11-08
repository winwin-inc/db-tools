<?php
namespace winwin\db\tools\schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Schema\Schema as DoctrineSchema;
use Doctrine\DBAL\Schema\SchemaDiff;
use winwin\db\tools\schema\types\TinyintType;

class Schema
{
    private static $REGISTRY;

    /**
     * Initialize schema setup
     * 
     * @param Connection $conn
     * @return static
     */
    public static function register(Connection $conn = null)
    {
        if (!Type::hasType('tinyint')) {
            Type::addType('tinyint', TinyintType::class);
        }
        if ($conn === null) {
            return;
        }
        $hash = spl_object_hash($conn);
        if (isset(self::$REGISTRY[$hash])) {
            return;
        }
        $platform = $conn->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('tinyint', 'tinyint');
        $eventManager = $platform->getEventManager();
        if ($eventManager === null) {
            $platform->setEventManager($eventManager = new EventManager);
        }
        $eventManager->addEventSubscriber(new SchemaEventSubscriber());
        self::$REGISTRY[$hash] = true;
    }

    /**
     * @param Connection|array $source
     * @param array $includedTables
     * @return \Doctrine\DBAL\Schema\Schema 
     */
    public static function createSchema($source, array $includedTables = null)
    {
        if ($source instanceof Connection) {
            $conn = $source;
            self::register($conn);
            $sm = $conn->getSchemaManager();
            $tables = [];
            foreach ($sm->listTableNames() as $table) {
                if (empty($includedTables) || in_array($table, $includedTables)) {
                    $tables[] = $sm->listTableDetails($table);
                }
            }
            $namespaces = [];
            if ($conn->getDatabasePlatform()->supportsSchemas()) {
                $namespaces = $sm->listNamespaceNames();
            }
            return new DoctrineSchema($tables, [], $sm->createSchemaConfig(), $namespaces);
        } elseif (is_array($source)) {
            self::register(null);
            $schema = new DoctrineSchema;
            $tables = [];
            foreach ($source as $table => $definitions) {
                if (empty($includedTables) || in_array($table, $includedTables)) {
                    $tables[] = Table::fromArray($schema->createTable($table), $definitions);
                }
            }
            return $schema;
        }
    }

    /**
     * @param DoctrineSchema|SchemaDiff $schema
     * @param Connection $conn
     * @return string
     */
    public static function toSql($schema, Connection $conn)
    {
        self::register($conn);
        if ($schema instanceof DoctrineSchema) {
            return $schema->toSql($conn->getDatabasePlatform());
        } elseif ($schema instanceof SchemaDiff) {
            return $schema->toSql($conn->getDatabasePlatform());
        }
    }

    /**
     * @param DoctrineSchema|SchemaDiff $schema
     * @param Connection $conn
     * @return string
     */
    public static function toArray($schema)
    {
        if ($schema instanceof DoctrineSchema) {
            $tables = [];
            foreach ($schema->getTables() as $table) {
                $tables[$table->getName()] = (new Table($table))->toArray();
            }
            return $tables;
        } elseif ($schema instanceof SchemaDiff) {
            return [];
        }
    }
}
