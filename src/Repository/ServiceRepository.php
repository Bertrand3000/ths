<?php

namespace App\Repository;

use App\Entity\Etage;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 *
 * @method Service|null find($id, $lockMode = null, $lockVersion = null)
 * @method Service|null findOneBy(array $criteria, array $orderBy = null)
 * @method Service[]    findAll()
 * @method Service[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * @param Etage $etage
     * @return Service[]
     */
    public function findByEtage(Etage $etage): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.etage = :etage')
            ->setParameter('etage', $etage)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Service[]
     */
    public function findWithAgentsCount(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.agents', 'a')
            ->addSelect('COUNT(a.numagent) as agentsCount')
            ->groupBy('s.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les services en fonction de critères, avec tri.
     * @return Service[]
     */
    public function search(?string $q, string $sort, string $direction, ?int $etageId, ?int $siteId): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.etage', 'e')
            ->addSelect('e')
            ->leftJoin('e.site', 'si')
            ->addSelect('si');

        if ($q) {
            $qb->andWhere('s.nom LIKE :q OR e.nom LIKE :q OR si.nom LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($etageId) {
            $qb->andWhere('s.etage = :etageId')
                ->setParameter('etageId', $etageId);
        }

        if ($siteId) {
            $qb->andWhere('e.site = :siteId')
                ->setParameter('siteId', $siteId);
        }

        if ($sort && $direction) {
            $allowedSorts = ['s.id', 's.nom', 'e.nom', 'si.nom'];
            if (in_array($sort, $allowedSorts)) {
                $qb->orderBy($sort, $direction);
            }
        }

        return $qb->getQuery()->getResult();
    }
}
