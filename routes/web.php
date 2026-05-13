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

Route::get('/quienes-somos', function () {
    return view('site.about', SiteCatalog::aboutViewData());
});

Route::get('/{servicePage}', function (string $servicePage) {
    $data = SiteCatalog::servicePageViewData($servicePage);
    abort_if($data === null, 404);

    return view('site.service-page', $data);
})->where('servicePage', 'alquileres|compra-venta|restauraciones');

Route::get('/catalogo/{categoryPath}', function (string $categoryPath) {
    $data = SiteCatalog::categoryViewData($categoryPath);
    abort_if($data === null, 404);

    return view('site.category', $data);
})->where('categoryPath', '.*');

Route::get('/producto/{slug}', function (string $slug) {
    $data = SiteCatalog::productViewData($slug);
    abort_if($data === null, 404);

    return view('site.product', $data);
});

Route::get('/admin', function () {
    return view('admin.dashboard');
});
