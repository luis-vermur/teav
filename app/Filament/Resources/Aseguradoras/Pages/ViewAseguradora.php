<?php

namespace App\Filament\Resources\Aseguradoras\Pages;

use App\Filament\Resources\Aseguradoras\AseguradoraResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAseguradora extends ViewRecord
{
    protected static string $resource = AseguradoraResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->nombre;
    }

    public function getSubheading(): ?string
    {
        return $this->getRecord()->rfc;
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
