<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Bootstrap;
use App\Entity\ImportJob;
use App\Infrastructure\Seeder;
use App\Message\ImportProductsHandler;
use App\Message\ImportProductsMessage;
use App\Tests\Support\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class ApiFlowTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        $container = TestKernel::container();
        TestKernel::reset();
        $container->get(Seeder::class)->ensureAdmin();
        $this->app = Bootstrap::app($container);
    }

    public function testHealthLoginCatalogImportAndUpsert(): void
    {
        $health = $this->json($this->request('GET', '/api/health'));
        self::assertSame(200, $health['status']);
        self::assertSame('ok', $health['body']['status']);

        $denied = $this->request('GET', '/api/products');
        self::assertSame(401, $denied->getStatusCode());

        $badLogin = $this->request('POST', '/api/auth/login', ['email' => 'admin@example.com', 'password' => 'wrong-password']);
        self::assertSame(401, $badLogin->getStatusCode());

        $login = $this->json($this->request('POST', '/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'admin12345',
        ]));
        self::assertSame(200, $login['status']);
        $token = $login['body']['token'];
        self::assertIsString($token);

        $empty = $this->json($this->request('GET', '/api/products?page=1&limit=20', null, $token));
        self::assertSame(0, $empty['body']['meta']['total']);

        $fixture = dirname(__DIR__) . '/fixtures/import-example.xlsx';
        $created = $this->json($this->upload($this->copyOf($fixture), 'import-example.xlsx', $token));
        self::assertSame(202, $created['status']);
        self::assertSame('pending', $created['body']['data']['status']);
        $this->drain();

        $job = $this->json($this->request('GET', '/api/imports/' . $created['body']['data']['id'], null, $token));
        self::assertSame(ImportJob::COMPLETED, $job['body']['data']['status']);
        self::assertSame(40, $job['body']['data']['createdCount']);
        self::assertSame(0, $job['body']['data']['errorCount']);

        $page = $this->json($this->request('GET', '/api/products?page=1&limit=2', null, $token));
        self::assertSame(40, $page['body']['meta']['total']);
        self::assertSame(20, $page['body']['meta']['totalPages']);
        self::assertCount(2, $page['body']['data']);
        self::assertArrayNotHasKey('description', $page['body']['data'][0]);
        self::assertIsString($page['body']['data'][0]['cover']);
        self::assertStringStartsWith('/media/', $page['body']['data'][0]['cover']);

        $filtered = $this->json($this->request('GET', '/api/products?name=' . rawurlencode('Бермуды') . '&priceMin=1000&priceMax=1400', null, $token));
        self::assertGreaterThan(0, $filtered['body']['meta']['total']);
        foreach ($filtered['body']['data'] as $item) {
            self::assertStringContainsString('Бермуды', $item['name']);
            self::assertGreaterThanOrEqual(1000, (float) $item['price']);
            self::assertLessThanOrEqual(1400, (float) $item['price']);
        }

        $id = $page['body']['data'][0]['id'];
        $detail = $this->json($this->request('GET', '/api/products/' . $id, null, $token));
        self::assertIsString($detail['body']['data']['description']);
        self::assertNotEmpty($detail['body']['data']['attributes']);
        self::assertNotEmpty($detail['body']['data']['images']);
        self::assertArrayHasKey('path', $detail['body']['data']['images'][0]);
        self::assertArrayHasKey('url', $detail['body']['data']['images'][0]);

        $again = $this->json($this->upload($this->copyOf($fixture), 'import-example.xlsx', $token));
        $this->drain();
        $second = $this->json($this->request('GET', '/api/imports/' . $again['body']['data']['id'], null, $token));
        self::assertSame(40, $second['body']['data']['updatedCount']);
        self::assertSame(0, $second['body']['data']['createdCount']);
        $after = $this->json($this->request('GET', '/api/products?limit=1', null, $token));
        self::assertSame(40, $after['body']['meta']['total']);

        $invalid = tempnam(sys_get_temp_dir(), 'bad');
        self::assertIsString($invalid);
        file_put_contents($invalid, 'hello');
        $rejected = $this->upload($invalid, 'notes.xlsx', $token);
        self::assertSame(422, $rejected->getStatusCode());
        if (is_file($invalid)) {
            unlink($invalid);
        }

        $export = $this->request('GET', '/api/products/export', null, $token);
        self::assertSame(200, $export->getStatusCode());
        self::assertStringStartsWith('PK', (string) $export->getBody());
    }

    public function testExportRefusesASelectionLargerThanTheLimit(): void
    {
        $login = $this->json($this->request('POST', '/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'admin12345',
        ]));
        $token = $login['body']['token'];
        self::assertIsString($token);

        $em = TestKernel::container()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->getConnection()->executeStatement(<<<'SQL'
            INSERT INTO products (external_code, name, description, price, discount, created_at, updated_at)
            SELECT 'bulk-' || g, 'Товар', '', 10.00, 0.00, NOW(), NOW()
            FROM generate_series(1, 5001) AS g
        SQL);

        $rejected = $this->json($this->request('GET', '/api/products/export', null, $token));
        self::assertSame(422, $rejected['status']);
        self::assertSame(
            'В выборке 5001 товаров, экспорт ограничен 5000. Уточните фильтр.',
            $rejected['body']['error'],
        );

        $narrow = $this->request('GET', '/api/products/export?name=' . rawurlencode('нет такого'), null, $token);
        self::assertSame(200, $narrow->getStatusCode());
        self::assertStringStartsWith('PK', (string) $narrow->getBody());
    }

    public function testLoginRejectsMalformedFieldsWithoutRevealingTheAccount(): void
    {
        $empty = $this->json($this->request('POST', '/api/auth/login', ['email' => '   ', 'password' => '']));
        self::assertSame(422, $empty['status']);
        self::assertSame('Укажите email и пароль', $empty['body']['error']);

        $email = $this->json($this->request('POST', '/api/auth/login', ['email' => 'not-an-email', 'password' => 'admin12345']));
        self::assertSame(422, $email['status']);
        self::assertSame('Некорректный email', $email['body']['error']);

        $long = $this->json($this->request('POST', '/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => str_repeat('a', 201),
        ]));
        self::assertSame(422, $long['status']);

        $unknown = $this->json($this->request('POST', '/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'admin12345',
        ]));
        $wrong = $this->json($this->request('POST', '/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]));
        self::assertSame(401, $unknown['status']);
        self::assertSame(401, $wrong['status']);
        self::assertSame($unknown['body']['error'], $wrong['body']['error']);
    }

    public function testImportRateLimit(): void
    {
        $login = $this->json($this->request('POST', '/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'admin12345',
        ]));
        $token = $login['body']['token'];
        $last = 0;
        for ($i = 0; $i < 6; ++$i) {
            $path = tempnam(sys_get_temp_dir(), 'limit');
            self::assertIsString($path);
            file_put_contents($path, 'nope');
            $last = $this->upload($path, 'notes.xlsx', $token)->getStatusCode();
            if (is_file($path)) {
                unlink($path);
            }
        }

        self::assertSame(429, $last);
    }

    public function testImportJobVisibleOnlyToOwner(): void
    {
        $container = TestKernel::container();
        $em = $container->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->persist(new \App\Entity\User(
            'other@example.com',
            password_hash('other12345', PASSWORD_DEFAULT),
            'Other',
        ));
        $em->flush();

        $login = $this->json($this->request('POST', '/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'admin12345',
        ]));
        $token = $login['body']['token'];
        $fixture = dirname(__DIR__) . '/fixtures/import-example.xlsx';
        $created = $this->json($this->upload($this->copyOf($fixture), 'import-example.xlsx', $token));
        $jobId = $created['body']['data']['id'];

        $other = $this->json($this->request('POST', '/api/auth/login', [
            'email' => 'other@example.com',
            'password' => 'other12345',
        ]));
        $foreign = $this->request('GET', '/api/imports/' . $jobId, null, $other['body']['token']);
        self::assertSame(404, $foreign->getStatusCode());
    }

    public function testBrokenRowDoesNotStopValidRow(): void
    {
        $login = $this->json($this->request('POST', '/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'admin12345',
        ]));
        $token = $login['body']['token'];
        $path = $this->mixedWorkbook();
        $response = $this->json($this->upload($path, 'mixed.xlsx', $token));
        $this->drain();
        $job = $this->json($this->request('GET', '/api/imports/' . $response['body']['data']['id'], null, $token));
        self::assertSame(1, $job['body']['data']['createdCount']);
        self::assertSame(1, $job['body']['data']['errorCount']);
        self::assertSame(ImportJob::COMPLETED, $job['body']['data']['status']);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function mixedWorkbook(): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['Внешний код', 'Наименование', 'Описание', 'Цена: Цена продажи', 'Закупочная цена', 'Доп. поле: Цвет'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }
        $sheet->fromArray(['ok-1', 'Хороший товар', 'Описание', '100,00', '40,00', 'Nero'], null, 'A2');
        $sheet->fromArray(['bad-1', 'Плохой товар', 'Описание', 'нет', '10,00', 'Rosso'], null, 'A3');
        $path = tempnam(sys_get_temp_dir(), 'mixed') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /** @param array<string, mixed>|null $json
     * @return array{status: int, body: array<string, mixed>}
     */
    private function json(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true);

        return [
            'status' => $response->getStatusCode(),
            'body' => is_array($decoded) ? $decoded : [],
        ];
    }

    /** @param array<string, mixed>|null $json */
    private function request(string $method, string $uri, ?array $json = null, ?string $token = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'http://localhost' . $uri);
        if ($token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        }
        if ($json !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withParsedBody($json);
        }

        return $this->app->handle($request);
    }

    private function copyOf(string $path): string
    {
        $copy = tempnam(sys_get_temp_dir(), 'xlsx');
        self::assertIsString($copy);
        if (!copy($path, $copy)) {
            self::fail('Cannot copy workbook');
        }

        return $copy;
    }

    private function upload(string $path, string $name, string $token): ResponseInterface
    {
        $file = new UploadedFile($path, $name, 'application/octet-stream', (int) filesize($path), UPLOAD_ERR_OK, false);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'http://localhost/api/imports')
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withUploadedFiles(['file' => $file]);

        return $this->app->handle($request);
    }

    private function drain(): void
    {
        $container = TestKernel::container();
        $transport = $container->get(TransportInterface::class);
        $handler = $container->get(ImportProductsHandler::class);
        self::assertInstanceOf(TransportInterface::class, $transport);
        self::assertInstanceOf(ImportProductsHandler::class, $handler);
        foreach ($transport->get() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof ImportProductsMessage) {
                $handler($message);
            }
            $transport->ack($envelope);
        }
    }
}
