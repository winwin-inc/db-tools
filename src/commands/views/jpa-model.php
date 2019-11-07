<?php if ($namespace) : ?>

package <?= $namespace ?>;
<?php endif ?>

import javax.persistence.*;

@Entity
@Table(name = "<?= $table ?>")
public class <?= $className ?> {
<?php foreach ($columns as $column) : ?>
<?php if ($column['isAutoincrement']) : ?>
    @Id
    @GeneratedValue(strategy = GenerationType.AUTO)
<?php endif ?>
<?php if ($column['name'] != $column['varName']): ?>    @Column (name = "<?= $column['name'] ?>")
<?php endif ?>
    private <?= $column['javaType'] ?> <?= $column['varName'] ?>;

<?php endforeach ?>
<?php foreach ($columns as $column) : ?>
    public <?= $column['javaType'] ?> get<?= $column['methodName'] ?>()
    {
        return this.<?= $column['varName'] ?>;
    }
    
    public void set<?= $column['methodName'] ?>(<?= $column['javaType'] ?> <?= $column['varName'] ?>)
    {
        this.<?= $column['varName'] ?> = <?= $column['varName'] ?>;
    }

<?php endforeach ?>
    
}
