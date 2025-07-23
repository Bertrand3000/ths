<?php

namespace App\Repository;

use App\Entity\Log;
use App\Entity\NetworkSwitch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Log>
 *
 * @method Log|null find($id, $lockMode = null, $lockVersion = null)
 * @method Log|null findOneBy(array $criteria, array $orderBy = null)
 * @method Log[]    findAll()
 * @method Log[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Log::class);
    }

    /**
     * @return Log[]
     */
    public function findRecentMessages(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.timestamp', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param NetworkSwitch $switch
     * @return Log[]
     */
    public function findBySwitch(NetworkSwitch $switch): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.switch = :switch')
            ->setParameter('switch', $switch)
            ->orderBy('s.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
