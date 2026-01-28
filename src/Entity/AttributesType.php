<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
/**
 * @ORM\Entity
 * @ORM\Table(name="attributes_type")
 */
class AttributesType
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $value = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $description = null;

    /**
     * @ORM\Column(type="string", length=20)
     * @Assert\Choice({"button", "selectable"})
     */
    private ?string $type = null;

    public const TYPE_BUTTON = 'button';
    public const TYPE_SELECTABLE = 'selectable';

    /**
     * ✅ 1 AttributesType -> muchos Attribute
     * @ORM\OneToMany(targetEntity=Attribute::class, mappedBy="attributesType", orphanRemoval=true)
     */
    private Collection $attributes;

    public function __construct()
    {
        $this->attributes = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getValue(): ?string { return $this->value; }
    public function setValue(string $value): self { $this->value = $value; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): self
    {
        if (!in_array($type, [self::TYPE_BUTTON, self::TYPE_SELECTABLE], true)) {
            throw new \InvalidArgumentException('Invalid type (button|selectable)');
        }
        $this->type = $type;
        return $this;
    }

    /**
     * @return Collection|Attribute[]
     */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(Attribute $attribute): self
    {
        if (!$this->attributes->contains($attribute)) {
            $this->attributes->add($attribute);
            $attribute->setAttributesType($this);
        }
        return $this;
    }

    public function removeAttribute(Attribute $attribute): self
    {
        if ($this->attributes->removeElement($attribute)) {
            if ($attribute->getAttributesType() === $this) {
                // si quieres “desenganchar”:
                // $attribute->setAttributesType(null);  // OJO: tu JoinColumn es nullable=false
            }
        }
        return $this;
    }

}
