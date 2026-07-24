<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ColorRepository")
 * @ORM\Table(name="color")
 */
class Color
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @Gedmo\Translatable
     * @ORM\Column(type="string", length=80)
     */
    private string $name = '';

    /**
     * @Gedmo\Locale
     */
    private $locale;

    /**
     * @ORM\Column(type="string", length=16)
     */
    private string $hex = '';

    /**
     * @ORM\Column(type="string", length=16)
     */
    private string $ral = '';

    /**
     * @ORM\Column(type="string", length=10)
     */
    private string $type = 'door'; // 'door' o 'body'

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private ?string $reference = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;
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

    public function getHex(): string
    {
        return $this->hex;
    }

    public function setHex(string $hex): self
    {
        $this->hex = $hex;
        return $this;
    }

     public function getRal(): string
    {
        return $this->ral;
    }

    public function setRal(string $ral): self
    {
        $this->ral = $ral;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $v): self { $this->reference = $v; return $this; }

    public function setType(string $type): self
    {
        // opcional: normalizar
        $type = strtolower(trim($type));
        if (!in_array($type, ['door', 'body'], true)) {
            throw new \InvalidArgumentException('Type debe ser "door" o "body".');
        }

        $this->type = $type;
        return $this;
    }
}
