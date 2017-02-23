<?= '<?php' ?>

<?php if ($namespace) : ?>

namespace <?= $namespace ?>;
<?php endif ?>

class <?= $className ?>

{
<?php foreach ($columns as $column) : ?>
    /**
     * @var <?= $column['varType'] ?>

     */
    private $<?= $column['varName'] ?>;

<?php endforeach ?>
<?php foreach ($columns as $column) : ?>
    public function get<?= $column['methodName'] ?>()
    {
        return $this-><?= $column['varName'] ?>;
    }
    
    public function set<?= $column['methodName'] ?>($<?= $column['varName'] ?>)
    {
        $this-><?= $column['varName'] ?> = $<?= $column['varName'] ?>;
        
        return $this;
    }

<?php endforeach ?>
}
