<?= '<?php' ?>

<?php if ($namespace) : ?>

namespace <?= $namespace ?>;
<?php endif ?>

use winwin\db\orm\annotation\Column;
use winwin\db\orm\annotation\CreatedAt;
use winwin\db\orm\annotation\Entity;
use winwin\db\orm\annotation\Enum;
use winwin\db\orm\annotation\GeneratedValue;
use winwin\db\orm\annotation\Id;
use winwin\db\orm\annotation\Serializer;
use winwin\db\orm\annotation\Table;
use winwin\db\orm\annotation\UniqueConstraint;
use winwin\db\orm\annotation\UpdatedAt;

/**
 * @Entity
 * @Table
 */
class <?= $className ?>

{
<?php foreach ($columns as $column) : ?>
    /**
     * @Column
<?php if ($column['isAutoincrement']) : ?>
     * @Id
     * @GeneratedValue
<?php endif ?>
<?php if ($column['isCreatedAt']) : ?>
     * @CreatedAt
<?php endif ?>
<?php if ($column['isUpdatedAt']) : ?>
     * @UpdatedAt
<?php endif ?>
     * @var <?= $column['varType'] ?>

     */
    private $<?= $column['varName'] ?>;

<?php endforeach ?>
<?php foreach ($columns as $column) : ?>
    /**
     * @return <?= $column['varType'] ?>

     */
    public function get<?= $column['methodName'] ?>()
    {
        return $this-><?= $column['varName'] ?>;
    }
    
    /**
     * @param <?= $column['varType'] ?> $<?= $column['varName'] ?>

     * 
     * @return static
     */
    public function set<?= $column['methodName'] ?>($<?= $column['varName'] ?>)
    {
        $this-><?= $column['varName'] ?> = $<?= $column['varName'] ?>;
        
        return $this;
    }

<?php endforeach ?>
}
