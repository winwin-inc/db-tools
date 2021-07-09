<?php echo '<?php'; ?>

<?php if ($namespace) { ?>

namespace <?php echo $namespace; ?>;
<?php } ?>

use kuiper\db\AbstractCrudRepository;
use kuiper\db\annotation\Repository;
use kuiper\db\Criteria;
use kuiper\db\metadata\MetaModelInterface;
use <?php echo $entityNamespace; ?>\<?php echo $entityClass; ?>;

/**
 * @method <?php echo $entityClass; ?>|null findById($id)
 * @method bool existsById($id)
 * @method <?php echo $entityClass; ?>[] findAllById(array $ids): array
 * @method void insert(<?php echo $entityClass; ?> $<?php echo $varName; ?>)
 * @method void batchInsert(array $entities)
 * @method void update(<?php echo $entityClass; ?> $<?php echo $varName; ?>)
 * @method void batchUpdate(array $entities)
 * @method void save(<?php echo $entityClass; ?> $<?php echo $varName; ?>)
 * @method void batchSave(array $entities)
 * @method void delete(<?php echo $entityClass; ?> $<?php echo $varName; ?>)
 * @method void deleteById($id)
 * @method void deleteAllById(array $ids)
 * @method MetaModelInterface getMetaModel()
 */
interface <?php echo $entityClass; ?>Repository
{
}
