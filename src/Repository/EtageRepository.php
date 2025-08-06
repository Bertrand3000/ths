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

    /**
     * Recherche les étages en fonction de critères, avec tri.
     * @return Etage[]
     */
    public function search(?string $q, string $sort, string $direction, ?int $siteId): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.site', 's')
            ->addSelect('s');

        if ($q) {
            $qb->andWhere('e.nom LIKE :q OR s.nom LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($siteId) {
            $qb->andWhere('e.site = :siteId')
                ->setParameter('siteId', $siteId);
        }

        if ($sort && $direction) {
            // Valider les champs de tri pour éviter l'injection SQL
            $allowedSorts = ['e.id', 'e.nom', 's.nom'];
            if (in_array($sort, $allowedSorts)) {
                $qb->orderBy($sort, $direction);
            }
        }

        return $qb->getQuery()->getResult();
    }
}
