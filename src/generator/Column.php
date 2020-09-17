<?php

declare(strict_types=1);

namespace winwin\db\tools\generator;

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
}
