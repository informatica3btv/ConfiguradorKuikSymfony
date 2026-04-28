<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="door")
 */
class Door
{   

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /** @ORM\Column(type="string", length=255) */
    private $reference;

    /** @ORM\Column(type="string", length=255) */
    private $size;

    /** @ORM\Column(type="string", length=255) */
    private $serie;

    /** @ORM\Column(type="string", length=255) */
    private $place;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    private $methacrylate = false;

    /** @ORM\Column(type="string", length=50, nullable=true) */
    private $tipo; // 'home' | 'profesional' | null (ambos)

    public function __construct()
    {

    }

    public function getId(): ?int { return $this->id; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $v): self { $this->reference = $v; return $this; }

    public function getSize(): ?string { return $this->size; }
    public function setSize(string $v): self { $this->size = $v; return $this; }

    public function getSerie(): ?string { return $this->serie; }
    public function setSerie(string $v): self { $this->serie = $v; return $this; }

    public function getPlace(): ?string { return $this->place; }
    public function setPlace(string $v): self { $this->place = $v; return $this; }

    public function getMethacrylate(): bool { return $this->methacrylate ?? false; }
    public function setMethacrylate(bool $v): self { $this->methacrylate = $v; return $this; }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(?string $v): self { $this->tipo = $v; return $this; }
}
