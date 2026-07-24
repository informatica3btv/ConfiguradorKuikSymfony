<?php

namespace App\Repository;

use App\Entity\ProductTypeCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductTypeCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductTypeCategory::class);
    }

    /**
     * @return ProductTypeCategory[]
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['label' => 'ASC']);
    }

    /**
     * @return array<string, ProductTypeCategory>
     */
    public function findAllIndexedByTypeKey(): array
    {
        $indexed = [];
        foreach ($this->findAll() as $typeCategory) {
            $indexed[$typeCategory->getTypeKey()] = $typeCategory;
        }
        return $indexed;
    }
}
