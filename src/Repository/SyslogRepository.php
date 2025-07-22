<?php

namespace App\Repository;

use App\Entity\Syslog;
use App\Entity\Switch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Syslog>
 *
 * @method Syslog|null find($id, $lockMode = null, $lockVersion = null)
 * @method Syslog|null findOneBy(array $criteria, array $orderBy = null)
 * @method Syslog[]    findAll()
 * @method Syslog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SyslogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Syslog::class);
    }

    /**
     * @return Syslog[]
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
     * @param Switch $switch
     * @return Syslog[]
     */
    public function findBySwitch(Switch $switch): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.switch = :switch')
            ->setParameter('switch', $switch)
            ->orderBy('s.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
