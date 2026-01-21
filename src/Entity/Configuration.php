<?php

namespace App\Entity;

use App\Repository\ConfigurationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ConfigurationRepository::class)
 * @ORM\Table(
 *     name="configuration",
 *     indexes={
 *         @ORM\Index(name="IDX_CONFIGURATION_USER", columns={"user_id"})
 *     }
 * )
 */
class Configuration
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(name="user_id", type="integer")
     */
    private int $userId;

    /**
     * @ORM\Column(name="project_name", type="string", length=255)
     */
    private string $projectName;

    /**
     * @ORM\Column(type="text")
     */
    private string $payload = '{}';

    /**
     * @ORM\Column(name="client_name", type="string", length=255)
     */
    private string $clientName;

    /**
     * @ORM\Column(name="client_email", type="string", length=255)
     */
    private string $clientEmail;

    /**
     * @ORM\Column(name="client_phone", type="string", length=50, nullable=true)
     */
    private ?string $clientPhone = null;

    /**
     * @ORM\Column(name="client_city", type="string", length=255, nullable=true)
     */
    private ?string $clientCity = null;

    /**
     * @ORM\Column(name="client_address", type="string", length=255, nullable=true)
     */
    private ?string $clientAddress = null;

    /**
     * @ORM\Column(name="created_at", type="datetime")
     */
    private \DateTimeInterface $createdAt;

    /**
     * @ORM\Column(name="updated_at", type="datetime")
     */
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getProjectName(): string
    {
        return $this->projectName;
    }

    public function setProjectName(string $projectName): self
    {
        $this->projectName = $projectName;
        return $this;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    public function setClientName(string $clientName): self
    {
        $this->clientName = $clientName;
        return $this;
    }

    public function getClientEmail(): string
    {
        return $this->clientEmail;
    }

    public function setClientEmail(string $clientEmail): self
    {
        $this->clientEmail = $clientEmail;
        return $this;
    }

    public function getClientPhone(): ?string
    {
        return $this->clientPhone;
    }

    public function setClientPhone(?string $clientPhone): self
    {
        $this->clientPhone = $clientPhone;
        return $this;
    }

    public function getClientAddress(): ?string
    {
        return $this->clientAddress;
    }

    public function setClientAddress(?string $clientAddress): self
    {
        $this->clientAddress = $clientAddress;
        return $this;
    }

     public function getClientCity(): ?string
    {
        return $this->clientCity;
    }

    public function setClientCity(?string $clientCity): self
    {
        $this->clientCity = $clientCity;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

       public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
