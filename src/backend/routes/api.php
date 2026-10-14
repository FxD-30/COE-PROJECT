<?php

use Illuminate\Support\Facades\Route;

Route::get('/hello', function () {
    return ['message' => 'Hello from Laravel!'];
});
