<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\NotFoundException;
use App\Http\JsonResponder;
use App\Http\ProductPresenter;
use App\Repository\ProductFilter;
use App\Repository\ProductRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProductController
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductPresenter $presenter,
    ) {
    }

    /** GET /api/products?page=&limit=&name=&price_min=&price_max= */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $filter = ProductFilter::fromQuery($request->getQueryParams());
        $result = $this->products->paginate($filter);

        return JsonResponder::json($response, [
            'data' => array_map($this->presenter->summary(...), $result['items']),
            'meta' => [
                'page' => $filter->page,
                'limit' => $filter->limit,
                'total' => $result['total'],
                'totalPages' => (int) ceil($result['total'] / $filter->limit),
            ],
        ]);
    }

    /** @param array{id: string} $args */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = filter_var($args['id'], \FILTER_VALIDATE_INT);
        $product = false === $id ? null : $this->products->findWithRelations($id);
        if (null === $product) {
            throw new NotFoundException('Товар не найден');
        }

        return JsonResponder::json($response, $this->presenter->detail($product));
    }
}
