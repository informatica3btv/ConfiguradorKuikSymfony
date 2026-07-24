<?php

namespace App\Repository;

use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LanguageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Language::class);
    }

    /**
     * @return Language[]
     */
    public function findActive(): array
    {
        return $this->findBy(['isActive' => true], ['position' => 'ASC', 'name' => 'ASC']);
    }

    public function findDefault(): ?Language
    {
        return $this->findOneBy(['isDefault' => true]);
    }
}
