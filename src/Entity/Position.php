<?php

namespace App\Entity;

use App\Entity\NetworkSwitch;
use App\Repository\PositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PositionRepository::class)]
class Position
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'positions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Etage $etage = null;

    #[ORM\ManyToOne(inversedBy: 'positions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Service $service = null;

    #[ORM\ManyToOne(inversedBy: 'positions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?NetworkSwitch $networkSwitch = null;

    #[ORM\Column]
    private ?int $coordx = null;

    #[ORM\Column]
    private ?int $coordy = null;

    #[ORM\Column(length: 10)]
    private ?string $prise = null;

    #[ORM\Column(length: 17, nullable: true)]
    private ?string $mac = null;

    #[ORM\Column(length: 13)]
    private ?string $type = null;

    #[ORM\Column]
    private ?bool $sanctuaire = false;

    #[ORM\Column]
    private ?bool $flex = true;

    #[ORM\OneToMany(mappedBy: 'position', targetEntity: Materiel::class)]
    private Collection $materiels;

    #[ORM\OneToOne(mappedBy: 'position', cascade: ['persist', 'remove'])]
    private ?AgentPosition $agentPosition = null;

    public function __construct()
    {
        $this->materiels = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEtage(): ?Etage
    {
        return $this->etage;
    }

    public function setEtage(?Etage $etage): static
    {
        $this->etage = $etage;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getNetworkSwitch(): ?NetworkSwitch
    {
        return $this->networkSwitch;
    }

    public function setNetworkSwitch(?NetworkSwitch $networkSwitch): static
    {
        $this->networkSwitch = $networkSwitch;

        return $this;
    }

    public function getCoordx(): ?int
    {
        return $this->coordx;
    }

    public function setCoordx(int $coordx): static
    {
        $this->coordx = $coordx;

        return $this;
    }

    public function getCoordy(): ?int
    {
        return $this->coordy;
    }

    public function setCoordy(int $coordy): static
    {
        $this->coordy = $coordy;

        return $this;
    }

    public function getPrise(): ?string
    {
        return $this->prise;
    }

    public function setPrise(string $prise): static
    {
        $this->prise = $prise;

        return $this;
    }

    public function getMac(): ?string
    {
        return $this->mac;
    }

    public function setMac(?string $mac): static
    {
        $this->mac = $mac;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isSanctuaire(): ?bool
    {
        return $this->sanctuaire;
    }

    public function setSanctuaire(bool $sanctuaire): static
    {
        $this->sanctuaire = $sanctuaire;

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
     * @return Collection<int, Materiel>
     */
    public function getMateriels(): Collection
    {
        return $this->materiels;
    }

    public function addMateriel(Materiel $materiel): static
    {
        if (!$this->materiels->contains($materiel)) {
            $this->materiels->add($materiel);
            $materiel->setPosition($this);
        }

        return $this;
    }

    public function removeMateriel(Materiel $materiel): static
    {
        if ($this->materiels->removeElement($materiel)) {
            // set the owning side to null (unless already changed)
            if ($materiel->getPosition() === $this) {
                $materiel->setPosition(null);
            }
        }

        return $this;
    }

    public function getAgentPosition(): ?AgentPosition
    {
        return $this->agentPosition;
    }

    public function setAgentPosition(AgentPosition $agentPosition): static
    {
        // set the owning side of the relation if necessary
        if ($agentPosition->getPosition() !== $this) {
            $agentPosition->setPosition($this);
        }

        $this->agentPosition = $agentPosition;

        return $this;
    }
}
