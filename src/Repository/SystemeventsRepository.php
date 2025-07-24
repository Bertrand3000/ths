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
     * Récupère les nouveaux événements depuis le dernier ID traité.
     *
     * @param int $lastId L'ID du dernier événement traité.
     * @return Systemevents[]
     */
    public function findNewEvents(int $lastId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime les événements dont l'ID est inférieur ou égal à l'ID fourni.
     *
     * @param int $maxId L'ID maximum des événements à supprimer.
     * @return int Le nombre d'événements supprimés.
     */
    public function deleteOldEvents(int $maxId): int
    {
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.id <= :maxId')
            ->setParameter('maxId', $maxId)
            ->getQuery()
            ->execute();
    }
}
