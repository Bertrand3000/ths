<?php

namespace App\Repository;

use App\Entity\Agent;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Agent>
 *
 * @method Agent|null find($id, $lockMode = null, $lockVersion = null)
 * @method Agent|null findOneBy(array $criteria, array $orderBy = null)
 * @method Agent[]    findAll()
 * @method Agent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AgentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agent::class);
    }

    /**
     * @param Service $service
     * @return Agent[]
     */
    public function findByService(Service $service): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.service = :service')
            ->setParameter('service', $service)
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $name
     * @return Agent[]
     */
    public function searchByName(string $name): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.nom LIKE :name OR a.prenom LIKE :name')
            ->setParameter('name', '%' . $name . '%')
            ->orderBy('a.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
