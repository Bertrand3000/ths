<?php

namespace App\Repository;

use App\Entity\Etage;
use App\Entity\Switch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Switch>
 *
 * @method Switch|null find($id, $lockMode = null, $lockVersion = null)
 * @method Switch|null findOneBy(array $criteria, array $orderBy = null)
 * @method Switch[]    findAll()
 * @method Switch[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SwitchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Switch::class);
    }

    /**
     * @param Etage $etage
     * @return Switch[]
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
     * @param string $name
     * @return Switch|null
     */
    public function findByName(string $name): ?Switch
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.nom = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
