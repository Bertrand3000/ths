<?php

namespace App\Entity;

use App\Repository\NetworkSwitchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NetworkSwitchRepository::class)]
#[ORM\Table(name: '`network_switch`')]
class NetworkSwitch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'switches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Etage $etage = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column]
    private ?int $coordx = null;

    #[ORM\Column]
    private ?int $coordy = null;

    #[ORM\Column]
    private ?int $nbprises = null;

    #[ORM\OneToMany(mappedBy: 'networkSwitch', targetEntity: Position::class)]
    private Collection $positions;

    #[ORM\OneToMany(mappedBy: 'switch', targetEntity: Log::class)]
    private Collection $logs;

    public function __construct()
    {
        $this->positions = new ArrayCollection();
        $this->logs = new ArrayCollection();
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

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

    public function getNbprises(): ?int
    {
        return $this->nbprises;
    }

    public function setNbprises(int $nbprises): static
    {
        $this->nbprises = $nbprises;

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
            $position->setNetworkSwitch($this);
        }

        return $this;
    }

    public function removePosition(Position $position): static
    {
        if ($this->positions->removeElement($position)) {
            // set the owning side to null (unless already changed)
            if ($position->getNetworkSwitch() === $this) {
                $position->setNetworkSwitch(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Log>
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(Log $log): static
    {
        if (!$this->logs->contains($log)) {
            $this->logs->add($log);
            $log->setSwitch($this);
        }

        return $this;
    }

    public function removeLog(Log $log): static
    {
        if ($this->logs->removeElement($log)) {
            // set the owning side to null (unless already changed)
            if ($log->getSwitch() === $this) {
                $log->setSwitch(null);
            }
        }

        return $this;
    }
}
