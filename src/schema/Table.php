<?php
namespace winwin\db\tools\schema;

use Doctrine\DBAL\Schema\Table as DoctrineTable;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use InvalidArgumentException;
use ReflectionClass;

class Table
{
    /**
     * @var array
     */
    private static $COLUMN_DEFAULTS;

    /**
     * @var DoctrineTable
     */
    private $table;
    
    public function __construct(DoctrineTable $table)
    {
        $this->table = $table;
    }

    /**
     * @return DoctrineTable
     */
    public function getTable(): DoctrineTable
    {
        return $this->table;
    }

    /**
     * Constructs table instance from data
     * 
     * @param DoctrineTable $table
     * @param array $definitions
     * @return self
     */
    public static function fromArray(DoctrineTable $table, array $definitions)
    {
        if (empty($definitions['columns'])) {
            throw new InvalidArgumentException("columns is required");
        }
        foreach ($definitions['columns'] as $name => $columnDef) {
            list ($type, $options) = self::parseColumn($columnDef);
            $table->addColumn($name, $type, $options);
        }
        if (isset($definitions['indexes'])) {
            foreach ($definitions['indexes'] as $name => $indexDef) {
                $index = self::parseIndex($indexDef);
                if ($index['type'] === 'PRIMARY') {
                    $table->setPrimaryKey($index['columns']);
                } elseif ($index['type'] === 'UNIQUE') {
                    $table->addUniqueIndex($index['columns'], $name, $index['options']);
                } else {
                    $table->addIndex($index['columns'], $name, $index['flags'], $index['options']);
                }
            }
        }
        if (isset($definitions['options'])) {
            foreach ($definitions['options'] as $name => $val) {
                $table->addOption($name, $val);
            }
        }
        return new self($table);
    }
    
    public function toArray(): array
    {
        $table = $this->table;
        $columns = [];
        foreach ($table->getColumns() as $name => $column) {
            $columns[$name] = $this->stringifyColumn($column);
        }
        $indexes = [];
        foreach ($table->getIndexes() as $index) {
            if ($index->isPrimary()) {
                $indexes['PRIMARY'] = $this->stringifyIndex($index);
            } else {
                $indexes[$index->getName()] = $this->stringifyIndex($index);
            }
        }
        $foreignKeys = [];
        foreach ($table->getForeignKeys() as $foreignKey) {
            $foreignKeys[] = $this->stringifyForeignKey($foreignKey);
        }
        return array_filter([
            'columns' => $columns,
            'indexes' => $indexes,
            'foreignKeys' => $foreignKeys,
            'options' => $table->getOptions()
        ]);
    }

    private function stringifyColumn(Column $column): string
    {
        $defaults = self::getColumnDefaults();
        $info = $column->toArray();
        $type = $column->getType()->getName();
        if (isset($info['length'])) {
            $type .= '(' . $info['length'] . ')';
        }
        foreach (['name', 'type', 'length'] as $name) {
            unset($info[$name]);
        }
        foreach (['unsigned', 'fixed', 'notnull', 'autoincrement'] as $key) {
            if (!empty($info[$key])) {
                $type .= " " . $key;
            }
            unset($info[$key]);
        }
        if (isset($info['collation']) && $info['collation'] === 'utf8_general_ci') {
            unset($info['collation']);
        }
        foreach ($info as $name => $val) {
            if (array_key_exists($name, $defaults) && $val === $defaults[$name]) {
                unset($info[$name]);
            }
        }
        if (!empty($info)) {
            return $type . ' ' . self::jsonEncode($info);
        } else {
            return $type;
        }
    }

    private static function getColumnDefaults(): array
    {
        if (null === self::$COLUMN_DEFAULTS) {
            $defaults = [];
            $class = new ReflectionClass(Column::class);
            foreach ($class->getDefaultProperties() as $name => $val) {
                $defaults[trim($name, '_')] = $val;
            }
            self::$COLUMN_DEFAULTS = $defaults;
        }
        return self::$COLUMN_DEFAULTS;
    }

    private function stringifyIndex(Index $index): string
    {
        if ($index->isPrimary()) {
            $def = 'PRIMARY KEY(';
        } elseif ($index->isUnique()) {
            $def = 'UNIQUE KEY(';
        } else {
            $def = 'KEY(';
        }
        $options = $index->getOptions();
        $length = isset($options['length']) ? $options['length'] : [];
        $def .= implode(',', array_map(function ($name) use ($length): string {
            if (isset($length[$name])) {
                return $name . '('.$length[$name] . ')';
            } else {
                return $name;
            }
        }, $index->getColumns()));
        $def .= ')';
        unset($options['length']);
        $others = [];
        if (!empty($options)) {
            $others['options'] = $options;
        }
        $flags = $index->getFlags();
        if (!empty($flags)) {
            $others['flags'] = $flags;
        }
        return $def . (empty($others) ? '' : ' ' . self::jsonEncode($others));
    }

    private function stringifyForeignKey(ForeignKeyConstraint $foreignKey): string
    {
        $def = sprintf(
            '(%s) REFERENCES %s (%s)',
            $localColumns = implode(',', $foreignKey->getLocalColumns()),
            $foreignTable = $foreignKey->getForeignTableName(),
            $foreignColumns = implode(',', $foreignKey->getForeignColumns())
        );
        $options = $foreignKey->getOptions();
        return $def . (empty($options) ? '' : ' ' . self::jsonEncode($options));
    }

    private static function parseColumn(string $columnDef): array
    {
        $original = $columnDef;
        $columnDef = trim($columnDef);
        if (preg_match('/^(\w+)\s*/', $columnDef, $matches)) {
            $type = $matches[1];
            $columnDef = substr($columnDef, strlen($matches[0]));
        } else {
            throw new InvalidArgumentException("column definition is invalid: '{$columnDef}'");
        }
        $options = [];
        if (preg_match('/^\(\s*(\d+)\s*\)/', $columnDef, $matches)) {
            $options['length'] = (int) $matches[1];
        }
        if (!empty($columnDef) && $columnDef[0] == '{') {
            $options = array_merge($options, json_decode($columnDef, true));
        } else {
            if (($pos = strpos($columnDef, ' {')) !== false) {
                $data = json_decode(substr($columnDef, $pos+1), true);
                if (JSON_ERROR_NONE !== json_last_error()) {
                    throw new \InvalidArgumentException(
                        'json_decode error: ' . json_last_error_msg() . ' when parse ' . $original);
                }
                $options = array_merge($options, $data);
                $columnDef = substr($columnDef, 0, $pos);
            }
            foreach (preg_split('/\s+/', $columnDef) as $name) {
                if (!empty($name)) {
                    $options[$name] = true;
                }
            }
        }
        foreach (['unsigned', 'fixed', 'notnull', 'autoincrement'] as $key) {
            if (!isset($options[$key])) {
                $options[$key] = false;
            }
        }
        return [$type, $options];
    }

    private static function parseIndex(string $indexDef): array
    {
        $def = $indexDef;
        $indexDef = trim($indexDef);
        $index = [
            'name' => null,
            'type' => 'KEY',
            'columns' => [],
            'flags' => [],
            'options' => [],
        ];
        if (preg_match('/^((primary|unique)\s+)?key/i', $indexDef, $matches)) {
            if (isset($matches[2])) {
                $index['type'] = strtoupper($matches[2]);
            }
            $indexDef = substr($indexDef, strlen($matches[0]));
        } else {
            throw new InvalidArgumentException("invalid index definition '{$indexDef}'");
        }
        if (($pos = strpos($indexDef, ' {')) !== false) {
            $options = json_decode(substr($indexDef, $pos+1), true);
            if (isset($options['options'])) {
                $index['options'] = $options['options'];
            }
            if (isset($options['flags'])) {
                $index['flags'] = $options['flags'];
            }
            $indexDef = substr($indexDef, 0, $pos);
        }
        $indexDef = trim($indexDef);
        if (preg_match('/^\((.*)\)$/', $indexDef, $matches)) {
            foreach (preg_split('/\s*,\s*/', $matches[1]) as $column) {
                if (preg_match('/\(\s*(\d+)\s*\)$/', $column, $matches)) {
                    $column = trim(substr($column, 0, -strlen($matches[0])));
                    $index['options']['length'][$column] = (int)$matches[1];
                }
                $index['columns'][] = $column;
            }
        } else {
            throw new InvalidArgumentException("invalid index definition '{$def}'");
        }
        return $index;
    }

    /**
     * @param array $data
     */
    private static function jsonEncode($data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
