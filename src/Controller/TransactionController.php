<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Form\TransactionType;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/transaction')]
final class TransactionController extends AbstractController
{
    #[Route(name: 'app_transaction_index', methods: ['GET'])]
    public function index(Request $request, TransactionRepository $transactionRepository): Response
    {
        $user = $this->getUser();
        
        // Получаем параметры фильтра из запроса
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        
        // Преобразуем даты в DateTime объекты
        $startDate = $startDate ? new \DateTime($startDate) : new \DateTime('first day of this month');
        $endDate = $endDate ? new \DateTime($endDate) : new \DateTime('last day of this month');
        
        // Устанавливаем время для корректного фильтра по датам
        $startDate->setTime(0, 0, 0);
        $endDate->setTime(23, 59, 59);
        
        // Получаем транзакции за период
        $transactions = $transactionRepository->findByUserAndDateRange($user, $startDate, $endDate);
        
        // Получаем статистику по категориям
        $categoryStats = $transactionRepository->getCategoryStats($user, $startDate, $endDate);

        // Считаем общие суммы ПРАВИЛЬНО
        $totalIncome = 0;
        $totalExpense = 0;
        
        foreach ($categoryStats as $stat) {
            if ($stat['type'] === 'income') {
                $totalIncome += $stat['total'];
            } else {
                // Для расходов берем абсолютное значение, так как в базе они отрицательные
                $totalExpense += abs($stat['total']);
            }
        }

        return $this->render('transaction/index.html.twig', [
            'transactions' => $transactions,
            'category_stats' => $categoryStats,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
        ]);
    }

    // Остальные методы остаются без изменений...
  #[Route('/new', name: 'app_transaction_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $transaction = new Transaction();
        $form = $this->createForm(TransactionType::class, $transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Устанавливаем текущего пользователя
            $transaction->setUser($this->getUser());
            
            // АВТОМАТИЧЕСКИ УСТАНАВЛИВАЕМ ЗНАК СУММЫ НА ОСНОВЕ ТИПА КАТЕГОРИИ
            $amount = $transaction->getAmount();
            $categoryType = $transaction->getCategory()->getType();
            
            if ($categoryType === 'expense' && $amount > 0) {
                $transaction->setAmount(-abs($amount));
            } elseif ($categoryType === 'income' && $amount < 0) {
                $transaction->setAmount(abs($amount));
            }
            
            $entityManager->persist($transaction);
            $entityManager->flush();

            return $this->redirectToRoute('app_transaction_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('transaction/new.html.twig', [
            'transaction' => $transaction,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_transaction_show', methods: ['GET'])]
    public function show(Transaction $transaction): Response
    {
        // Проверяем, что пользователь просматривает свою транзакцию
        if ($transaction->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('transaction/show.html.twig', [
            'transaction' => $transaction,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_transaction_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Transaction $transaction, EntityManagerInterface $entityManager): Response
    {
        // Проверяем, что пользователь редактирует свою транзакцию
        if ($transaction->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(TransactionType::class, $transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_transaction_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('transaction/edit.html.twig', [
            'transaction' => $transaction,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_transaction_delete', methods: ['POST'])]
    public function delete(Request $request, Transaction $transaction, EntityManagerInterface $entityManager): Response
    {
        // Проверяем, что пользователь удаляет свою транзакцию
        if ($transaction->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$transaction->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($transaction);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_transaction_index', [], Response::HTTP_SEE_OTHER);
    }
}