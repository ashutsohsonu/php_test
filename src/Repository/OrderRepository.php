<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $order, bool $flush = true): void
    {
        $this->getEntityManager()->persist($order);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function countActiveOrders(): int
    {
        return $this->count(['status' => 'active']);
    }

    public function countActiveNonVipOrders(): int
    {
        return $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status = :status')
            ->andWhere('o.vip = :vip')
            ->setParameter('status', 'active')
            ->setParameter('vip', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findActiveOrders(): array
    {
        return $this->findBy(['status' => 'active'], ['createdAt' => 'ASC']);
    }

    public function findOldestActiveNonVipOrder(): ?Order
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.vip = :vip')
            ->setParameter('status', 'active')
            ->setParameter('vip', false)
            ->orderBy('o.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }


      public function findPendingOrders()
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.vip = :vip')
            ->setParameter('status', 'pending')
            ->setParameter('vip', false)
            ->orderBy('o.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }


    public function getLatestPickupTime(): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('o')
            ->select('o.pickupTime')
            ->where('o.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('o.pickupTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? \DateTimeImmutable::createFromMutable($result['pickupTime']) : null;
    }


}