<?php

namespace App\Repository;

use App\Entity\Etage;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 *
 * @method Service|null find($id, $lockMode = null, $lockVersion = null)
 * @method Service|null findOneBy(array $criteria, array $orderBy = null)
 * @method Service[]    findAll()
 * @method Service[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * @param Etage $etage
     * @return Service[]
     */
    public function findByEtage(Etage $etage): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.etage = :etage')
            ->setParameter('etage', $etage)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Service[]
     */
    public function findWithAgentsCount(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.agents', 'a')
            ->addSelect('COUNT(a.numagent) as agentsCount')
            ->groupBy('s.id')
            ->getQuery()
            ->getResult();
    }
}
