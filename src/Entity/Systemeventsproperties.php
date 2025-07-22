<?php

namespace App\Entity;

use App\Repository\SystemeventspropertiesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemeventspropertiesRepository::class)]
class Systemeventsproperties
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'systemeventsproperties')]
    #[ORM\JoinColumn(name: 'systemeventid', referencedColumnName: 'id')]
    private ?Systemevents $systemevent = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paramname = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $paramvalue = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSystemevent(): ?Systemevents
    {
        return $this->systemevent;
    }

    public function setSystemevent(?Systemevents $systemevent): static
    {
        $this->systemevent = $systemevent;

        return $this;
    }

    public function getParamname(): ?string
    {
        return $this->paramname;
    }

    public function setParamname(?string $paramname): static
    {
        $this->paramname = $paramname;

        return $this;
    }

    public function getParamvalue(): ?string
    {
        return $this->paramvalue;
    }

    public function setParamvalue(?string $paramvalue): static
    {
        $this->paramvalue = $paramvalue;

        return $this;
    }
}
