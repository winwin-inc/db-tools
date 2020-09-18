<?= '<?php'; ?>

<?php if ($namespace) : ?>

namespace <?= $namespace; ?>;
<?php endif; ?>

use kuiper\db\annotation\CreationTimestamp;
use kuiper\db\annotation\GeneratedValue;
use kuiper\db\annotation\Id;
use kuiper\db\annotation\UpdateTimestamp;
use kuiper\db\annotation\Convert;
use kuiper\db\converter\DateConverter;

class <?= $className; ?>

{
<?php foreach ($columns as $column) : ?>
    /**
<?php /** @var \winwin\db\tools\generator\Column $column */ if ($column->isAutoincrement()) : ?>
     * @Id
     * @GeneratedValue
<?php endif; ?>
<?php if ($column->isCreatedAt()) : ?>
     * @CreationTimestamp
<?php endif; ?>
<?php if ($column->isUpdatedAt()) : ?>
     * @UpdateTimestamp
<?php endif; ?>
<?php if ('date' === $column->getDbType()): ?>
     * @Convert(DateConverter::class)
<?php endif; ?>
     * @var <?= $column->getVarType(); ?>|null
     */
    private $<?= $column->getVarName(); ?>;

<?php endforeach; ?>
<?php foreach ($columns as $column) : ?>
    /**
     * @return <?= $column->getVarType(); ?>|null
     */
    public function get<?= $column->getMethodName(); ?>(): ?<?= $column->getVarType(); ?>

    {
        return $this-><?= $column->getVarName(); ?>;
    }

    /**
     * @param <?= $column->getVarType(); ?>|null $<?= $column->getVarName(); ?>

     */
    public function set<?= $column->getMethodName(); ?>(?<?= $column->getVarType(); ?> $<?= $column->getVarName(); ?>): void
    {
        $this-><?= $column->getVarName(); ?> = $<?= $column->getVarName(); ?>;
    }

<?php endforeach; ?>
}
