<?php

namespace App\Entity;

use App\Repository\AgentConnexionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Enum\TypeConnexion;

#[ORM\Entity(repositoryClass: AgentConnexionRepository::class)]
class AgentConnexion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'agentConnexions')]
    #[ORM\JoinColumn(name: 'numagent', referencedColumnName: 'numagent', nullable: false)]
    private ?Agent $agent = null;

    #[ORM\Column(type: 'string', enumType: TypeConnexion::class)]
    private ?TypeConnexion $type = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 17, nullable: true)]
    private ?string $mac = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $dateconnexion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $dateactualisation = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $status = null;

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

    public function getType(): ?TypeConnexion
    {
        return $this->type;
    }

    public function setType(TypeConnexion $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): static
    {
        $this->ip = $ip;

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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }
}
