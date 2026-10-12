<?php

namespace App\Filament\Resources\Marcas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Alta y edición de marcas.
 *
 * El país de origen no es decorativo: sirve para agrupar la flota por
 * procedencia cuando se negocian refacciones o tiempos de taller.
 */
class MarcaForm
{
    /** Los más comunes en el mercado mexicano; el campo admite otros. */
    public const PAISES = [
        'Alemania'      => 'Alemania',
        'Japón'         => 'Japón',
        'Estados Unidos' => 'Estados Unidos',
        'Corea del Sur' => 'Corea del Sur',
        'México'        => 'México',
        'Francia'       => 'Francia',
        'Italia'        => 'Italia',
        'Reino Unido'   => 'Reino Unido',
        'China'         => 'China',
        'India'         => 'India',
        'Suecia'        => 'Suecia',
        'España'        => 'España',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Marca')
                    ->description('Agrupa a los modelos: Audi → Q3, Q5, A4.')
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Audi')
                            ->unique(ignoreRecord: true)
                            ->validationMessages(['unique' => 'Esa marca ya está dada de alta.']),

                        Select::make('pais_origen')
                            ->label('País de origen')
                            ->options(self::PAISES)
                            ->searchable()
                            ->placeholder('Sin especificar')
                            /**
                             * La lista no es cerrada: si llega una marca de un
                             * país que no está, se da de alta desde aquí sin
                             * tocar el código. `createOptionUsing` devuelve el
                             * valor que queda seleccionado.
                             */
                            ->createOptionForm([
                                TextInput::make('pais')
                                    ->label('País')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(fn (array $data) => $data['pais'])
                            ->createOptionModalHeading('Otro país de origen'),

                        Toggle::make('activo')
                            ->label('Marca activa')
                            ->helperText('Las inactivas no aparecen al dar de alta un vehículo.')
                            ->default(true)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
