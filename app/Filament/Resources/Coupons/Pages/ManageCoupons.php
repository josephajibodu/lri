<?php

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Resources\Coupons\CouponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageCoupons extends ManageRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New coupon')
                ->modalHeading('New coupon')
                ->modalDescription('You can’t change a coupon once it’s created, only disable it.')
                ->modalSubmitActionLabel('Create coupon')
                ->modalWidth(Width::ExtraLarge)
                ->slideOver()
                ->createAnother(false)
                ->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => auth()->id()]),
        ];
    }
}
