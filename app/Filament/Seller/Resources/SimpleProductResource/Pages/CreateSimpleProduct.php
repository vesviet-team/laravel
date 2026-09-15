<?php

namespace App\Filament\Seller\Resources\SimpleProductResource\Pages;

use App\Actions\CreateSellerProductAction;
use App\Filament\Seller\Resources\SimpleProductResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

class CreateSimpleProduct extends CreateRecord
{
    protected static string $resource = SimpleProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // P0-01: Use Filament::getTenant() — the authoritative active tenant context.
        // auth()->user()->sellerProfile traverses the Eloquent relation which may not
        // match the active Filament tenant in edge cases (e.g. multi-profile scenarios).
        $sellerProfile = Filament::getTenant();

        if (! $sellerProfile) {
            Notification::make()
                ->title('Bạn chưa có gian hàng')
                ->body('Vui lòng tạo Seller Profile trước khi thêm sản phẩm.')
                ->danger()
                ->send();
            throw new RuntimeException('No active seller tenant found. Cannot create product.');
        }

        $data['seller_id'] = $sellerProfile->id;
        $data['is_visible'] = $data['is_visible'] ?? true;
        $data['status'] = $data['status'] ?? 'published';
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateSellerProductAction::class)->execute($data);
    }
}
