<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

/**
 * Alta y edición de usuarios del panel.
 *
 * Dos detalles que importan:
 *  - La contraseña sólo se escribe si se captura algo. En edición, dejarla
 *    vacía conserva la que ya tenía (`dehydrated` en false).
 *  - El rol es obligatorio: sin rol el usuario entra al panel y no ve nada,
 *    lo que parece una falla del sistema y no una cuenta mal dada de alta.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Quién es')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre completo')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->validationMessages(['unique' => 'Ya hay una cuenta con ese correo.'])
                            ->helperText('Es con lo que inicia sesión.'),
                    ])
                    ->columns(2),

                Section::make('Acceso')
                    ->description('Qué puede hacer dentro del panel.')
                    ->schema([
                        Select::make('roles')
                            ->label('Rol')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->required()
                            ->getOptionLabelFromRecordUsing(fn ($record) => ucfirst(str_replace('_', ' ', $record->name)))
                            ->helperText('Sin rol, la cuenta entra al panel pero no ve ningún módulo.')
                            ->columnSpanFull(),

                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->maxLength(255)
                            ->same('password_confirmation')
                            ->validationMessages(['same' => 'Las dos contraseñas no coinciden.'])
                            // Vacía en edición = se conserva la actual.
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->helperText(fn (string $operation) => $operation === 'edit'
                                ? 'Déjala vacía para no cambiarla.'
                                : 'Mínimo 8 caracteres.'),

                        TextInput::make('password_confirmation')
                            ->label('Repite la contraseña')
                            ->password()
                            ->revealable()
                            ->dehydrated(false)
                            ->required(fn (string $operation) => $operation === 'create'),

                        Toggle::make('email_verified_at')
                            ->label('Cuenta verificada')
                            ->helperText('Márcala si no vas a mandar el correo de verificación.')
                            ->formatStateUsing(fn ($state) => filled($state))
                            // Si ya estaba verificada, se respeta la fecha
                            // original en vez de sellarla de nuevo en cada
                            // guardado.
                            ->dehydrateStateUsing(fn ($state, ?object $record) => $state
                                ? ($record?->email_verified_at ?? now())
                                : null)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
