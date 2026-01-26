<?php

namespace App\Entity;

use App\Repository\ConfigurationTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ConfigurationTypeRepository::class)
 * @ORM\Table(name="configuration_type",
 *    uniqueConstraints={
 *        @ORM\UniqueConstraint(name="uniq_name_family", columns={"name","family"})
 *    }
 * )
 */
class ConfigurationType
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
     * @ORM\Column(type="string", length=20)
     * @Assert\Choice({"home", "professional"})
     */
    private ?string $family = null;

    /**
     * ✅ inversedBy="configurationTypes" (en Attribute)
     * @ORM\ManyToMany(targetEntity=Attribute::class, mappedBy="configurationTypes")
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

    public function getFamily(): ?string { return $this->family; }
    public function setFamily(string $family): self { $this->family = $family; return $this; }

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
        }
        return $this;
    }

    public function removeAttribute(Attribute $attribute): self
    {
        $this->attributes->removeElement($attribute);
        return $this;
    }
}
