<?php

namespace App\Repository;

use App\Entity\AgentPosition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgentPosition>
 *
 * @method AgentPosition|null find($id, $lockMode = null, $lockVersion = null)
 * @method AgentPosition|null findOneBy(array $criteria, array $orderBy = null)
 * @method AgentPosition[]    findAll()
 * @method AgentPosition[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AgentPositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentPosition::class);
    }

    /**
     * @return AgentPosition[]
     */
    public function findCurrentPositions(): array
    {
        return $this->createQueryBuilder('ap')
            ->andWhere('ap.dateactualisation > :date')
            ->setParameter('date', new \DateTime('-15 minutes'))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AgentPosition[]
     */
    public function findExpiredPositions(): array
    {
        return $this->createQueryBuilder('ap')
            ->andWhere('ap.dateactualisation < :date')
            ->setParameter('date', new \DateTime('-15 minutes'))
            ->getQuery()
            ->getResult();
    }
}
