<?php

namespace App\Http\Controllers\Admin;

class AttributeController
{
    public function index(): array
    {
        return ['module' => 'attributes', 'action' => 'index'];
    }

    public function store(): array
    {
        return ['module' => 'attributes', 'action' => 'store'];
    }

    public function update(): array
    {
        return ['module' => 'attributes', 'action' => 'update'];
    }
}