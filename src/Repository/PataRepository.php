<?php

namespace App\Repository;

use App\Entity\Pata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pata::class);
    }

    public function findByTipo(?string $tipo): array
    {
        $qb = $this->createQueryBuilder('p')->orderBy('p.numColumnas', 'ASC');

        if ($tipo !== null) {
            $qb->andWhere('p.tipo = :tipo OR p.tipo IS NULL')
               ->setParameter('tipo', $tipo);
        }

        return $qb->getQuery()->getResult();
    }
}
