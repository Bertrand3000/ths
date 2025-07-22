<?php

namespace App\Entity;

use App\Repository\SiteRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: SiteRepository::class)]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column]
    private ?bool $flex = true;

    #[ORM\OneToMany(mappedBy: 'site', targetEntity: Etage::class)]
    private Collection $etages;

    public function __construct()
    {
        $this->etages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function isFlex(): ?bool
    {
        return $this->flex;
    }

    public function setFlex(bool $flex): static
    {
        $this->flex = $flex;

        return $this;
    }

    /**
     * @return Collection<int, Etage>
     */
    public function getEtages(): Collection
    {
        return $this->etages;
    }

    public function addEtage(Etage $etage): static
    {
        if (!$this->etages->contains($etage)) {
            $this->etages->add($etage);
            $etage->setSite($this);
        }

        return $this;
    }

    public function removeEtage(Etage $etage): static
    {
        if ($this->etages->removeElement($etage)) {
            // set the owning side to null (unless already changed)
            if ($etage->getSite() === $this) {
                $etage->setSite(null);
            }
        }

        return $this;
    }
}
