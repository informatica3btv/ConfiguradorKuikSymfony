<?php

namespace App\Repository;

use App\Entity\Mailbox;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MailboxRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mailbox::class);
    }

    public function findOneMailboxByDimensions(string $alto, string $ancho, string $fondo): ?Mailbox
    {
        return $this->findOneBy(['alto' => $alto, 'ancho' => $ancho, 'fondo' => $fondo]);
    }
}
