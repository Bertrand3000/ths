<?php

namespace App\Entity;

use App\Repository\AgentPositionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentPositionRepository::class)]
class AgentPosition
{
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'agentPosition', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'numagent', referencedColumnName: 'numagent', nullable: false)]
    private ?Agent $agent = null;

    #[ORM\OneToOne(inversedBy: 'agentPosition', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Position $position = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $jour = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateconnexion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateactualisation = null;

    #[ORM\Column(length: 15)]
    private ?string $ip = null;

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

    public function getDateactualisation(): ?\DateTimeInterface
    {
        return $this->dateactualisation;
    }

    public function setDateactualisation(\DateTimeInterface $dateactualisation): static
    {
        $this->dateactualisation = $dateactualisation;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }
}
