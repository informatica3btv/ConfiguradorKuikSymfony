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

    public function getId(): ?int { return $this->id; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $v): self { $this->reference = $v; return $this; }

    public function getAlto(): ?string { return $this->alto; }
    public function setAlto(string $v): self { $this->alto = $v; return $this; }

    public function getAncho(): ?string { return $this->ancho; }
    public function setAncho(string $v): self { $this->ancho = $v; return $this; }

    public function getFondo(): ?string { return $this->fondo; }
    public function setFondo(string $v): self { $this->fondo = $v; return $this; }
}
