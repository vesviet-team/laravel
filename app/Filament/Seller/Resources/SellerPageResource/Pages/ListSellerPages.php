<?php

namespace App\Filament\Seller\Resources\SellerPageResource\Pages;

use App\Filament\Seller\Resources\SellerPageResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListSellerPages extends ListRecords
{
    protected static string $resource = SellerPageResource::class;

    public function mount(): void
    {
        parent::mount();

        // P1-04: Use Filament::getTenant() — the authoritative active tenant context.
        // auth()->user()->sellerProfile may not match the active Filament tenant.
        $seller = Filament::getTenant();
        $page = $seller?->pages()->first();

        if ($page) {
            $this->redirect(SellerPageResource::getUrl('edit', ['record' => $page->id]));
        }
    }
}
