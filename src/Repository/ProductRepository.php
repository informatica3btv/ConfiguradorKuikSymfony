<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }


    public function findOneBySerieAndPlace(int $serie, string $place): ?Product
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.serie = :serie')
            ->andWhere('p.place = :place')
            ->setParameter('serie', $serie)
            ->setParameter('place', $place)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneBySerieAndPlaceAndSize(string $serie, string $place, string $size): ?Product
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.serie = :serie')
            ->andWhere('p.place = :place')
            ->andWhere('p.size = :size')
            ->setParameter('serie', $serie)
            ->setParameter('place', $place)
            ->setParameter('size', $size)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
