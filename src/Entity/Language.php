<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LanguageRepository")
 * @ORM\Table(name="language")
 */
class Language
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=10, unique=true)
     */
    private string $code = '';

    /**
     * @ORM\Column(type="string", length=80)
     */
    private string $name = '';

    /**
     * Nombre del fichero de la imagen de bandera (almacenado en public/uploads/flags/).
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $flag = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $isActive = true;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $isDefault = false;

    /**
     * @ORM\Column(type="integer")
     */
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtolower(trim($code));
        return $this;
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

    public function getFlag(): ?string
    {
        return $this->flag;
    }

    public function setFlag(?string $flag): self
    {
        $this->flag = $flag !== null ? trim($flag) : null;
        return $this;
    }

    public function getFlagUrl(): ?string
    {
        return $this->flag ? '/uploads/flags/'.$this->flag : null;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }
}
