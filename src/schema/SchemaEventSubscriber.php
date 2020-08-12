<?php
namespace winwin\db\tools\schema;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\SchemaIndexDefinitionEventArgs;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Identifier;
use InvalidArgumentException;

/**
 * fix create table sql  
 */
class SchemaEventSubscriber implements EventSubscriber
{
    /**
     * @inheritDoc
     */
    public function getSubscribedEvents(): array
    {
        return [
            'onSchemaIndexDefinition',
            'onSchemaCreateTable'
        ];
    }

    /**
     * @param AbstractPlatform $platform
     * @return bool
     */
    private function isMysqlPlatform($platform): bool
    {
        return $platform->getName() === 'mysql';
    }

    public function onSchemaCreateTable(SchemaCreateTableEventArgs $args): void
    {
        if ($this->isMysqlPlatform($args->getPlatform())) {
            $table = $args->getTable();
            $columns = $args->getColumns();
            $options = $args->getOptions();
            $platform = $args->getPlatform();
            $tableName = $table->getQuotedName($platform);
            $sql = $this->getCreateTableSQL($platform, $tableName, $columns, $options);
            $args->addSql($sql);
            $args->preventDefault();
        }
    }

    public function onSchemaIndexDefinition(SchemaIndexDefinitionEventArgs $args): void
    {
        if ($this->isMysqlPlatform($args->getDatabasePlatform())) {
            $rows = $args->getConnection()->fetchAll(sprintf('SHOW INDEX FROM `%s`', $args->getTable()));
            $data = $args->getTableIndex();
            foreach ($rows as $row) {
                if ($row['Key_name'] === $data['name']) {
                    if (isset($row['Sub_part'])) {
                        $data['options']['length'][$row['Column_name']] = $row['Sub_part'];
                    }
                }
            }
            // print_r($data);
            $args->setIndex(new Index($data['name'], $data['columns'], $data['unique'], $data['primary'], $data['flags'], $data['options']));
            $args->preventDefault();
        }
    }

    public function getCreateTableSQL($platform, $tableName, $columns, $options): array
    {
        $columnListSql = $platform->getColumnDeclarationListSQL($columns);
        if (isset($options['uniqueConstraints']) && ! empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $name => $definition) {
                $columnListSql .= ', ' . $platform->getUniqueConstraintDeclarationSQL($name, $definition);
            }
        }

        if (isset($options['primary']) && ! empty($options['primary'])) {
            $columnListSql .= ', PRIMARY KEY(' . implode(', ', array_unique(array_values($options['primary']))) . ')';
        }

        if (isset($options['indexes']) && ! empty($options['indexes'])) {
            foreach ($options['indexes'] as $index => $definition) {
                $columnListSql .= ', ' . $this->getIndexDeclarationSQL($platform, $index, $definition);
            }
        }

        $query = 'CREATE TABLE ' . $tableName . ' (' . $columnListSql;

        $check = $platform->getCheckDeclarationSQL($columns);
        if (! empty($check)) {
            $query .= ', ' . $check;
        }
        $query .= ')';

        if (isset($options['engine'])) {
            $query .= 'ENGINE=' . strtoupper(trim($options['engine']));
        }

        $sql[] = $query;

        if (isset($options['foreignKeys'])) {
            foreach ((array) $options['foreignKeys'] as $definition) {
                $sql[] = $platform->getCreateForeignKeySQL($definition, $tableName);
            }
        }
        return $sql;
    }

    public function getIndexDeclarationSQL($platform, $name, Index $index): string
    {
        $options = $index->getOptions();
        if (!empty($options['length'])) {
            $length = $options['length'];
            $columns = [];
            foreach ($index->getColumns() as $column) {
                $quoted = (new Identifier($column))->getQuotedName($platform);
                if (isset($length[$column])) {
                    $quoted .= '(' . $length[$column]. ')';
                }
                $columns[] = $quoted;
            }
        } else {
            $columns = $index->getQuotedColumns($platform);
        }
        $name = new Identifier($name);

        if (count($columns) === 0) {
            throw new InvalidArgumentException("Incomplete definition. 'columns' required.");
        }

        return ($index->isUnique() ? 'UNIQUE ' : '') . 'INDEX ' . $name->getQuotedName($platform) . ' ('
            . $platform->getIndexFieldDeclarationListSQL($columns)
            . ')';
    }
}
