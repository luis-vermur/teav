<?php

namespace App\Filament\Resources\Proveedors\Schemas;

use App\Filament\Forms\CampoRfc;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Alta y edición de proveedores.
 *
 * `tipos` es una columna json casteada a array en el modelo. Antes se
 * capturaba con un TextInput, así que al guardar quedaba la cadena
 * "taller" donde el resto del sistema espera ["taller"]: el badge de la
 * ficha y cualquier in_array() se rompen con ese formato. Por eso es un
 * Select múltiple, que además evita que cada quien invente su propia
 * ortografía ("gestoria", "Gestoría", "gestorías").
 */
class ProveedorForm
{
    /** Los mismos que pinta ProveedorInfolist. */
    public const TIPOS = [
        'agencia'      => 'Agencia',
        'taller'       => 'Taller',
        'llantera'     => 'Llantera',
        'gestoria'     => 'Gestoría',
        'verificentro' => 'Verificentro',
        'aseguradora'  => 'Aseguradora',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Quién es')
                    ->description('Un mismo proveedor puede ser agencia, taller y gestoría a la vez.')
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre o razón social')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Audi Center Querétaro')
                            ->columnSpan(2),

                        CampoRfc::make(),

                        Select::make('tipos')
                            ->label('Qué maneja')
                            ->options(self::TIPOS)
                            ->multiple()
                            ->required()
                            ->helperText('Marca todos los que apliquen.')
                            ->columnSpan(2),

                        Toggle::make('activo')
                            ->label('Proveedor activo')
                            ->helperText('Los inactivos dejan de aparecer al registrar servicios.')
                            ->default(true),
                    ])
                    ->columns(3),

                Section::make('Contacto')
                    ->description('Con quién se habla cuando un auto está en el taller.')
                    ->schema([
                        TextInput::make('contacto_nombre')
                            ->label('Persona de contacto')
                            ->maxLength(255),

                        TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('442 000 0000'),

                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(3),

                Section::make('Notas')
                    ->collapsed()
                    ->schema([
                        Textarea::make('notas')
                            ->label('')
                            ->rows(3)
                            ->placeholder('Días de entrega, descuentos negociados, con quién no tratar...')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
