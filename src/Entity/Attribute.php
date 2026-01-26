<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\AttributeRepository;

/**
 * @ORM\Entity(repositoryClass=AttributeRepository::class)
 * @ORM\Table(name="attribute")
 */
class Attribute
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
     * ✅ Muchos Attribute -> 1 AttributesType
     * @ORM\ManyToOne(targetEntity=AttributesType::class, inversedBy="attributes")
     * @ORM\JoinColumn(name="attributes_type_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private ?AttributesType $attributesType = null;

    /**
     * ✅ Muchos Attribute <-> Muchos ConfigurationType
     * @ORM\ManyToMany(targetEntity=ConfigurationType::class, inversedBy="attributes")
     * @ORM\JoinTable(name="attribute_configuration_type",
     *   joinColumns={@ORM\JoinColumn(name="attribute_id", referencedColumnName="id", onDelete="CASCADE")},
     *   inverseJoinColumns={@ORM\JoinColumn(name="configuration_type_id", referencedColumnName="id", onDelete="CASCADE")}
     * )
     */
    private Collection $configurationTypes;

    public function __construct()
    {
        $this->configurationTypes = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getValue(): ?string { return $this->value; }
    public function setValue(string $value): self { $this->value = $value; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getAttributesType(): ?AttributesType { return $this->attributesType; }
    public function setAttributesType(AttributesType $type): self { $this->attributesType = $type; return $this; }

    /**
     * @return Collection|ConfigurationType[]
     */
    public function getConfigurationTypes(): Collection
    {
        return $this->configurationTypes;
    }

    public function addConfigurationType(ConfigurationType $type): self
    {
        if (!$this->configurationTypes->contains($type)) {
            $this->configurationTypes->add($type);
            $type->addAttribute($this); // mantener ambos lados sincronizados
        }
        return $this;
    }

    public function removeConfigurationType(ConfigurationType $type): self
    {
        if ($this->configurationTypes->removeElement($type)) {
            $type->removeAttribute($this);
        }
        return $this;
    }
}
