<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Enum\CategoryType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelImportController extends AbstractController
{
    private $userCategoriesCache = [];

    #[Route('/import/transactions', name: 'app_import_transactions', methods: ['POST'])]
    public function importTransactions(
        Request $request, 
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('import_transaction', $submittedToken)) {
            $this->addFlash('error', 'Недействительный CSRF токен');
            return $this->redirectToRoute('app_transaction_index');
        }
        
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $excelFile = $request->files->get('excel_file');
        $transactionType = $request->request->get('transaction_type', 'expense');

        if (!$excelFile) {
            $this->addFlash('error', 'Файл не был загружен');
            return $this->redirectToRoute('app_transaction_index');
        }

        // Сбрасываем кэш категорий
        $this->userCategoriesCache = [];

        try {
            $spreadsheet = IOFactory::load($excelFile->getPathname());
            $importedCount = 0;
            $categoriesCreated = 0;

            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $sheetName = $worksheet->getTitle();
                $highestRow = $worksheet->getHighestRow();
                
                if ($highestRow <= 1) continue;

                $result = $this->processWorksheet($worksheet, $user, $entityManager, $transactionType, $sheetName);
                $importedCount += $result['transactions'];
                $categoriesCreated += $result['categories'];
            }

            $entityManager->flush();

            if ($importedCount > 0) {
                $this->addFlash('success', 
                    sprintf('Импортировано %d транзакций. Создано %d новых категорий.', 
                    $importedCount, $categoriesCreated)
                );
            } else {
                $this->addFlash('warning', 'Не найдено данных для импорта. Проверьте формат файла.');
            }

        } catch (\Exception $e) {
            $this->addFlash('error', 'Ошибка при обработке файла: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_transaction_index');
    }

private function processWorksheet($worksheet, $user, EntityManagerInterface $entityManager, string $transactionType, string $sheetName): array
{
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    $headers = [];
    
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
    
    // Читаем заголовки
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $cellValue = $worksheet->getCell([$col, 1])->getCalculatedValue();
        $header = trim($cellValue ?? '');
        
        if (empty($header)) break;
        $headers[$col] = $header;
    }

    $dateColumn = $this->findColumnIndex($headers, ['дата', 'date']);
    if (!$dateColumn) return ['transactions' => 0, 'categories' => 0];

    $importedCount = 0;
    $categoriesCreated = 0;
    $categoryType = $transactionType === 'income' ? CategoryType::INCOME : CategoryType::EXPENSE;
    $currentDate = null;

    $this->preloadUserCategories($user, $categoryType, $entityManager);

    // ОТЛАДКА
    error_log("=== НАЧАЛО ИМПОРТА ===");

    for ($row = 2; $row <= $highestRow; $row++) {
        $dateValue = $worksheet->getCell([$dateColumn, $row])->getCalculatedValue();
        $trimmedDateValue = trim($dateValue ?? '');
        $trimmedsheetName = trim($sheetName ?? '');
        error_log("Строка {$row}: dateValue = '{$dateValue}', trimmed = '{$trimmedDateValue}'");
        
        // ПРЕРЫВАЕМ ВЫПОЛНЕНИЕ если встретили "Итог" - дальше нет данных
        if (in_array(strtolower($trimmedsheetName), ['итог', 'всего', 'total', 'сумма'])) {
            error_log("❌ НАЙДЕН ИТОГ! ПРЕРЫВАЕМ ВЫПОЛНЕНИЕ!");
            break; // Выходим из цикла полностью
        }
        
        // ЕСЛИ ЕСТЬ ДАТА - обновляем текущую дату
        if (!empty($trimmedDateValue)) {
            $parsedDate = $this->parseDate($dateValue, $worksheet->getTitle());
            if ($parsedDate) {
                $currentDate = $parsedDate;
                error_log("✅ Установлена дата: " . $currentDate->format('Y-m-d'));
            }
        }
        
        // Пропускаем строки если нет даты (только в начале файла)
        if (!$currentDate) {
            error_log("➡️ Пропуск строки {$row} - нет даты");
            continue;
        }

        // ОБРАБАТЫВАЕМ СТРОКУ С ДАННЫМИ (используем текущую дату)
        foreach ($headers as $col => $header) {
            if ($col == $dateColumn || $header === 'Дата' || $header === 'Примечания' || $header === 'Итог') continue;

            $categoryAmount = $worksheet->getCell([$col, $row])->getCalculatedValue();
            
            if ($this->isNumericValue($categoryAmount)) {
                $amount = $this->parseAmount($categoryAmount);
                
                if ($amount != 0) {
                    error_log("💰 Импорт: {$header} = {$amount}");
                    
                    $category = $this->getOrCreateCategory(trim($header), $categoryType, $user, $entityManager);
                    
                    if (!$category->getId()) {
                        $entityManager->persist($category);
                        $categoriesCreated++;
                        $this->userCategoriesCache[$categoryType->value][mb_strtolower(trim($header))] = $category;
                    }

                    $transaction = new Transaction();
                    $transaction->setUser($user);
                    $transaction->setCategory($category);
                    // $transaction->setAmount($categoryType === CategoryType::EXPENSE ? -abs($amount) : abs($amount));
                    $transaction->setAmount($amount);
                    $transaction->setDate($currentDate);
                    
                    $notesColumn = $this->findColumnIndex($headers, ['примечания', 'notes']);
                    $notes = '';
                    if ($notesColumn) {
                        $notes = $worksheet->getCell([$notesColumn, $row])->getCalculatedValue();
                        $notes = trim($notes);
                    }
                    
                    $description = 'Импорт: ' . $header;
                    if (!empty($notes)) $description .= ' (' . $notes . ')';
                    $transaction->setDescription($description);

                    $entityManager->persist($transaction);
                    $importedCount++;
                }
            }
        }
    }

    error_log("=== КОНЕЦ ИМПОРТА ===");
    return ['transactions' => $importedCount, 'categories' => $categoriesCreated];
}

    private function preloadUserCategories($user, CategoryType $type, EntityManagerInterface $entityManager): void
    {
        if (!isset($this->userCategoriesCache[$type->value])) {
            $categories = $entityManager->getRepository(Category::class)
                ->findBy(['user' => $user, 'type' => $type]);
            
            $this->userCategoriesCache[$type->value] = [];
            foreach ($categories as $category) {
                $this->userCategoriesCache[$type->value][mb_strtolower(trim($category->getName()))] = $category;
            }
        }
    }

    private function getOrCreateCategory(string $name, CategoryType $type, $user, EntityManagerInterface $entityManager): Category
    {
        $searchName = mb_strtolower(trim($name));
        
        // Ищем в кэше
        if (isset($this->userCategoriesCache[$type->value][$searchName])) {
            return $this->userCategoriesCache[$type->value][$searchName];
        }

        // Создаем новую категорию
        $category = new Category();
        $category->setName(trim($name));
        $category->setType($type);
        $category->setUser($user);
        $category->setColor($this->generateRandomColor());
        
        return $category;
    }

    private function isNumericValue($value): bool
    {
        if (is_numeric($value)) {
            return true;
        }
        
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || $value === '-') {
                return false;
            }
            
            // Пробуем разные форматы
            $formats = [
                str_replace(',', '.', $value), // 26,13 → 26.13
                preg_replace('/[^\d,.]/', '', $value), // убираем все кроме цифр, запятых и точек
            ];
            
            foreach ($formats as $format) {
                if (is_numeric($format) && floatval($format) != 0) {
                    return true;
                }
            }
        }
        
        return false;
    }

    private function parseAmount($value): float
    {
        if (is_numeric($value)) {
            return floatval($value);
        }
        
        if (is_string($value)) {
            $value = trim($value);
            
            // Пробуем разные форматы
            $formats = [
                str_replace(',', '.', $value), // 26,13 → 26.13
                preg_replace('/[^\d,.]/', '', $value), // убираем все кроме цифр, запятых и точек
            ];
            
            foreach ($formats as $format) {
                if (is_numeric($format)) {
                    return floatval($format);
                }
            }
        }
        
        return 0.0;
    }

    private function findColumnIndex(array $headers, array $possibleNames): ?int
    {
        foreach ($headers as $col => $header) {
            if (empty($header)) continue;
            
            $normalizedHeader = mb_strtolower(trim($header));
            foreach ($possibleNames as $name) {
                if (str_contains($normalizedHeader, mb_strtolower($name))) {
                    return $col;
                }
            }
        }
        return null;
    }

    private function parseDate($dateValue, string $sheetName): ?\DateTimeInterface
    {
        try {
            if (is_numeric($dateValue)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue);
            }

            $dateString = trim(strval($dateValue));
            if (empty($dateString)) return null;

            $months = [
                'января' => 1, 'февраля' => 2, 'марта' => 3, 'апреля' => 4,
                'мая' => 5, 'июня' => 6, 'июля' => 7, 'августа' => 8,
                'сентября' => 9, 'октября' => 10, 'ноября' => 11, 'декабря' => 12
            ];

            foreach ($months as $monthName => $monthNumber) {
                if (str_contains(mb_strtolower($dateString), $monthName)) {
                    preg_match('/(\d{4})/', $sheetName, $yearMatches);
                    $year = $yearMatches[1] ?? date('Y');
                    preg_match('/(\d+)/', $dateString, $dayMatches);
                    $day = $dayMatches[1] ?? 1;
                    $dateString = sprintf('%d-%02d-%02d', $year, $monthNumber, $day);
                    break;
                }
            }

            if (is_numeric($dateString) && $dateString >= 1 && $dateString <= 31) {
                preg_match('/(\d{4})/', $sheetName, $yearMatches);
                $year = $yearMatches[1] ?? date('Y');
                preg_match('/(\d{1,2})/', $sheetName, $monthMatches);
                $month = $monthMatches[1] ?? date('n');
                $dateString = sprintf('%d-%02d-%02d', $year, $month, $dateString);
            }

            return new \DateTime($dateString);
            
        } catch (\Exception $e) {
            return null;
        }
    }

    private function generateRandomColor(): string
    {
        $colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8'];
        return $colors[array_rand($colors)];
    }
}