<?php

namespace App\Repository;

use App\Entity\Columna;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ColumnaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Columna::class);
    }

    public function findOneColumnaBySerieAndPlace(string $serie, string $place): ?Columna
    {
        return $this->findOneBy(['serie' => $serie, 'place' => $place]);
    }
}
