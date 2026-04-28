<?php

namespace App\Repository;

use App\Entity\Bandeja;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BandejaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bandeja::class);
    }

    public function findOneBySerie(string $serie, ?string $tipo = null): ?Bandeja
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.serie = :serie')
            ->setParameter('serie', $serie);

        if ($tipo !== null) {
            $qb->andWhere('b.tipo = :tipo OR b.tipo IS NULL')
               ->setParameter('tipo', $tipo);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
