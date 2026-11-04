<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\DeskPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    DeskPanelProvider::class,
];
