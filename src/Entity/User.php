<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="user")
 */
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     */
    private string $email;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private string $name;

    /**
     * @ORM\Column(type="json")
     */
    private array $roles = [];

    /**
     * @ORM\Column(type="string")
     */
    private string $password;


    public function __construct()
    {
        // si quieres que por defecto ya nazca con ROLE_USER en BD
        $this->roles = ['ROLE_USER'];
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    // 👇 IDENTIFICADOR DEL USUARIO (login)
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    // ⚠️ Necesario para compatibilidad
    public function getUsername(): string
    {
        return $this->email;
    }
    
    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getRoles(): array
    {
        // devolvemos lo que haya en BD
        $roles = $this->roles ?? [];

        // (opcional) asegurar unique por seguridad
        return array_values(array_unique($roles));
    }


    public function setRoles(array $roles): self
    {
        $roles[] = 'ROLE_USER'; // siempre
        $this->roles = array_values(array_unique($roles));
        return $this;
    }

    // 👇 PASSWORD
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    // 👇 Ya no se usa (bcrypt/argon2)
    public function getSalt(): ?string
    {
        return null;
    }

    // 👇 Limpieza de datos sensibles (no necesitas nada aquí)
    public function eraseCredentials(): void
    {
    }
}
