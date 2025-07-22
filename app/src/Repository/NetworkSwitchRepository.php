<?php

namespace App\Repository;

use App\Entity\Etage;
use App\Entity\NetworkSwitch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NetworkSwitch>
 *
 * @method NetworkSwitch|null find($id, $lockMode = null, $lockVersion = null)
 * @method NetworkSwitch|null findOneBy(array $criteria, array $orderBy = null)
 * @method NetworkSwitch[]    findAll()
 * @method NetworkSwitch[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NetworkSwitchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NetworkSwitch::class);
    }

    /**
     * @param Etage $etage
     * @return NetworkSwitch[]
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
     * @return NetworkSwitch|null
     */
    public function findByName(string $name): ?NetworkSwitch
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.nom = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
