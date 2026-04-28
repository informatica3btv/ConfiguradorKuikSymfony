<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="envolvente")
 */
class Envolvente
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /** @ORM\Column(type="string", length=255) */
    private $reference;

    /** @ORM\Column(type="string", length=50) */
    private $tipo; // 'buzon' | 'taquilla'

    /** @ORM\Column(type="string", length=50) */
    private $rango; // 'pequeño' | 'grande'

    /** @ORM\Column(type="string", length=500, nullable=true) */
    private $descripcion;

    /** @ORM\Column(type="string", length=50, nullable=true) */
    private $tipoConfig; // 'home' | 'profesional' | null (ambos)

    public function getId(): ?int { return $this->id; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $v): self { $this->reference = $v; return $this; }

    public function getTipo(): ?string { return $this->tipo; }
    public function setTipo(string $v): self { $this->tipo = $v; return $this; }

    public function getRango(): ?string { return $this->rango; }
    public function setRango(string $v): self { $this->rango = $v; return $this; }

    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $v): self { $this->descripcion = $v; return $this; }

    public function getTipoConfig(): ?string { return $this->tipoConfig; }
    public function setTipoConfig(?string $v): self { $this->tipoConfig = $v; return $this; }
}
