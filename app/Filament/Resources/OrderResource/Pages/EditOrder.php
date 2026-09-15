<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Actions\UpdateOrderStatusAction;
use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        $currentStatus = $this->record->status instanceof OrderStatus ? $this->record->status : OrderStatus::tryFrom($this->record->status);

        return [
            Actions\Action::make('confirm_order')
                ->label('Xác nhận đơn')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $currentStatus === OrderStatus::Pending)
                ->action(fn () => $this->updateStatus(OrderStatus::Confirmed)),

            Actions\Action::make('process_order')
                ->label('Đóng gói')
                ->icon('heroicon-o-cube')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => $currentStatus === OrderStatus::Confirmed)
                ->action(fn () => $this->updateStatus(OrderStatus::Processing)),

            Actions\Action::make('ship_order')
                ->label('Giao hàng')
                ->icon('heroicon-o-truck')
                ->color('warning')
                ->form([
                    TextInput::make('waybill_id')
                        ->label('Mã vận đơn')
                        ->required(),
                ])
                ->requiresConfirmation()
                ->visible(fn (): bool => $currentStatus === OrderStatus::Processing)
                ->action(function (array $data) {
                    $this->updateStatus(OrderStatus::Shipped, 'Mã vận đơn: '.$data['waybill_id']);
                }),

            Actions\Action::make('deliver_order')
                ->label('Đã giao thành công')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $currentStatus === OrderStatus::Shipped)
                ->action(fn () => $this->updateStatus(OrderStatus::Delivered)),

            Actions\Action::make('cancel_order')
                ->label('Huỷ đơn & hoàn kho')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('cancel_reason')
                        ->label('Lý do huỷ')
                        ->required(),
                ])
                ->requiresConfirmation()
                ->visible(fn (): bool => ! $currentStatus?->isTerminal())
                ->action(fn (array $data) => $this->updateStatus(OrderStatus::Cancelled, $data['cancel_reason'])),
        ];
    }

    protected function updateStatus(OrderStatus $newStatus, ?string $note = null): void
    {
        try {
            app(UpdateOrderStatusAction::class)->execute($this->record, $newStatus, $note);
            $this->refreshFormData(['status_label']);
            Notification::make()
                ->title('Chuyển trạng thái thành công')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Không thể thao tác')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
