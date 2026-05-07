<?php

namespace App\Http\Controllers\Admin;

class CategoryController
{
    public function index(): array
    {
        return ['module' => 'categories', 'action' => 'index'];
    }

    public function store(): array
    {
        return ['module' => 'categories', 'action' => 'store'];
    }

    public function update(): array
    {
        return ['module' => 'categories', 'action' => 'update'];
    }
}