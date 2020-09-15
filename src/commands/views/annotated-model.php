<?= '<?php' ?>

<?php if ($namespace) : ?>

namespace <?= $namespace ?>;
<?php endif ?>

use kuiper\db\annotation\CreationTimestamp;
use kuiper\db\annotation\GeneratedValue;
use kuiper\db\annotation\Id;
use kuiper\db\annotation\UpdateTimestamp;
use kuiper\db\annotation\Convert;
use kuiper\db\converter\DateConverter;

class <?= $className ?>

{
<?php foreach ($columns as $column) : ?>
    /**
<?php if ($column['isAutoincrement']) : ?>
     * @Id
     * @GeneratedValue
<?php endif ?>
<?php if ($column['isCreatedAt']) : ?>
     * @CreationTimestamp
<?php endif ?>
<?php if ($column['isUpdatedAt']) : ?>
     * @UpdateTimestamp
<?php endif ?>
<?php if ($column['dbType'] === 'date'): ?>
     * @Convert(DateConverter::class)
<?php endif ?>
     * @var <?= $column['varType'] ?>|null
     */
    private $<?= $column['varName'] ?>;

<?php endforeach ?>
<?php foreach ($columns as $column) : ?>
    /**
     * @return <?= $column['varType'] ?>|null
     */
    public function get<?= $column['methodName'] ?>(): ?<?= $column['varType'] ?>

    {
        return $this-><?= $column['varName'] ?>;
    }
    
    /**
     * @param <?= $column['varType'] ?>|null $<?= $column['varName'] ?>

     */
    public function set<?= $column['methodName'] ?>(?<?= $column['varType'] ?> $<?= $column['varName'] ?>): void
    {
        $this-><?= $column['varName'] ?> = $<?= $column['varName'] ?>;
    }

<?php endforeach ?>
}
