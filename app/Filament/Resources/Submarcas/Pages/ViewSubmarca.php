<?php

namespace App\Filament\Resources\Submarcas\Pages;

use App\Filament\Resources\Submarcas\SubmarcaResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSubmarca extends ViewRecord
{
    protected static string $resource = SubmarcaResource::class;

    public function getTitle(): string
    {
        $r = $this->getRecord();

        return trim(($r->marca?->nombre ?? '') . ' ' . $r->nombre);
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->segmento;
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
