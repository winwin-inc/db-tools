<?php

declare(strict_types=1);

namespace repository;

use kuiper\db\AbstractCrudRepository;
use kuiper\db\annotation\Repository;
use kuiper\db\Criteria;
use winwin\db\tools\fixtures\entity\Foo;

/**
 * @Repository(entityClass=Foo::class)
 *
 * @method Foo|null findById($id)
 * @method Foo|null findFirstBy(Criteria $criteria)
 * @method Foo|null findByNaturalId(Foo $example)
 * @method Foo[] findAllByNaturalId(array $examples): array
 * @method Foo[] findAllById(array $ids): array
 * @method Foo[] findAllBy(Criteria $criteria): array
 */
class FooRepository extends AbstractCrudRepository
{
}
