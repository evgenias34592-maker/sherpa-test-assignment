<?php

declare(strict_types=1);

namespace App\Controller;

use App\Catalog\ProductExporter;
use App\Catalog\ProductPresenter;
use App\Catalog\ProductQuery;
use App\Http\ApiException;
use App\Http\JsonResponse;
use App\Import\MoneyParser;
use App\Repository\ProductRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

final class ProductController
{
    private const EXPORT_MAX_ROWS = 5000;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductPresenter $presenter,
        private readonly ProductExporter $exporter,
        private readonly MoneyParser $money,
        private readonly JsonResponse $json,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = ProductQuery::fromArray($request->getQueryParams(), $this->money);
        $total = $this->products->countSearch($query->name, $query->priceMin, $query->priceMax);
        $items = $this->products->search($query->name, $query->priceMin, $query->priceMax, $query->page, $query->limit);
        $ids = [];
        foreach ($items as $product) {
            $ids[] = $product->id();
        }
        $covers = $this->products->coverPaths($ids);
        $data = [];
        foreach ($items as $product) {
            $data[] = $this->presenter->summary($product, $covers[$product->id()] ?? null);
        }
        $totalPages = $total === 0 ? 0 : (int) ceil($total / $query->limit);

        return $this->json->write($response, [
            'data' => $data,
            'meta' => [
                'page' => $query->page,
                'limit' => $query->limit,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $raw = RouteContext::fromRequest($request)->getRoute()?->getArgument('id');
        if (!is_string($raw) || preg_match('/^[1-9]\d*$/', $raw) !== 1) {
            throw new ApiException('Товар не найден', 404);
        }
        $product = $this->products->find((int) $raw);
        if ($product === null) {
            throw new ApiException('Товар не найден', 404);
        }

        return $this->json->write($response, ['data' => $this->presenter->detail($product)]);
    }

    public function export(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = ProductQuery::fromArray($request->getQueryParams(), $this->money);
        $total = $this->products->countSearch($query->name, $query->priceMin, $query->priceMax);
        if ($total > self::EXPORT_MAX_ROWS) {
            throw new ApiException(
                'В выборке ' . $total . ' товаров, экспорт ограничен ' . self::EXPORT_MAX_ROWS . '. Уточните фильтр.',
                422,
            );
        }
        $products = $this->products->export($query->name, $query->priceMin, $query->priceMax, self::EXPORT_MAX_ROWS);
        $path = $this->exporter->xlsx($products);
        $contents = file_get_contents($path);
        @unlink($path);
        if (!is_string($contents)) {
            throw new ApiException('Не удалось сформировать файл', 500);
        }
        $response->getBody()->write($contents);

        return $response
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->withHeader('Content-Disposition', 'attachment; filename="products.xlsx"')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
