<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use App\Repository\TransactionRepository;
use App\Enum\CategoryType;

class DashboardController extends AbstractController
{
    public function index(TransactionRepository $transactionRepo): Response
    {
        $user = $this->getUser();
        
        $recentTransactions = [];
        $expenseAmount = 0;
        $incomeAmount = 0;

        if ($user) {
            $recentTransactions = $transactionRepo->findBy(
                ['user' => $user],
                ['date' => 'DESC'],
                5
            );
            $recentTransactions = $transactionRepo->findRecentByUser($user, 5);
            $expenseAmount = $transactionRepo->getSumByCategoryType($user, CategoryType::EXPENSE->value);
            $incomeAmount = $transactionRepo->getSumByCategoryType($user, CategoryType::INCOME->value);
        }

        return $this->render('dashboard/index.html.twig', [
            'recent_transactions' => $recentTransactions,
            'expense_amount' => $expenseAmount,
            'income_amount' => $incomeAmount
        ]);


    }
}