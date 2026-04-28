<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="roof")
 */
class Roof
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
    private $serie;

    /** @ORM\Column(type="string", length=255) */
    private $place;

    /** @ORM\Column(type="string", length=255) */
    private $columns;

    /** @ORM\Column(type="string", length=50, nullable=true) */
    private $tipo;

    public function __construct() {}

    public function getId(): ?int { return $this->id; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $v): self { $this->reference = $v; return $this; }

    public function getSerie(): ?string { return $this->serie; }
    public function setSerie(string $v): self { $this->serie = $v; return $this; }

    public function getPlace(): ?string { return $this->place; }
    public function setPlace(string $v): self { $this->place = $v; return $this; }

    public function getColumns(): ?string { return $this->columns; }
    public function setColumns(string $v): self { $this->columns = $v; return $this; }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(?string $v): self { $this->tipo = $v; return $this; }
}
