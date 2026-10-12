<?php

namespace App\Filament\Resources\Arrendatarios\Schemas;

use App\Filament\Forms\CampoRfc;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ArrendatarioForm
{
    public static function configure(Schema $schema): Schema
{
    return $schema->components([
        Tabs::make('Arrendatario')->columnSpanFull()->tabs([

            Tab::make('Datos generales')->schema([
                Radio::make('tipo_persona')
                    ->label('Tipo de persona')
                    ->options(['fisica' => 'Persona física', 'moral' => 'Persona moral'])
                    ->default('fisica')
                    ->inline()
                    ->live()
                    ->required()
                    ->columnSpanFull(),

                // --- Física ---
                TextInput::make('nombre')
                    ->label('Nombre(s)')
                    ->visible(fn (Get $get) => $get('tipo_persona') === 'fisica')
                    ->required(fn (Get $get) => $get('tipo_persona') === 'fisica'),

                TextInput::make('apellido_paterno')
                    ->label('Apellido paterno')
                    ->visible(fn (Get $get) => $get('tipo_persona') === 'fisica')
                    ->required(fn (Get $get) => $get('tipo_persona') === 'fisica'),

                TextInput::make('apellido_materno')
                    ->label('Apellido materno')
                    ->visible(fn (Get $get) => $get('tipo_persona') === 'fisica'),

                TextInput::make('curp')
                    ->label('CURP')->length(18)
                    ->visible(fn (Get $get) => $get('tipo_persona') === 'fisica'),

                DatePicker::make('fecha_nacimiento')
                    ->label('Fecha de nacimiento')
                    ->maxDate(now()->subYears(18))
                    ->visible(fn (Get $get) => $get('tipo_persona') === 'fisica'),

                TextInput::make('ocupacion')
                    ->label('Ocupación')
                    ->visible(fn (Get $get) => $get('tipo_persona') === 'fisica'),

                // --- Moral ---
                TextInput::make('razon_social')
                    ->label('Razón social')
                    ->visible(fn (Get $get) => $get('tipo_persona') === 'moral')
                    ->required(fn (Get $get) => $get('tipo_persona') === 'moral')
                    ->columnSpanFull(),

                TextInput::make('nombre_comercial')
                    ->label('Nombre comercial')
                    ->visible(fn (Get $get) => $get('tipo_persona') === 'moral'),

                DatePicker::make('fecha_constitucion')
                    ->label('Fecha de constitución')
                    ->visible(fn (Get $get) => $get('tipo_persona') === 'moral'),

                TextInput::make('representante_legal_nombre')
                    ->label('Representante legal')
                    ->visible(fn (Get $get) => $get('tipo_persona') === 'moral'),

                TextInput::make('representante_legal_rfc')
                    ->label('RFC del representante')->maxLength(13)
                    ->visible(fn (Get $get) => $get('tipo_persona') === 'moral'),
            ])->columns(2),

            Tab::make('Contacto y fiscal')->schema([
                CampoRfc::make(),

                TextInput::make('regimen_fiscal')->label('Régimen fiscal (clave SAT)'),
                TextInput::make('email')->email(),
                TextInput::make('telefono')->label('Teléfono')->tel(),
                TextInput::make('telefono_alterno')->label('Teléfono alterno')->tel(),
                TextInput::make('licencia_numero')->label('Licencia de conducir')
                    ->visible(fn (Get $get) => $get('tipo_persona') === 'fisica'),
                DatePicker::make('licencia_vigencia')->label('Vigencia de licencia')
                    ->visible(fn (Get $get) => $get('tipo_persona') === 'fisica'),
            ])->columns(2),

            Tab::make('Control interno')->schema([
                Select::make('estatus')->options([
                    'prospecto' => 'Prospecto', 'en_validacion' => 'En validación',
                    'activo' => 'Activo', 'suspendido' => 'Suspendido',
                    'lista_negra' => 'Lista negra', 'inactivo' => 'Inactivo',
                ])->default('prospecto')->required()->live(),

                Select::make('calificacion_interna')
                    ->label('Calificación')
                    ->options([1 => '1 · Muy mala', 2 => '2 · Mala', 3 => '3 · Regular', 4 => '4 · Buena', 5 => '5 · Excelente']),

                TextInput::make('ingreso_mensual_declarado')
                    ->label('Ingreso mensual declarado')->numeric()->prefix('$')->minValue(0),

                TextInput::make('limite_credito')
                    ->label('Límite de crédito')->numeric()->prefix('$')->minValue(0),

                Textarea::make('motivo_estatus')
                    ->label('Motivo del estatus')
                    ->rows(2)->columnSpanFull()
                    ->visible(fn (Get $get) => in_array($get('estatus'), ['suspendido', 'lista_negra'])),

                Textarea::make('notas')->rows(3)->columnSpanFull(),
            ])->columns(2),
        ]),
    ]);
}
}
