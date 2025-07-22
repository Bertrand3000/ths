<?php

namespace App\Repository;

use App\Entity\Systemevents;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Systemevents>
 *
 * @method Systemevents|null find($id, $lockMode = null, $lockVersion = null)
 * @method Systemevents|null findOneBy(array $criteria, array $orderBy = null)
 * @method Systemevents[]    findAll()
 * @method Systemevents[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SystemeventsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Systemevents::class);
    }

    /**
     * @return Systemevents[]
     */
    public function findConnectionEvents(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.message LIKE :connexion')
            ->setParameter('connexion', '%CREATE_NEIGHBOR%')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $tag
     * @return Systemevents[]
     */
    public function findBySyslogTag(string $tag): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.syslogtag = :tag')
            ->setParameter('tag', $tag)
            ->getQuery()
            ->getResult();
    }
}
