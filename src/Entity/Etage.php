<?php

namespace App\Entity;

use App\Repository\EtageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EtageRepository::class)]
class Etage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'etages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Site $site = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 200)]
    private ?string $arriereplan = null;

    #[ORM\Column]
    private ?int $largeur = null;

    #[ORM\Column]
    private ?int $hauteur = null;

    #[ORM\OneToMany(mappedBy: 'etage', targetEntity: Service::class)]
    private Collection $services;

    #[ORM\OneToMany(mappedBy: 'etage', targetEntity: Switch::class)]
    private Collection $switches;

    #[ORM\OneToMany(mappedBy: 'etage', targetEntity: Position::class)]
    private Collection $positions;

    public function __construct()
    {
        $this->services = new ArrayCollection();
        $this->switches = new ArrayCollection();
        $this->positions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): ?Site
    {
        return $this->site;
    }

    public function setSite(?Site $site): static
    {
        $this->site = $site;

        return $this;
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

    public function getArriereplan(): ?string
    {
        return $this->arriereplan;
    }

    public function setArriereplan(string $arriereplan): static
    {
        $this->arriereplan = $arriereplan;

        return $this;
    }

    public function getLargeur(): ?int
    {
        return $this->largeur;
    }

    public function setLargeur(int $largeur): static
    {
        $this->largeur = $largeur;

        return $this;
    }

    public function getHauteur(): ?int
    {
        return $this->hauteur;
    }

    public function setHauteur(int $hauteur): static
    {
        $this->hauteur = $hauteur;

        return $this;
    }

    /**
     * @return Collection<int, Service>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
            $service->setEtage($this);
        }

        return $this;
    }

    public function removeService(Service $service): static
    {
        if ($this->services->removeElement($service)) {
            // set the owning side to null (unless already changed)
            if ($service->getEtage() === $this) {
                $service->setEtage(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Switch>
     */
    public function getSwitches(): Collection
    {
        return $this->switches;
    }

    public function addSwitch(Switch $switch): static
    {
        if (!$this->switches->contains($switch)) {
            $this->switches->add($switch);
            $switch->setEtage($this);
        }

        return $this;
    }

    public function removeSwitch(Switch $switch): static
    {
        if ($this->switches->removeElement($switch)) {
            // set the owning side to null (unless already changed)
            if ($switch->getEtage() === $this) {
                $switch->setEtage(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Position>
     */
    public function getPositions(): Collection
    {
        return $this->positions;
    }

    public function addPosition(Position $position): static
    {
        if (!$this->positions->contains($position)) {
            $this->positions->add($position);
            $position->setEtage($this);
        }

        return $this;
    }

    public function removePosition(Position $position): static
    {
        if ($this->positions->removeElement($position)) {
            // set the owning side to null (unless already changed)
            if ($position->getEtage() === $this) {
                $position->setEtage(null);
            }
        }

        return $this;
    }
}
