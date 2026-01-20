<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route('/test/simple', name: 'app_test_simple')]
    public function simple(): Response
    {
        return new Response('TEST: Контроллер работает! ' . date('Y-m-d H:i:s'));
    }

    #[Route('/test/import-form', name: 'app_test_import_form')]
    public function importForm(): Response
    {
        return $this->render('test/import_form.html.twig');
    }

    #[Route('/test/import-handler', name: 'app_test_import_handler', methods: ['POST'])]
    public function importHandler(Request $request): Response
    {
        $file = $request->files->get('test_file');
        
        if ($file) {
            return new Response("✅ Файл получен: " . $file->getClientOriginalName() . " (" . $file->getSize() . " bytes)");
        } else {
            return new Response("❌ Файл не получен");
        }
    }

    #[Route('/import/debug-form', name: 'app_import_debug_form')]
    public function debugForm(): Response
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head><title>Debug Form</title></head>
        <body>
            <h1>ТОЧНАЯ КОПИЯ ФОРМЫ С СТРАНИЦЫ ТРАНЗАКЦИЙ</h1>
            
            <form method="post" action="' . $this->generateUrl('app_import_transactions') . '" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="' . $this->container->get('security.csrf.token_manager')->getToken('import_transaction')->getValue() . '">
                <input type="file" name="excel_file" required>
                <select name="transaction_type">
                    <option value="expense">Расходы</option>
                    <option value="income">Доходы</option>
                </select>
                <button type="submit">ОТПРАВИТЬ</button>
            </form>
            
            <p>CSRF токен: ' . $this->container->get('security.csrf.token_manager')->getToken('import_transaction')->getValue() . '</p>
        </body>
        </html>';
        
        return new Response($html);
    }
}