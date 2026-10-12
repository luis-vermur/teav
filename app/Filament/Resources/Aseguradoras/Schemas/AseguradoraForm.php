<?php

namespace App\Filament\Resources\Aseguradoras\Schemas;

use App\Filament\Forms\CampoRfc;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Alta y edición de aseguradoras.
 *
 * El teléfono de siniestros se pide aparte y con ayuda visible porque es el
 * único dato que alguien va a buscar con prisa, desde el celular y con el
 * auto chocado enfrente.
 */
class AseguradoraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Identificación')
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre comercial')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Qualitas, GNP, AXA...')
                            ->columnSpan(2),

                        CampoRfc::make(),
                    ])
                    ->columns(3),

                Section::make('A quién llamar')
                    ->description('Datos que se usan cuando hay un siniestro o hay que renovar una póliza.')
                    ->schema([
                        TextInput::make('telefono_siniestros')
                            ->label('Teléfono de siniestros (24 h)')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('800 000 0000')
                            ->helperText('El que se marca desde la carretera. Conviene llenarlo siempre.')
                            ->columnSpanFull(),

                        TextInput::make('contacto_nombre')
                            ->label('Ejecutivo de cuenta')
                            ->maxLength(255),

                        TextInput::make('contacto_email')
                            ->label('Correo del ejecutivo')
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make()
                    ->schema([
                        Toggle::make('activo')
                            ->label('Aseguradora activa')
                            ->helperText('Las inactivas dejan de aparecer al emitir una póliza nueva.')
                            ->default(true),
                    ]),
            ]);
    }
}
