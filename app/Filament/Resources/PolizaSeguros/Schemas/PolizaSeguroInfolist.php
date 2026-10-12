<?php

namespace App\Filament\Resources\PolizaSeguros\Schemas;

use App\Filament\Resources\Unidads\UnidadResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha de la póliza.
 *
 * Lo que importa de una póliza es si sigue viva y cuánto falta para
 * renovarla; eso va arriba. Los deducibles se guardan como fracción
 * (0.05) pero aquí se leen como porcentaje, que es como los dice la gente.
 */
class PolizaSeguroInfolist
{
    private const ESTATUS = [
        'vigente'    => ['Vigente', 'success'],
        'por_vencer' => ['Por vencer', 'warning'],
        'vencida'    => ['Vencida', 'danger'],
        'cancelada'  => ['Cancelada', 'gray'],
    ];

    private const COBERTURA = [
        'amplia'   => 'Amplia',
        'limitada' => 'Limitada',
        'rc'       => 'Responsabilidad civil',
    ];

    private const FORMA_PAGO = [
        'contado'    => 'De contado',
        'semestral'  => 'Semestral',
        'trimestral' => 'Trimestral',
        'mensual'    => 'Mensual',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // ─────────────── RESUMEN ───────────────
            Section::make()
                ->schema([
                    TextEntry::make('estatus')
                        ->label('Situación')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::ESTATUS[$state][0] ?? ucfirst((string) $state))
                        ->color(fn ($state) => self::ESTATUS[$state][1] ?? 'gray'),

                    // Los días que faltan es lo que dispara la renovación.
                    TextEntry::make('dias_para_vencer')
                        ->label('Vence')
                        ->state(fn ($record) => (int) now()->startOfDay()
                            ->diffInDays($record->vigencia_fin, false))
                        ->formatStateUsing(fn ($state) => match (true) {
                            $state < 0   => abs($state) . ' días vencida',
                            $state === 0 => 'Vence hoy',
                            default      => "En {$state} días",
                        })
                        ->helperText(fn ($record) => $record->vigencia_fin?->format('d/M/Y'))
                        ->badge()
                        ->color(fn ($state) => match (true) {
                            $state < 0  => 'danger',
                            $state < 30 => 'warning',
                            default     => 'success',
                        }),

                    TextEntry::make('prima_total')
                        ->label('Prima total')
                        ->money('MXN')
                        ->weight('bold'),

                    TextEntry::make('suma_asegurada')
                        ->label('Suma asegurada')
                        ->money('MXN')
                        ->placeholder('—'),
                ])
                ->columns(4),

            // ─────────────── COBERTURA ───────────────
            Section::make('Cobertura')
                ->schema([
                    TextEntry::make('tipo_cobertura')
                        ->label('Tipo de cobertura')
                        ->badge()
                        ->color('info')
                        ->formatStateUsing(fn ($state) => self::COBERTURA[$state] ?? ucfirst((string) $state)),

                    // Se guardan como fracción; aquí se leen como porcentaje.
                    TextEntry::make('deducible_danos')
                        ->label('Deducible de daños')
                        ->formatStateUsing(fn ($state) => number_format((float) $state * 100, 2) . '%')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('deducible_robo')
                        ->label('Deducible de robo')
                        ->formatStateUsing(fn ($state) => number_format((float) $state * 100, 2) . '%')
                        ->hidden(fn ($state) => blank($state)),
                ])
                ->columns(3),

            // ─────────────── VIGENCIA Y PAGO ───────────────
            Section::make('Vigencia y pago')
                ->schema([
                    TextEntry::make('numero_poliza')
                        ->label('Número de póliza')
                        ->copyable()
                        ->weight('bold'),

                    TextEntry::make('vigencia_inicio')
                        ->label('Vigente desde')
                        ->date('d/M/Y'),

                    TextEntry::make('vigencia_fin')
                        ->label('Vigente hasta')
                        ->date('d/M/Y'),

                    TextEntry::make('forma_pago')
                        ->label('Forma de pago')
                        ->formatStateUsing(fn ($state) => self::FORMA_PAGO[$state] ?? ucfirst((string) $state)),

                    // Si no hay conductor designado, conviene notarlo.
                    TextEntry::make('conductor_designado')
                        ->label('Conductor designado')
                        ->placeholder('Sin conductor designado')
                        ->color(fn ($state) => blank($state) ? 'warning' : null),
                ])
                ->columns(4),

            // ─────────────── VEHÍCULO ───────────────
            Section::make('Vehículo asegurado')
                ->schema([
                    TextEntry::make('unidad.descripcion')
                        ->label('Vehículo')
                        ->weight('bold')
                        ->helperText(fn ($record) => $record->unidad?->codigo_interno)
                        ->url(fn ($record) => $record->unidad
                            ? UnidadResource::getUrl('view', ['record' => $record->unidad])
                            : null)
                        ->color('primary'),

                    TextEntry::make('unidad.vin')
                        ->label('VIN')
                        ->copyable()
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('unidad.placas')
                        ->label('Placas')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('aseguradora.nombre')
                        ->label('Aseguradora')
                        ->helperText(fn ($record) => $record->aseguradora?->telefono_siniestros
                            ? 'Siniestros: ' . $record->aseguradora->telefono_siniestros
                            : 'Sin teléfono de siniestros registrado'),
                ])
                ->columns(4),

            // ─────────────── SINIESTROS ───────────────
            // Sólo aparece si los hubo: una póliza limpia no necesita la sección.
            Section::make('Siniestros con esta póliza')
                ->visible(fn ($record) => $record->siniestros()->exists())
                ->schema([
                    TextEntry::make('siniestros_count')
                        ->label('Siniestros reportados')
                        ->state(fn ($record) => $record->siniestros()->count())
                        ->badge()
                        ->color('warning'),

                    TextEntry::make('siniestros_monto')
                        ->label('Daño estimado acumulado')
                        ->state(fn ($record) => (float) $record->siniestros()->sum('monto_estimado'))
                        ->money('MXN'),

                    TextEntry::make('siniestros_recuperado')
                        ->label('Recuperado de la aseguradora')
                        ->state(fn ($record) => (float) $record->siniestros()->sum('monto_recuperado'))
                        ->money('MXN')
                        ->color('success'),
                ])
                ->columns(3),

            Section::make('Notas')
                ->collapsed()
                ->visible(fn ($record) => filled($record->notas))
                ->schema([
                    TextEntry::make('notas')
                        ->label('')
                        ->columnSpanFull(),
                ]),

            // ─────────────── PIE ───────────────
            Section::make()
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Dada de alta el')
                        ->date('d/M/Y')
                        ->size('xs')
                        ->color('gray'),
                ])
                ->columns(2),
        ]);
    }
}
