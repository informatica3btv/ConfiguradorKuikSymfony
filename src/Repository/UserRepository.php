<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Busca el usuario por email (login normal) o por código de distribuidor
 * (login vía API de BTV), según cuál de los dos coincida con lo que
 * escribió en el formulario de login.
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface, PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :identifier OR u.distributorCode = :identifier')
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @deprecated solo por compatibilidad con la interfaz del bridge de Doctrine */
    public function loadUserByUsername(string $username): ?UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
