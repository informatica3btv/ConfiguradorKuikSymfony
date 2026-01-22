<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="project")
 */
class Project
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;


    /** @ORM\Column(type="string", length=120) */
    private $projectName;

    /** @ORM\Column(type="string", length=120) */
    private $clientName;

    /** @ORM\Column(type="string", length=30, nullable=true) */
    private $phone;

    /** @ORM\Column(type="string", length=180, nullable=true) */
    private $email;

    /** @ORM\Column(type="string", length=120, nullable=true) */
    private $city;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $address;

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }


    public function getProjectName(): ?string { return $this->projectName; }
    public function setProjectName(string $v): self { $this->projectName = $v; return $this; }

    public function getClientName(): ?string { return $this->clientName; }
    public function setClientName(string $v): self { $this->clientName = $v; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): self { $this->phone = $v; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): self { $this->email = $v; return $this; }

    public function getCity(): ?string { return $this->city; }
    public function setCity(?string $v): self { $this->city = $v; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $v): self { $this->address = $v; return $this; }
}
