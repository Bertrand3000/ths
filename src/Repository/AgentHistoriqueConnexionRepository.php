<?php

namespace App\Repository;

use App\Entity\AgentHistoriqueConnexion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgentHistoriqueConnexion>
 *
 * @method AgentHistoriqueConnexion|null find($id, $lockMode = null, $lockVersion = null)
 * @method AgentHistoriqueConnexion|null findOneBy(array $criteria, array $orderBy = null)
 * @method AgentHistoriqueConnexion[]    findAll()
 * @method AgentHistoriqueConnexion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AgentHistoriqueConnexionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentHistoriqueConnexion::class);
    }

    /**
     * @return AgentHistoriqueConnexion[]
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.dateconnexion BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('h.dateconnexion', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
