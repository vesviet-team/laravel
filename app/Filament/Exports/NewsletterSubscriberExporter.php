<?php

namespace App\Filament\Exports;

use App\Models\NewsletterSubscriber;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class NewsletterSubscriberExporter extends Exporter
{
    protected static ?string $model = NewsletterSubscriber::class;

    public static function getColumns(): array
    {
        return [
            //
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your newsletter subscriber export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
