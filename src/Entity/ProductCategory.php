<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProductCategoryRepository")
 * @ORM\Table(name="product_category")
 */
class ProductCategory
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private string $name = '';

    /**
     * @ORM\Column(type="integer")
     */
    private int $position = 0;

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }

    public function __toString(): string { return $this->name; }
}
