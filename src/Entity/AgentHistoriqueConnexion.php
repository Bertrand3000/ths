<?php

namespace App\Entity;

use App\Repository\AgentHistoriqueConnexionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AgentHistoriqueConnexionRepository::class)]
class AgentHistoriqueConnexion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['historique:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'agentHistoriqueConnexions')]
    #[ORM\JoinColumn(name: 'numagent', referencedColumnName: 'numagent', nullable: false)]
    #[Groups(['historique:read'])]
    private ?Agent $agent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['historique:read'])]
    private ?Position $position = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['historique:read'])]
    private ?\DateTimeInterface $jour = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['historique:read'])]
    private ?\DateTimeInterface $dateconnexion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['historique:read'])]
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
