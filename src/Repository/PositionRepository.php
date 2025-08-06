<?php

namespace App\Repository;

use App\Entity\Position;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Position>
 *
 * @method Position|null find($id, $lockMode = null, $lockVersion = null)
 * @method Position|null findOneBy(array $criteria, array $orderBy = null)
 * @method Position[]    findAll()
 * @method Position[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Position::class);
    }

    /**
     * @param \App\Entity\Etage|null $etage
     * @param \App\Entity\Service|null $service
     * @param string|null $type
     * @return Position[]
     */
    public function findAvailablePositionsFiltered(\App\Entity\Etage $etage = null, \App\Entity\Service $service = null, string $type = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p', 'e', 's')
            ->leftJoin('p.agentPosition', 'ap')
            ->join('p.etage', 'e')
            ->join('p.service', 's')
            ->where('ap.agent IS NULL')
            ->andWhere('p.flex = :flex')
            ->setParameter('flex', true);

        if ($etage) {
            $qb->andWhere('p.etage = :etage')
                ->setParameter('etage', $etage);
        }

        if ($service) {
            $qb->andWhere('p.service = :service')
                ->setParameter('service', $service);
        }

        if ($type) {
            $qb->andWhere('p.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->orderBy('e.nom', 'ASC')
            ->addOrderBy('s.nom', 'ASC')
            ->addOrderBy('p.prise', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $x
     * @param int $y
     * @return Position|null
     */
    public function findByCoordinates(int $x, int $y): ?Position
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.coordx = :x')
            ->andWhere('p.coordy = :y')
            ->setParameter('x', $x)
            ->setParameter('y', $y)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Position[]
     */
    public function findFlexPositions(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.flex = :flex')
            ->setParameter('flex', true)
            ->getQuery()
            ->getResult();
    }
}
