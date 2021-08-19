<?php declare(strict_types=1);
echo '<?php'; ?>

<?php if ($namespace) { ?>

namespace <?php echo $namespace; ?>;
<?php } ?>

use kuiper\db\AbstractCrudRepository;
use kuiper\db\annotation\Repository;
use kuiper\db\Criteria;
use <?php echo $entityNamespace; ?>\<?php echo $entityClass; ?>;

/**
 * @Repository(entityClass=<?php echo $entityClass; ?>::class)
 *
 * @method <?php echo $entityClass; ?>|null findById($id)
 * @method <?php echo $entityClass; ?>|null findFirstBy(Criteria $criteria)
 * @method <?php echo $entityClass; ?>|null findByNaturalId(<?php echo $entityClass; ?> $example)
 * @method <?php echo $entityClass; ?>[] findAllByNaturalId(array $examples): array
 * @method <?php echo $entityClass; ?>[] findAllById(array $ids): array
 * @method <?php echo $entityClass; ?>[] findAllBy($criteria): array
 */
class <?php echo $entityClass; ?>RepositoryImpl extends AbstractCrudRepository implements <?php echo $entityClass; ?>Repository
{
}
