<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="mailbox")
 */
class Mailbox
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
    private $alto;

    /** @ORM\Column(type="string", length=255) */
    private $ancho;

    /** @ORM\Column(type="string", length=255) */
    private $fondo;

    /** @ORM\Column(type="string", length=500, nullable=true) */
    private $descripcion;

    /** @ORM\Column(type="string", length=50, nullable=true) */
    private $tipo;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    private $agrupacion = false;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    private $electronico = false;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    private $tarjetero = false;

    /** @ORM\Column(type="boolean", options={"default": false}) */
    private $aceroInoxidable = false;

    /** @ORM\Column(type="string", length=1000, nullable=true) */
    private $imageUrl;

    public function getId(): ?int { return $this->id; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $v): self { $this->reference = $v; return $this; }

    public function getAlto(): ?string { return $this->alto; }
    public function setAlto(string $v): self { $this->alto = $v; return $this; }

    public function getAncho(): ?string { return $this->ancho; }
    public function setAncho(string $v): self { $this->ancho = $v; return $this; }

    public function getFondo(): ?string { return $this->fondo; }
    public function setFondo(string $v): self { $this->fondo = $v; return $this; }

    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $v): self { $this->descripcion = $v; return $this; }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(?string $v): self { $this->tipo = $v; return $this; }

    public function isAgrupacion(): bool { return (bool) $this->agrupacion; }
    public function setAgrupacion(bool $v): self { $this->agrupacion = $v; return $this; }

    public function isElectronico(): bool { return (bool) $this->electronico; }
    public function setElectronico(bool $v): self { $this->electronico = $v; return $this; }

    public function isTarjetero(): bool { return (bool) $this->tarjetero; }
    public function setTarjetero(bool $v): self { $this->tarjetero = $v; return $this; }

    public function isAceroInoxidable(): bool { return (bool) $this->aceroInoxidable; }
    public function setAceroInoxidable(bool $v): self { $this->aceroInoxidable = $v; return $this; }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $v): self { $this->imageUrl = $v; return $this; }
}
