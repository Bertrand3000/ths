<?php

namespace App\Repository;

use App\Entity\AgentConnexion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgentConnexion>
 *
 * @method AgentConnexion|null find($id, $lockMode = null, $lockVersion = null)
 * @method AgentConnexion|null findOneBy(array $criteria, array $orderBy = null)
 * @method AgentConnexion[]    findAll()
 * @method AgentConnexion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AgentConnexionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentConnexion::class);
    }

    /**
     * @return AgentConnexion[]
     */
    public function findExpiredConnections(\DateTimeInterface $timeout): array
    {
        return $this->createQueryBuilder('ac')
            ->andWhere('ac.dateactualisation < :timeout')
            ->setParameter('timeout', $timeout)
            ->getQuery()
            ->getResult();
    }
}
