<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ControlRepository")
 * @ORM\Table(name="control")
 */
class Control
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @Gedmo\Locale
     */
    private $locale;

    /** @ORM\Column(type="string", length=255) */
    private $reference;

    /** @ORM\Column(type="string", length=50) */
    private $place; // interior | exterior

    /**
     * @Gedmo\Translatable
     * @ORM\Column(type="string", length=500, nullable=true)
     */
    private $descripcion;

    /** @ORM\Column(type="string", length=50, nullable=true) */
    private $tipo;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    private $aceroInoxidable = false;

    public function getId(): ?int { return $this->id; }

    public function setTranslatableLocale(string $locale): self { $this->locale = $locale; return $this; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $v): self { $this->reference = $v; return $this; }

    public function getPlace(): ?string { return $this->place; }
    public function setPlace(string $v): self { $this->place = $v; return $this; }

    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $v): self { $this->descripcion = $v; return $this; }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(?string $v): self { $this->tipo = $v; return $this; }

    public function isAceroInoxidable(): bool { return (bool) $this->aceroInoxidable; }
    public function setAceroInoxidable(bool $v): self { $this->aceroInoxidable = $v; return $this; }
}
