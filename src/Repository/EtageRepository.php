<?php

namespace App\Repository;

use App\Entity\Etage;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Etage>
 *
 * @method Etage|null find($id, $lockMode = null, $lockVersion = null)
 * @method Etage|null findOneBy(array $criteria, array $orderBy = null)
 * @method Etage[]    findAll()
 * @method Etage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EtageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Etage::class);
    }

    /**
     * @param Site $site
     * @return Etage[]
     */
    public function findBySite(Site $site): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.site = :site')
            ->setParameter('site', $site)
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Etage[]
     */
    public function findWithDimensions(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.largeur > 0')
            ->andWhere('e.hauteur > 0')
            ->getQuery()
            ->getResult();
    }
}
