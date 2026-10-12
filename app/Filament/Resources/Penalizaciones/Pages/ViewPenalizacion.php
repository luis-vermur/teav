<?php

namespace App\Filament\Resources\Penalizaciones\Pages;

use App\Filament\Resources\Penalizaciones\Actions\CondonarAction;
use App\Filament\Resources\Penalizaciones\PenalizacionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPenalizacion extends ViewRecord
{
    protected static string $resource = PenalizacionResource::class;

    public function getTitle(): string
    {
        $r = $this->getRecord();

        return match ($r->tipo) {
            'mora'              => 'Recargo por mora',
            'km_excedente'      => 'Cargo por km excedente',
            'daños'             => 'Cargo por daños',
            'documentos'        => 'Cargo por documentos',
            'devolucion_tardia' => 'Cargo por devolución tardía',
            default             => 'Recargo',
        } . ' — $' . number_format((float) $r->monto, 2);
    }

    public function getSubheading(): ?string
    {
        $r = $this->getRecord();

        return trim(implode(' — ', array_filter([
            $r->contrato?->folio,
            $r->contrato?->arrendatario?->nombre_completo,
        ]))) ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            CondonarAction::make(),
        ];
    }
}
