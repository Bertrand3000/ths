<?php

namespace App\Entity;

use App\Repository\MaterielRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: MaterielRepository::class)]
class Materiel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['materiel:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'materiels')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Position $position = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['materiel:read'])]
    private ?string $type = null;

    #[ORM\Column]
    #[Groups(['materiel:read'])]
    private ?bool $special = false;

    #[ORM\Column(length: 20, unique: true)]
    #[Groups(['materiel:read'])]
    private ?string $codebarre = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }

    public function setPosition(?Position $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isSpecial(): ?bool
    {
        return $this->special;
    }

    public function setSpecial(bool $special): static
    {
        $this->special = $special;

        return $this;
    }

    public function getCodebarre(): ?string
    {
        return $this->codebarre;
    }

    public function setCodebarre(string $codebarre): static
    {
        $this->codebarre = $codebarre;

        return $this;
    }
}
