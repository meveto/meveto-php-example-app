<?php

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
    return view('welcome');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home')->middleware('meveto.check');

Route::get('meveto/login', 'MevetoController@login')->name('meveto.login');
Route::get('meveto/redirect', 'MevetoController@handleRedirect')->name('meveto.redirect');
Route::get('/connect-to-meveto', 'MevetoController@loginPage')->name('meveto-connect');
Route::post('meveto/connect', 'MevetoController@connectToMeveto')->name('meveto.connect');
Route::get('logout', 'Auth\LoginController@logout');

// Application's logout webhook that will be called by Meveto
Route::post('meveto/logout', 'MevetoController@logout');