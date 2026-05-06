<?php

namespace App\Repository;

use App\Entity\Brazo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BrazoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Brazo::class);
    }

    public function findByTipo(?string $tipo): array
    {
        $qb = $this->createQueryBuilder('b')->orderBy('b.altura', 'ASC');

        if ($tipo !== null) {
            $qb->andWhere('b.tipo = :tipo OR b.tipo IS NULL')
               ->setParameter('tipo', $tipo);
        }

        return $qb->getQuery()->getResult();
    }
}
