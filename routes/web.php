<?php

use App\Support\SiteCatalog;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('site.home', SiteCatalog::homeViewData());
});

Route::get('/catalogo', function () {
    return view('site.catalog', SiteCatalog::catalogViewData((string) request()->query('q', '')));
});

Route::get('/catalogo/{category}', function (string $category) {
    $data = SiteCatalog::categoryViewData($category);
    abort_if($data === null, 404);

    return view('site.category', $data);
});

Route::get('/catalogo/{category}/{subcategory}', function (string $category, string $subcategory) {
    $data = SiteCatalog::categoryViewData($category, $subcategory);
    abort_if($data === null, 404);

    return view('site.category', $data);
});

Route::get('/producto/{slug}', function (string $slug) {
    $data = SiteCatalog::productViewData($slug);
    abort_if($data === null, 404);

    return view('site.product', $data);
});

Route::get('/admin', function () {
    return view('admin.dashboard');
});
