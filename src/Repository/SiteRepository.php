<?php

namespace App\Repository;

use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Site>
 *
 * @method Site|null find($id, $lockMode = null, $lockVersion = null)
 * @method Site|null findOneBy(array $criteria, array $orderBy = null)
 * @method Site[]    findAll()
 * @method Site[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    /**
     * @return Site[] Returns an array of Site objects
     */
    public function findFlexSites(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.flex = :flex')
            ->setParameter('flex', true)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param string|null $q
     * @param string $sort
     * @param string $direction
     * @return Site[]
     */
    public function search(?string $q, string $sort, string $direction): array
    {
        $qb = $this->createQueryBuilder('s');

        if ($q) {
            $qb->andWhere('s.nom LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        $qb->orderBy($sort, $direction);

        return $qb->getQuery()->getResult();
    }
}
