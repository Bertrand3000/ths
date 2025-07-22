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
     * @return Position[]
     */
    public function findAvailablePositions(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.agentPosition', 'ap')
            ->andWhere('ap.agent IS NULL')
            ->andWhere('p.flex = :flex')
            ->setParameter('flex', true)
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
