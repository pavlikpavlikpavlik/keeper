<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    // Метод для поиска транзакций конкретного пользователя
    public function findByUser(User $user)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentByUser(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getSumByCategoryType(User $user, string $type): float
    {
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount) as total')
            ->join('t.category', 'c')
            ->andWhere('t.user = :user')
            ->andWhere('c.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }

    public function findByUserAndDateRange(User $user, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.date BETWEEN :start_date AND :end_date')
            ->setParameter('user', $user)
            ->setParameter('start_date', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('end_date', $endDate->format('Y-m-d 23:59:59'))
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getCategoryStats(User $user, \DateTime $startDate, \DateTime $endDate): array
    {
        $results = $this->createQueryBuilder('t')
            ->select('c.name as category_name', 'c.type as type', 'c.color as color', 'SUM(t.amount) as total')
            ->join('t.category', 'c')
            ->andWhere('t.user = :user')
            ->andWhere('t.date BETWEEN :start_date AND :end_date')
            ->setParameter('user', $user)
            ->setParameter('start_date', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('end_date', $endDate->format('Y-m-d 23:59:59'))
            ->groupBy('c.id', 'c.name', 'c.type', 'c.color')
            ->orderBy('c.type', 'DESC') // Сначала доходы, потом расходы
            ->addOrderBy('ABS(SUM(t.amount))', 'DESC') // Сортируем по абсолютной сумме
            ->getQuery()
            ->getResult();

        // Обрабатываем результаты - для расходов должны быть отрицательные суммы
        foreach ($results as &$result) {
            if ($result['type'] instanceof \BackedEnum) {
                $result['type'] = $result['type']->value;
            }
            
            // Если это расход (expense), но сумма положительная - делаем отрицательной
            if ($result['type'] === 'expense' && $result['total'] > 0) {
                $result['total'] = -$result['total'];
            }
            
            // Если это доход (income), но сумма отрицательная - делаем положительной
            if ($result['type'] === 'income' && $result['total'] < 0) {
                $result['total'] = abs($result['total']);
            }
        }

        return $results;
    }
}