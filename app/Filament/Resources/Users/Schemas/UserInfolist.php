<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ficha del usuario.
 *
 * Es una ficha de seguridad, no de directorio: lo que se revisa aquí es qué
 * puede hacer esta cuenta. Por eso los permisos efectivos se listan en
 * claro, agrupados por módulo, en vez de dejarlos escondidos tras el rol.
 */
class UserInfolist
{
    /** Permisos que conviene ver de un vistazo por lo que implican. */
    private const SENSIBLES = ['condonar_adeudo', 'cerrar_contrato', 'reversar_pago', 'crear_usuario', 'eliminar_usuario'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make()
                ->schema([
                    TextEntry::make('roles.name')
                        ->label('Rol')
                        ->badge()
                        ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', (string) $state)))
                        ->color(fn ($state) => match ($state) {
                            'administrador' => 'danger',
                            'gerente'       => 'warning',
                            'cobranza'      => 'purple',
                            'flotilla'      => 'info',
                            'operador'      => 'info',
                            default         => 'gray',
                        })
                        ->placeholder('Sin rol asignado'),

                    TextEntry::make('permisos_total')
                        ->label('Permisos efectivos')
                        ->state(fn ($record) => $record->getAllPermissions()->count())
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'info' : 'danger')
                        ->helperText(fn ($record) => $record->getAllPermissions()->isEmpty()
                            ? 'Esta cuenta entra al panel pero no ve ningún módulo.'
                            : null),

                    TextEntry::make('email_verified_at')
                        ->label('Correo')
                        ->badge()
                        ->state(fn ($record) => $record->email_verified_at ? 'Verificado' : 'Sin verificar')
                        ->color(fn ($record) => $record->email_verified_at ? 'success' : 'gray'),

                    TextEntry::make('created_at')
                        ->label('Dado de alta')
                        ->date('d/M/Y'),
                ])
                ->columns(4),

            Section::make('Datos de acceso')
                ->schema([
                    TextEntry::make('name')
                        ->label('Nombre'),

                    TextEntry::make('email')
                        ->label('Correo electrónico')
                        ->copyable()
                        ->copyMessage('Correo copiado'),
                ])
                ->columns(2),

            /**
             * Los cuatro permisos que mueven dinero o dan acceso total. Si
             * los tiene, se dicen en rojo: no deberían pasar desapercibidos
             * en una revisión.
             */
            Section::make('Permisos delicados')
                ->description('Los que condonan adeudos, cierran contratos, reversan pagos o dan de alta y borran usuarios')
                ->schema([
                    TextEntry::make('permisos_sensibles')
                        ->label('')
                        ->state(fn ($record) => $record->getAllPermissions()
                            ->pluck('name')
                            ->intersect(self::SENSIBLES)
                            ->map(fn ($p) => ucfirst(str_replace('_', ' ', $p)))
                            ->values()
                            ->all())
                        ->badge()
                        ->color('danger')
                        ->placeholder('Ninguno. Esta cuenta no puede condonar, cerrar ni reversar.')
                        ->columnSpanFull(),
                ]),

            Section::make('Todos los permisos')
                ->description('Lo que el rol le concede, en claro')
                ->collapsed()
                ->schema([
                    TextEntry::make('permisos')
                        ->label('')
                        ->state(fn ($record) => $record->getAllPermissions()
                            ->pluck('name')
                            ->sort()
                            ->map(fn ($p) => ucfirst(str_replace('_', ' ', $p)))
                            ->values()
                            ->all())
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->placeholder('Sin permisos: asígnale un rol.')
                        ->columnSpanFull(),
                ])
                ->columns(1),
        ]);
    }
}
