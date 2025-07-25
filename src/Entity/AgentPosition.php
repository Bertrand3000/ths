<?php

namespace App\Entity;

use App\Repository\AgentPositionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentPositionRepository::class)]
class AgentPosition
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Agent::class)]
    #[ORM\JoinColumn(name: 'numagent', referencedColumnName: 'numagent', nullable: false)]
    private ?Agent $agent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'idposition', referencedColumnName: 'id', nullable: false)]
    private ?Position $position = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $jour = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateconnexion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateexpiration = null;

    /**
     * Met à jour la date d'expiration à 8 heures à partir de maintenant.
     */
    public function updateExpiration(): void
    {
        $this->dateexpiration = new \DateTime('+8 hours');
    }

    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    public function setAgent(Agent $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }

    public function setPosition(Position $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getJour(): ?\DateTimeInterface
    {
        return $this->jour;
    }

    public function setJour(\DateTimeInterface $jour): static
    {
        $this->jour = $jour;

        return $this;
    }

    public function getDateconnexion(): ?\DateTimeInterface
    {
        return $this->dateconnexion;
    }

    public function setDateconnexion(\DateTimeInterface $dateconnexion): static
    {
        $this->dateconnexion = $dateconnexion;

        return $this;
    }

    public function getDateexpiration(): ?\DateTimeInterface
    {
        return $this->dateexpiration;
    }

    public function setDateexpiration(?\DateTimeInterface $dateexpiration): static
    {
        $this->dateexpiration = $dateexpiration;

        return $this;
    }
}
