<?php

declare(strict_types=1);

namespace winwin\db\tools\generator;

use Doctrine\DBAL\Types\Type;
use winwin\db\tools\Text;

class Column
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var \Doctrine\DBAL\Schema\Column
     */
    private $column;

    /**
     * Column constructor.
     *
     * @param string                       $name
     * @param \Doctrine\DBAL\Schema\Column $column
     */
    public function __construct(string $name, \Doctrine\DBAL\Schema\Column $column)
    {
        $this->name = $name;
        $this->column = $column;
    }

    public function isAutoincrement(): bool
    {
        return $this->column->getAutoincrement();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVarName(): string
    {
        return lcfirst(Text::camelCase($this->name));
    }

    public function getMethodName(): string
    {
        return Text::camelCase($this->name);
    }

    public function getVarType(): string
    {
        return $this->getType($this->column->getType(), true);
    }

    public function getParamType(): string
    {
        $type = $this->getType($this->column->getType(), true);

        return '\\' === $type[0] ? $type.' ' : '';
    }

    public function getDbType(): string
    {
        return $this->column->getType()->getName();
    }

    public function isCreatedAt(): bool
    {
        return 'create_time' === $this->name;
    }

    public function isUpdatedAt(): bool
    {
        return 'update_time' === $this->name;
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
                'datetime' => '\DateTimeInterface',
                'time' => '\DateTimeInterface',
                'date' => '\DateTimeInterface',
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
