<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Asigna una categoría de agrupación (ProductCategory) a cada "tipo" de línea
 * que puede aparecer en la tabla de precios (puertas, laterales, columnas...
 * y también conceptos sueltos como placa, colgador o instalación).
 *
 * @ORM\Entity(repositoryClass="App\Repository\ProductTypeCategoryRepository")
 * @ORM\Table(name="product_type_category")
 */
class ProductTypeCategory
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * Clave interna estable (door, side, roof, columna, mailbox, envolvente,
     * control, bandeja, brazo, pata, placa, colgador, instalacion, color).
     *
     * @ORM\Column(type="string", length=50, unique=true)
     */
    private string $typeKey = '';

    /**
     * Nombre visible en el admin (Puertas, Laterales...).
     *
     * @ORM\Column(type="string", length=100)
     */
    private string $label = '';

    /**
     * @ORM\ManyToOne(targetEntity=ProductCategory::class)
     * @ORM\JoinColumn(name="category_id", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     */
    private ?ProductCategory $category = null;

    public function getId(): ?int { return $this->id; }

    public function getTypeKey(): string { return $this->typeKey; }
    public function setTypeKey(string $typeKey): self { $this->typeKey = $typeKey; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }

    public function getCategory(): ?ProductCategory { return $this->category; }
    public function setCategory(?ProductCategory $category): self { $this->category = $category; return $this; }
}
