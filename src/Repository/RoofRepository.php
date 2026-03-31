<?php

namespace App\Repository;

use App\Entity\Roof;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RoofRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Roof::class);
    }

    public function findOneRoofBySerieAndPlaceAndColumns(string $serie, string $place, string $columns): ?Roof
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.serie = :serie')
            ->andWhere('r.place = :place')
            ->andWhere('r.columns = :columns')
            ->setParameter('serie', $serie)
            ->setParameter('place', $place)
            ->setParameter('columns', $columns)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
