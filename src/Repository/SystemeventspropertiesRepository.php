<?php

namespace App\Repository;

use App\Entity\Systemeventsproperties;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Systemeventsproperties>
 *
 * @method Systemeventsproperties|null find($id, $lockMode = null, $lockVersion = null)
 * @method Systemeventsproperties|null findOneBy(array $criteria, array $orderBy = null)
 * @method Systemeventsproperties[]    findAll()
 * @method Systemeventsproperties[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SystemeventspropertiesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Systemeventsproperties::class);
    }
}
