<?php

namespace App\Entity;

use App\Repository\AgentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentRepository::class)]
class Agent
{
    #[ORM\Id]
    #[ORM\Column(length: 5)]
    private ?string $numagent = null;

    #[ORM\ManyToOne(inversedBy: 'agents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Service $service = null;

    #[ORM\Column(length: 4)]
    private ?string $civilite = null;

    #[ORM\Column(length: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\OneToOne(mappedBy: 'agent', cascade: ['persist', 'remove'])]
    private ?AgentPosition $agentPosition = null;

    #[ORM\OneToMany(mappedBy: 'agent', targetEntity: AgentHistoriqueConnexion::class)]
    private Collection $agentHistoriqueConnexions;

    #[ORM\OneToMany(mappedBy: 'agent', targetEntity: AgentConnexion::class)]
    private Collection $agentConnexions;

    public function __construct()
    {
        $this->agentHistoriqueConnexions = new ArrayCollection();
        $this->agentConnexions = new ArrayCollection();
    }

    public function getNumagent(): ?string
    {
        return $this->numagent;
    }

    public function setNumagent(string $numagent): static
    {
        $this->numagent = $numagent;

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

    public function getCivilite(): ?string
    {
        return $this->civilite;
    }

    public function setCivilite(string $civilite): static
    {
        $this->civilite = $civilite;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

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

    public function getAgentPosition(): ?AgentPosition
    {
        return $this->agentPosition;
    }

    public function setAgentPosition(AgentPosition $agentPosition): static
    {
        // set the owning side of the relation if necessary
        if ($agentPosition->getAgent() !== $this) {
            $agentPosition->setAgent($this);
        }

        $this->agentPosition = $agentPosition;

        return $this;
    }

    /**
     * @return Collection<int, AgentHistoriqueConnexion>
     */
    public function getAgentHistoriqueConnexions(): Collection
    {
        return $this->agentHistoriqueConnexions;
    }

    public function addAgentHistoriqueConnexion(AgentHistoriqueConnexion $agentHistoriqueConnexion): static
    {
        if (!$this->agentHistoriqueConnexions->contains($agentHistoriqueConnexion)) {
            $this->agentHistoriqueConnexions->add($agentHistoriqueConnexion);
            $agentHistoriqueConnexion->setAgent($this);
        }

        return $this;
    }

    public function removeAgentHistoriqueConnexion(AgentHistoriqueConnexion $agentHistoriqueConnexion): static
    {
        if ($this->agentHistoriqueConnexions->removeElement($agentHistoriqueConnexion)) {
            // set the owning side to null (unless already changed)
            if ($agentHistoriqueConnexion->getAgent() === $this) {
                $agentHistoriqueConnexion->setAgent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AgentConnexion>
     */
    public function getAgentConnexions(): Collection
    {
        return $this->agentConnexions;
    }

    public function addAgentConnexion(AgentConnexion $agentConnexion): static
    {
        if (!$this->agentConnexions->contains($agentConnexion)) {
            $this->agentConnexions->add($agentConnexion);
            $agentConnexion->setAgent($this);
        }

        return $this;
    }

    public function removeAgentConnexion(AgentConnexion $agentConnexion): static
    {
        if ($this->agentConnexions->removeElement($agentConnexion)) {
            // set the owning side to null (unless already changed)
            if ($agentConnexion->getAgent() === $this) {
                $agentConnexion->setAgent(null);
            }
        }

        return $this;
    }
}
