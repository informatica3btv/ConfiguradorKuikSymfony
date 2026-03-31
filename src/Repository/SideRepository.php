<?php

namespace App\Repository;

use App\Entity\Side;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Side::class);
    }

    public function findOneSideBySerie(string $serie): ?Side
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.serie = :serie')
            ->setParameter('serie', $serie)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneSideBySerieAndPlace(string $serie,string $place): ?Side
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.serie = :serie')
            ->andWhere('p.place = :place')
            ->setParameter('serie', $serie)
            ->setParameter('place', $place)
            ->getQuery()
            ->getOneOrNullResult();
    }

}
