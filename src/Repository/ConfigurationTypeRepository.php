<?php

// src/Repository/ConfigurationTypeRepository.php
namespace App\Repository;

use App\Entity\ConfigurationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConfigurationTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConfigurationType::class);
    }

    /**
     * Devuelve todos los tipos por familia (home | professional)
     */
    public function findByFamily(string $family): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.family = :family')
            ->setParameter('family', $family)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Devuelve uno por nombre + familia (útil para evitar duplicados)
     */
    public function findOneByNameAndFamily(string $name, string $family): ?ConfigurationType
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.name = :name')
            ->andWhere('c.family = :family')
            ->setParameter('name', $name)
            ->setParameter('family', $family)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Devuelve solo los nombres (para selects)
     */
    public function findNamesByFamily(string $family): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.name')
            ->andWhere('c.family = :family')
            ->setParameter('family', $family)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getScalarResult();
    }
}
