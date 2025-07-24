<?php

namespace App\Entity;

use App\Repository\SystemeventsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemeventsRepository::class)]
class Systemevents
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $customerid = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $receivedat = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $devicereportedtime = null;

    #[ORM\Column(nullable: true)]
    private ?int $facility = null;

    #[ORM\Column(nullable: true)]
    private ?int $priority = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $fromhost = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(nullable: true)]
    private ?int $ntseverity = null;

    #[ORM\Column(nullable: true)]
    private ?int $importance = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $eventsource = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $eventuser = null;

    #[ORM\Column(nullable: true)]
    private ?int $eventcategory = null;

    #[ORM\Column(nullable: true)]
    private ?int $eventid = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $eventbinarydata = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxavailable = null;

    #[ORM\Column(nullable: true)]
    private ?int $currusage = null;

    #[ORM\Column(nullable: true)]
    private ?int $minusage = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxusage = null;

    #[ORM\Column(nullable: true)]
    private ?int $infounitid = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $syslogtag = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $eventlogtype = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $genericfilename = null;

    #[ORM\Column(nullable: true)]
    private ?int $systemid = null;

    #[ORM\OneToMany(mappedBy: 'systemevent', targetEntity: Systemeventsproperties::class)]
    private Collection $systemeventsproperties;

    public function __construct()
    {
        $this->systemeventsproperties = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerid(): ?int
    {
        return $this->customerid;
    }

    public function setCustomerid(?int $customerid): static
    {
        $this->customerid = $customerid;

        return $this;
    }

    public function getSyslogtag(): ?string
    {
        return $this->syslogtag;
    }

    public function setSyslogtag(?string $syslogtag): static
    {
        $this->syslogtag = $syslogtag;

        return $this;
    }

    public function getReceivedat(): ?\DateTimeInterface
    {
        return $this->receivedat;
    }

    public function setReceivedat(?\DateTimeInterface $receivedat): static
    {
        $this->receivedat = $receivedat;

        return $this;
    }

    public function getDevicereportedtime(): ?\DateTimeInterface
    {
        return $this->devicereportedtime;
    }

    public function setDevicereportedtime(?\DateTimeInterface $devicereportedtime): static
    {
        $this->devicereportedtime = $devicereportedtime;

        return $this;
    }

    public function getFacility(): ?int
    {
        return $this->facility;
    }

    public function setFacility(?int $facility): static
    {
        $this->facility = $facility;

        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(?int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getFromhost(): ?string
    {
        return $this->fromhost;
    }

    public function setFromhost(?string $fromhost): static
    {
        $this->fromhost = $fromhost;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getNtseverity(): ?int
    {
        return $this->ntseverity;
    }

    public function setNtseverity(?int $ntseverity): static
    {
        $this->ntseverity = $ntseverity;

        return $this;
    }

    public function getImportance(): ?int
    {
        return $this->importance;
    }

    public function setImportance(?int $importance): static
    {
        $this->importance = $importance;

        return $this;
    }

    public function getEventsource(): ?string
    {
        return $this->eventsource;
    }

    public function setEventsource(?string $eventsource): static
    {
        $this->eventsource = $eventsource;

        return $this;
    }

    public function getEventuser(): ?string
    {
        return $this->eventuser;
    }

    public function setEventuser(?string $eventuser): static
    {
        $this->eventuser = $eventuser;

        return $this;
    }

    public function getEventcategory(): ?int
    {
        return $this->eventcategory;
    }

    public function setEventcategory(?int $eventcategory): static
    {
        $this->eventcategory = $eventcategory;

        return $this;
    }

    public function getEventid(): ?int
    {
        return $this->eventid;
    }

    public function setEventid(?int $eventid): static
    {
        $this->eventid = $eventid;

        return $this;
    }

    public function getEventbinarydata(): ?string
    {
        return $this->eventbinarydata;
    }

    public function setEventbinarydata(?string $eventbinarydata): static
    {
        $this->eventbinarydata = $eventbinarydata;

        return $this;
    }

    public function getMaxavailable(): ?int
    {
        return $this->maxavailable;
    }

    public function setMaxavailable(?int $maxavailable): static
    {
        $this->maxavailable = $maxavailable;

        return $this;
    }

    public function getCurrusage(): ?int
    {
        return $this->currusage;
    }

    public function setCurrusage(?int $currusage): static
    {
        $this->currusage = $currusage;

        return $this;
    }

    public function getMinusage(): ?int
    {
        return $this->minusage;
    }

    public function setMinusage(?int $minusage): static
    {
        $this->minusage = $minusage;

        return $this;
    }

    public function getMaxusage(): ?int
    {
        return $this->maxusage;
    }

    public function setMaxusage(?int $maxusage): static
    {
        $this->maxusage = $maxusage;

        return $this;
    }

    public function getInfounitid(): ?int
    {
        return $this->infounitid;
    }

    public function setInfounitid(?int $infounitid): static
    {
        $this->infounitid = $infounitid;

        return $this;
    }

    public function getEventlogtype(): ?string
    {
        return $this->eventlogtype;
    }

    public function setEventlogtype(?string $eventlogtype): static
    {
        $this->eventlogtype = $eventlogtype;

        return $this;
    }

    public function getGenericfilename(): ?string
    {
        return $this->genericfilename;
    }

    public function setGenericfilename(?string $genericfilename): static
    {
        $this->genericfilename = $genericfilename;

        return $this;
    }

    public function getSystemid(): ?int
    {
        return $this->systemid;
    }

    public function setSystemid(?int $systemid): static
    {
        $this->systemid = $systemid;

        return $this;
    }

    /**
     * @return Collection<int, Systemeventsproperties>
     */
    public function getSystemeventsproperties(): Collection
    {
        return $this->systemeventsproperties;
    }

    public function addSystemeventsproperty(Systemeventsproperties $systemeventsproperty): static
    {
        if (!$this->systemeventsproperties->contains($systemeventsproperty)) {
            $this->systemeventsproperties->add($systemeventsproperty);
            $systemeventsproperty->setSystemevent($this);
        }

        return $this;
    }

    public function removeSystemeventsproperty(Systemeventsproperties $systemeventsproperty): static
    {
        if ($this->systemeventsproperties->removeElement($systemeventsproperty)) {
            // set the owning side to null (unless already changed)
            if ($systemeventsproperty->getSystemevent() === $this) {
                $systemeventsproperty->setSystemevent(null);
            }
        }

        return $this;
    }
}
