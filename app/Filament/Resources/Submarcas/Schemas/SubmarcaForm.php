<?php

namespace App\Filament\Resources\Submarcas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de modelos (submarcas).
 *
 * El nombre sólo es único dentro de su marca: existe un "Ibiza" de Seat y
 * podría existir otro de otra marca. Por eso la regla de unicidad lleva la
 * marca dentro.
 */
class SubmarcaForm
{
    public const SEGMENTOS = [
        'Sedán'     => 'Sedán',
        'Hatchback' => 'Hatchback',
        'SUV'       => 'SUV',
        'Crossover' => 'Crossover',
        'Pickup'    => 'Pickup',
        'Van'       => 'Van',
        'Deportivo' => 'Deportivo',
        'Eléctrico' => 'Eléctrico',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Modelo')
                ->description('Es lo que se elige al dar de alta un vehículo: marca + modelo + año.')
                ->schema([
                    Select::make('marca_id')
                        ->label('Marca')
                        ->relationship('marca', 'nombre')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->createOptionForm([
                            TextInput::make('nombre')->label('Marca')->required()->maxLength(255),
                            Select::make('pais_origen')
                                ->label('País de origen')
                                ->options(\App\Filament\Resources\Marcas\Schemas\MarcaForm::PAISES)
                                ->searchable(),
                        ])
                        ->createOptionModalHeading('Nueva marca'),

                    TextInput::make('nombre')
                        ->label('Modelo')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Q3')
                        ->rule(fn (Get $get, ?object $record) => Rule::unique('submarcas', 'nombre')
                            ->where('marca_id', $get('marca_id'))
                            ->ignore($record?->id))
                        ->validationMessages(['unique' => 'Esa marca ya tiene un modelo con ese nombre.']),

                    Select::make('segmento')
                        ->label('Segmento')
                        ->options(self::SEGMENTOS)
                        ->placeholder('Sin clasificar')
                        ->helperText('Sirve para comparar rentabilidad entre tipos de auto.'),

                    Toggle::make('activo')
                        ->label('Modelo activo')
                        ->helperText('Los inactivos no aparecen al dar de alta un vehículo.')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }
}
