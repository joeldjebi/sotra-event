<?php

use App\Providers\ApiDocumentationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    ApiDocumentationServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
];
