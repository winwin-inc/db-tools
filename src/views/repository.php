<?= '<?php'; ?>

<?php if ($namespace) : ?>

namespace <?= $namespace; ?>;
<?php endif; ?>

use kuiper\db\AbstractCrudRepository;
use kuiper\db\annotation\Repository;
use kuiper\db\Criteria;
use <?= $entityNamespace; ?>\<?= $entityClass; ?>;

/**
 * @Repository(entityClass=<?= $entityClass; ?>::class)
 *
 * @method <?= $entityClass; ?>|null findById($id)
 * @method <?= $entityClass; ?>|null findFirstBy(Criteria $criteria)
 * @method <?= $entityClass; ?>|null findByNaturalId(<?= $entityClass; ?> $example)
 * @method <?= $entityClass; ?>[] findAllByNaturalId(array $examples): array
 * @method <?= $entityClass; ?>[] findAllById(array $ids): array
 * @method <?= $entityClass; ?>[] findAllBy(Criteria $criteria): array
 */
class <?= $entityClass; ?>Repository extends AbstractCrudRepository
{
}
