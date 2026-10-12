<?php

namespace App\Filament\Resources\Arrendatarios\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha del cliente.
 *
 * Criterio: sólo se muestra lo que aplica a ESTE cliente. Los campos de
 * persona moral no existen para una persona física y viceversa; los vacíos
 * se ocultan salvo cuando la ausencia es la información (documentos
 * faltantes, adeudo). Sin ids crudos ni timestamps.
 */
class ArrendatarioInfolist
{
    /** Situación del cliente: etiqueta legible y color semántico. */
    private const ESTATUS = [
        'prospecto'     => ['Prospecto', 'gray'],
        'en_validacion' => ['En validación', 'warning'],
        'activo'        => ['Activo', 'success'],
        'suspendido'    => ['Suspendido', 'warning'],
        'lista_negra'   => ['Lista negra', 'danger'],
        'inactivo'      => ['Inactivo', 'gray'],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // ─────────────── RESUMEN ───────────────
            // Sin encabezado: es la tarjeta de un vistazo, arriba de todo.
            Section::make()
                ->schema([
                    TextEntry::make('tipo_persona')
                        ->label('Tipo')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn ($state) => $state === 'moral' ? 'Empresa' : 'Persona física'),

                    TextEntry::make('estatus')
                        ->label('Situación')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::ESTATUS[$state][0] ?? ucfirst((string) $state))
                        ->color(fn ($state) => self::ESTATUS[$state][1] ?? 'gray'),

                    TextEntry::make('calificacion_interna')
                        ->label('Calificación interna')
                        ->placeholder('Sin calificar')
                        ->formatStateUsing(fn ($state) => blank($state)
                            ? null
                            : str_repeat('★', (int) $state) . str_repeat('☆', max(0, 5 - (int) $state)) . "  ({$state}/5)"),

                    // La ausencia de adeudo también es información: no se oculta.
                    TextEntry::make('saldo_vencido')
                        ->label('Saldo vencido')
                        ->state(fn ($record) => $record->saldoVencido())
                        ->formatStateUsing(fn ($state) => $state > 0
                            ? '$' . number_format($state, 2)
                            : 'Al corriente')
                        ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                        ->weight('bold'),

                    /**
                     * Lo primero que se pregunta de un cliente: ¿le puedo
                     * firmar un contrato? Y si no, por qué. Antes había que
                     * deducirlo revisando estatus, adeudos y expediente.
                     */
                    TextEntry::make('puede_contratar')
                        ->label('¿Puede firmar contrato?')
                        ->state(fn ($record) => $record->puedeContratar()
                            ? 'Sí, listo para contratar'
                            : 'Todavía no')
                        ->badge()
                        ->color(fn ($record) => $record->puedeContratar() ? 'success' : 'danger')
                        ->helperText(fn ($record) => $record->puedeContratar()
                            ? null
                            : implode(' · ', $record->motivosParaNoContratar()))
                        ->columnSpanFull(),
                ])
                ->columns(4),

            // ─────────────── IDENTIFICACIÓN · PERSONA FÍSICA ───────────────
            Section::make('Identificación')
                ->visible(fn ($record) => $record->esFisica())
                ->schema([
                    TextEntry::make('nombre_completo')
                        ->label('Nombre completo')
                        ->weight('bold')
                        ->columnSpan(2),

                    TextEntry::make('curp')
                        ->label('CURP')
                        ->copyable()
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('fecha_nacimiento')
                        ->label('Fecha de nacimiento')
                        ->hidden(fn ($state) => blank($state))
                        // La edad al lado, que es lo que de verdad se consulta.
                        ->formatStateUsing(fn ($state) => $state->format('d/M/Y') . ' (' . $state->age . ' años)'),

                    TextEntry::make('nacionalidad')
                        ->label('Nacionalidad')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('ocupacion')
                        ->label('Ocupación')
                        ->hidden(fn ($state) => blank($state)),
                ])
                ->columns(3),

            // ─────────────── IDENTIFICACIÓN · PERSONA MORAL ───────────────
            Section::make('Identificación')
                ->visible(fn ($record) => $record->esMoral())
                ->schema([
                    TextEntry::make('razon_social')
                        ->label('Razón social')
                        ->weight('bold')
                        ->columnSpan(2),

                    TextEntry::make('nombre_comercial')
                        ->label('Nombre comercial')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('fecha_constitucion')
                        ->label('Fecha de constitución')
                        ->date('d/M/Y')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('acta_constitutiva_numero')
                        ->label('Número de acta constitutiva')
                        ->copyable()
                        ->hidden(fn ($state) => blank($state)),

                    // Subsección: los datos del representante van juntos.
                    Section::make('Representante legal')
                        ->columnSpanFull()
                        ->schema([
                            TextEntry::make('representante_legal_nombre')
                                ->label('Nombre')
                                ->hidden(fn ($state) => blank($state)),

                            TextEntry::make('representante_legal_rfc')
                                ->label('RFC')
                                ->copyable()
                                ->hidden(fn ($state) => blank($state)),

                            TextEntry::make('representante_legal_curp')
                                ->label('CURP')
                                ->copyable()
                                ->hidden(fn ($state) => blank($state)),

                            TextEntry::make('representante_legal_telefono')
                                ->label('Teléfono')
                                ->hidden(fn ($state) => blank($state)),

                            TextEntry::make('representante_legal_email')
                                ->label('Correo electrónico')
                                ->copyable()
                                ->hidden(fn ($state) => blank($state)),
                        ])
                        ->columns(3),
                ])
                ->columns(3),

            // ─────────────── FISCAL Y CONTACTO ───────────────
            Section::make('Fiscal y contacto')
                ->schema([
                    TextEntry::make('rfc')
                        ->label('RFC')
                        ->copyable()
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('regimen_fiscal')
                        ->label('Régimen fiscal')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('email')
                        ->label('Correo electrónico')
                        ->copyable()
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('telefono')
                        ->label('Teléfono')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('telefono_alterno')
                        ->label('Teléfono alterno')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('direccion_fiscal')
                        ->label('Dirección fiscal')
                        ->state(fn ($record) => $record->direccion_fiscal?->completa)
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),
                ])
                ->columns(3),

            // ─────────────── LICENCIA ───────────────
            // Sólo persona física, y sólo si hay algo que enseñar.
            Section::make('Licencia de conducir')
                ->visible(fn ($record) => $record->esFisica()
                    && (filled($record->licencia_numero) || filled($record->licencia_vigencia)))
                ->schema([
                    TextEntry::make('licencia_numero')
                        ->label('Número')
                        ->copyable()
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('licencia_vigencia')
                        ->label('Vigencia')
                        ->hidden(fn ($state) => blank($state))
                        ->formatStateUsing(fn ($state) => $state->isPast()
                            ? $state->format('d/M/Y') . ' — vencida'
                            : $state->format('d/M/Y'))
                        ->color(fn ($state) => $state?->isPast() ? 'danger' : null)
                        ->weight(fn ($state) => $state?->isPast() ? 'bold' : null),
                ])
                ->columns(3),

            // ─────────────── SITUACIÓN CREDITICIA ───────────────
            Section::make('Situación crediticia')
                ->schema([
                    TextEntry::make('ingreso_mensual_declarado')
                        ->label('Ingreso mensual declarado')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('limite_credito')
                        ->label('Límite de crédito')
                        ->money('MXN')
                        ->hidden(fn ($state) => blank($state)),

                    TextEntry::make('contratos_vigentes')
                        ->label('Contratos vigentes')
                        ->state(fn ($record) => $record->contratosVigentes()->count()),

                    TextEntry::make('saldo_vencido_detalle')
                        ->label('Saldo vencido')
                        ->state(fn ($record) => $record->saldoVencido())
                        ->formatStateUsing(fn ($state) => $state > 0
                            ? '$' . number_format($state, 2)
                            : 'Sin adeudos')
                        ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                    // La ausencia SÍ es informativa: nunca se oculta.
                    TextEntry::make('documentos_faltantes')
                        ->label('Documentos faltantes')
                        ->state(fn ($record) => $record->documentosFaltantes())
                        ->badge()
                        ->color('warning')
                        ->placeholder('—')
                        ->columnSpanFull()
                        ->visible(fn ($record) => ! empty($record->documentosFaltantes())),

                    TextEntry::make('expediente_completo')
                        ->label('Expediente')
                        ->state('Expediente completo')
                        ->badge()
                        ->color('success')
                        ->columnSpanFull()
                        ->visible(fn ($record) => empty($record->documentosFaltantes())),
                ])
                ->columns(4),

            // ─────────────── NOTAS ───────────────
            Section::make('Notas')
                ->collapsed()
                ->visible(fn ($record) => filled($record->motivo_estatus) || filled($record->notas))
                ->schema([
                    TextEntry::make('motivo_estatus')
                        ->label('Motivo de la situación actual')
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),

                    TextEntry::make('notas')
                        ->label('Notas')
                        ->hidden(fn ($state) => blank($state))
                        ->columnSpanFull(),
                ])
                ->columns(1),

            // ─────────────── PIE ───────────────
            // Discreto y al final: quién y cuándo dio de alta al cliente.
            Section::make()
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Dado de alta el')
                        ->date('d/M/Y')
                        ->size('xs')
                        ->color('gray'),

                    TextEntry::make('registradoPor.name')
                        ->label('Registrado por')
                        ->size('xs')
                        ->color('gray')
                        ->placeholder('—'),
                ])
                ->columns(2),
        ]);
    }
}
