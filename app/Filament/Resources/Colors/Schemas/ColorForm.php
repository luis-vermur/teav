<?php

namespace App\Filament\Resources\Colors\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Alta y edición de colores.
 *
 * El catálogo existe para que nadie escriba "gris oscuro", "Gris Oxford" y
 * "gris gunmetal" como si fueran tres cosas distintas. El hex se captura con
 * un selector, no a mano: escrito a mano se cuela "1B1B1B" sin almohadilla
 * y la muestra de la tabla se dibuja transparente.
 */
class ColorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Color')
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Gris Daytona')
                            ->unique(ignoreRecord: true)
                            ->validationMessages(['unique' => 'Ese color ya está en el catálogo.']),

                        ColorPicker::make('hex')
                            ->label('Muestra')
                            ->helperText('Se usa para pintar el color en los listados de la flota.'),
                    ])
                    ->columns(2),
            ]);
    }
}
