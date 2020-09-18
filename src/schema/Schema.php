<?php

declare(strict_types=1);

namespace winwin\db\tools\schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Schema as DoctrineSchema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Types\Type;
use winwin\db\tools\schema\types\EnumType;
use winwin\db\tools\schema\types\TinyintType;

class Schema
{
    /**
     * @var array<string,bool>
     */
    private static $REGISTRY;

    /**
     * @param Connection|null $conn
     *
     * @throws DBALException
     */
    public static function register(?Connection $conn = null): void
    {
        foreach ([
                     TinyintType::TINYINT => TinyintType::class,
                     EnumType::ENUM_TYPE => EnumType::class,
                 ] as $type => $typeClass) {
            if (!Type::hasType($type)) {
                Type::addType($type, $typeClass);
            }
        }
        if (null === $conn) {
            return;
        }
        $hash = spl_object_hash($conn);
        if (isset(self::$REGISTRY[$hash])) {
            return;
        }
        $platform = $conn->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('tinyint', 'tinyint');
        $platform->registerDoctrineTypeMapping('enum', 'enum');
        $eventManager = $platform->getEventManager();
        if (null === $eventManager) {
            $platform->setEventManager($eventManager = new EventManager());
        }
        $eventManager->addEventSubscriber(new SchemaEventSubscriber());
        self::$REGISTRY[$hash] = true;
    }

    /**
     * @param Connection|array     $source
     * @param array|PatternMatcher $includedTables
     *
     * @return DoctrineSchema
     *
     * @throws DBALException
     */
    public static function createSchema($source, $includedTables = null): DoctrineSchema
    {
        if ($source instanceof Connection) {
            $conn = $source;
            self::register($conn);
            $sm = $conn->getSchemaManager();
            $tables = [];
            foreach ($sm->listTableNames() as $table) {
                if (self::match($includedTables, $table)) {
                    $tables[] = $sm->listTableDetails($table);
                }
            }
            $namespaces = [];
            if ($conn->getDatabasePlatform()->supportsSchemas()) {
                $namespaces = $sm->listNamespaceNames();
            }

            return new DoctrineSchema($tables, [], $sm->createSchemaConfig(), $namespaces);
        }

        if (is_array($source)) {
            self::register(null);
            $schema = new DoctrineSchema();
            foreach ($source as $table => $definitions) {
                if (self::match($includedTables, $table)) {
                    Table::fromArray($schema->createTable($table), $definitions);
                }
            }

            return $schema;
        }
        throw new \InvalidArgumentException('Cannot create schema from '.gettype($source));
    }

    /**
     * @param string[]|PatternMatcher $includedTables
     * @param string                  $table
     *
     * @return bool
     */
    private static function match($includedTables, string $table): bool
    {
        if (!empty($includedTables) && is_array($includedTables)) {
            return in_array($table, $includedTables, true);
        } elseif ($includedTables instanceof PatternMatcher) {
            return $includedTables->match($table);
        } else {
            return true;
        }
    }

    /**
     * @param DoctrineSchema|SchemaDiff $schema
     * @param Connection                $conn
     *
     * @return array
     *
     * @throws DBALException
     */
    public static function toSql($schema, Connection $conn): array
    {
        self::register($conn);
        if ($schema instanceof DoctrineSchema) {
            return $schema->toSql($conn->getDatabasePlatform());
        } elseif ($schema instanceof SchemaDiff) {
            return $schema->toSql($conn->getDatabasePlatform());
        }
        throw new \InvalidArgumentException('invalid schema '.gettype($schema));
    }

    /**
     * @param DoctrineSchema|SchemaDiff $schema
     *
     * @return array
     */
    public static function toArray($schema): array
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
        throw new \InvalidArgumentException('invalid schema '.gettype($schema));
    }
}
