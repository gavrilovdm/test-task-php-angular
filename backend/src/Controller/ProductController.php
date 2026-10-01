<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\NotFoundException;
use App\Http\JsonResponder;
use App\Http\ProductPresenter;
use App\Http\Request\ProductFilterFactory;
use App\Repository\ProductRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProductController
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductPresenter $presenter,
        private readonly ProductFilterFactory $filters,
    ) {
    }

    /** GET /api/products?page=&limit=&name=&price_min=&price_max= */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $page = $this->products->paginate($this->filters->fromQuery($request->getQueryParams()));

        return JsonResponder::json($response, [
            'data' => array_map($this->presenter->summary(...), $page->items),
            'meta' => [
                'page' => $page->page,
                'limit' => $page->limit,
                'total' => $page->total,
                'totalPages' => $page->totalPages(),
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
