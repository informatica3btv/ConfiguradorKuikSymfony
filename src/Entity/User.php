<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
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
     * Nula para los usuarios normales (email). Nunca se usa para
     * autenticar (eso lo valida la API de BTV en cada login), solo para
     * cumplir con el tipo de columna existente.
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $password = null;

    /**
     * Código numérico (4-5 dígitos) de distribuidor, igual que su usuario
     * en BTV. Nulo para los usuarios que se loguean con email.
     *
     * @ORM\Column(type="string", length=10, unique=true, nullable=true)
     */
    private ?string $distributorCode = null;


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

    // 👇 PASSWORD (nulo para usuarios de distribuidor, que no tienen)
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getDistributorCode(): ?string
    {
        return $this->distributorCode;
    }

    public function setDistributorCode(?string $distributorCode): self
    {
        $this->distributorCode = $distributorCode;
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
