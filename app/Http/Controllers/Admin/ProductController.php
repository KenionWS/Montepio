<?php

namespace App\Http\Controllers\Admin;

class ProductController
{
    public function index(): array
    {
        return [
            'module' => 'products',
            'goal' => 'listado paginado con filtros y columnas para edicion rapida',
        ];
    }

    public function store(): array
    {
        return ['module' => 'products', 'action' => 'store'];
    }

    public function update(): array
    {
        return ['module' => 'products', 'action' => 'update'];
    }

    public function bulkUpdate(): array
    {
        return [
            'module' => 'products',
            'action' => 'bulk-update',
            'supports' => [
                'category',
                'published',
                'is_for_sale',
                'price',
                'featured',
                'attributes',
            ],
        ];
    }

    public function import(): array
    {
        return [
            'module' => 'products',
            'action' => 'import',
            'formats' => ['csv'],
        ];
    }

    public function categoryListing(): array
    {
        return [
            'module' => 'catalog',
            'payload' => 'optimized listing DTO',
        ];
    }

    public function show(): array
    {
        return [
            'module' => 'catalog',
            'payload' => 'product detail DTO',
        ];
    }
}