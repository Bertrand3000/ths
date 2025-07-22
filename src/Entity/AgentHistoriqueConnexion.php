<?php

namespace App\Entity;

use App\Repository\AgentHistoriqueConnexionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentHistoriqueConnexionRepository::class)]
class AgentHistoriqueConnexion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'agentHistoriqueConnexions')]
    #[ORM\JoinColumn(name: 'numagent', referencedColumnName: 'numagent', nullable: false)]
    private ?Agent $agent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Position $position = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $jour = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateconnexion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $datedeconnexion = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    public function setAgent(?Agent $agent): static
    {
        $this->agent = $agent;

        return $this;
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

    public function getDatedeconnexion(): ?\DateTimeInterface
    {
        return $this->datedeconnexion;
    }

    public function setDatedeconnexion(?\DateTimeInterface $datedeconnexion): static
    {
        $this->datedeconnexion = $datedeconnexion;

        return $this;
    }
}
