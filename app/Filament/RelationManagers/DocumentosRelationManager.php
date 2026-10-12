<?php

namespace App\Filament\RelationManagers;

use App\Models\TipoDocumento;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/**
 * Gestor de documentos compartido.
 *
 * Los documentos son polimórficos: cuelgan de clientes, vehículos y
 * contratos. La única diferencia entre un caso y otro es la carpeta donde
 * se guardan y el texto que se muestra cuando no hay nada, así que las
 * subclases sólo definen eso.
 *
 * Los tipos disponibles se filtran solos por `aplica_a`, con la clase del
 * registro dueño: un cliente ofrece INE y comprobante de domicilio, un
 * vehículo ofrece factura y tarjeta de circulación.
 */
abstract class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';

    protected static ?string $title = 'Documentos';

    protected static ?string $modelLabel = 'documento';

    protected static ?string $pluralModelLabel = 'documentos';

    /** Carpeta dentro del disco privado: "clientes", "unidades", "contratos". */
    abstract protected function carpeta(): string;

    /** Qué decirle al usuario cuando todavía no hay documentos. */
    abstract protected function queSubir(): string;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('tipo_documento_id')
                ->label('Tipo de documento')
                ->options(fn () => TipoDocumento::query()
                    ->where('aplica_a', $this->getOwnerRecord()::class)
                    ->where('activo', true)
                    ->orderBy('orden')
                    ->pluck('nombre', 'id'))
                ->required()
                ->live()
                ->searchable()
                // Los obligatorios que aún faltan, para no adivinar.
                ->helperText(function (): ?string {
                    $faltan = $this->getOwnerRecord()->documentosFaltantes();

                    return $faltan
                        ? 'Aún faltan: ' . implode(', ', $faltan)
                        : 'Ya está completo el expediente obligatorio';
                }),

            FileUpload::make('ruta')
                ->label('Archivo')
                ->disk('documentos')
                ->directory(fn () => $this->carpeta() . '/' . $this->getOwnerRecord()->id)
                ->visibility('private')
                // Cada tipo de documento define qué formatos acepta.
                ->acceptedFileTypes(fn (Get $get) => self::tipo($get)?->mimes_permitidos
                    ?? ['application/pdf', 'image/jpeg', 'image/png'])
                /**
                 * El tope real es el MENOR entre lo que permite el tipo de
                 * documento y lo que permite PHP.
                 *
                 * Anunciar 10 MB cuando php.ini corta en 2 hacía que la
                 * subida fallara sin explicación: PHP descarta la petición
                 * antes de que Laravel la vea, así que ni siquiera quedaba
                 * registro en el log.
                 */
                ->maxSize(fn (Get $get) => self::topeEnKb($get))
                ->helperText(fn (Get $get) => 'Máximo '
                    . number_format(self::topeEnKb($get) / 1024, 1) . ' MB')
                ->required()
                ->downloadable()
                ->previewable()
                ->columnSpanFull(),

            DatePicker::make('fecha_emision')->label('Fecha de emisión'),

            DatePicker::make('fecha_vencimiento')
                ->label('Fecha de vencimiento')
                ->helperText('Deja vacío si el documento no caduca'),

            Textarea::make('notas')->label('Notas')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    /** El tipo de documento elegido en el formulario, si ya se eligió. */
    protected static function tipo(Get $get): ?TipoDocumento
    {
        return filled($get('tipo_documento_id'))
            ? TipoDocumento::find($get('tipo_documento_id'))
            : null;
    }

    /**
     * Tope de subida en KB: el menor entre el del tipo de documento y el
     * de PHP. De nada sirve permitir 10 MB si el servidor corta en 2.
     */
    protected static function topeEnKb(Get $get): int
    {
        $porTipo = (int) (static::tipo($get)?->tamano_max_mb ?? 10) * 1024;
        $porPhp  = static::limitePhpEnKb();

        return max(1, min($porTipo, $porPhp));
    }

    /** Lo que PHP acepta de verdad: el menor entre upload_max_filesize y post_max_size. */
    protected static function limitePhpEnKb(): int
    {
        $aKb = function (string $valor): int {
            $valor = trim($valor);
            $n = (int) $valor;

            return match (strtolower(substr($valor, -1))) {
                'g'     => $n * 1024 * 1024,
                'm'     => $n * 1024,
                'k'     => $n,
                default => (int) ($n / 1024),   // venía en bytes
            };
        };

        return min(
            $aKb(ini_get('upload_max_filesize') ?: '2M'),
            // El POST lleva el archivo más el resto del formulario.
            (int) ($aKb(ini_get('post_max_size') ?: '8M') * 0.9),
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nombre_original')
            // Evita N+1: sin esto cada fila consulta tipo y usuario aparte.
            ->modifyQueryUsing(fn ($query) => $query->with(['tipoDocumento', 'subidoPor']))
            ->columns([
                TextColumn::make('tipoDocumento.nombre')
                    ->label('Tipo')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('nombre_original')
                    ->label('Archivo')
                    ->description(fn ($record) => $record->tamano_legible)
                    ->limit(35),

                TextColumn::make('fecha_vencimiento')
                    ->label('Vence')
                    ->date('d/M/Y')
                    ->badge()
                    ->color(fn ($record) => match (true) {
                        $record->fecha_vencimiento === null => 'gray',
                        $record->estaVencido()              => 'danger',
                        $record->estaPorVencer()            => 'warning',
                        default                             => 'success',
                    })
                    ->placeholder('No caduca'),

                IconColumn::make('vigente')
                    ->label('Vigente')
                    ->boolean(),

                TextColumn::make('subidoPor.name')
                    ->label('Subió')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Cargado')
                    ->since()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Subir documento')
                    /**
                     * Los metadatos del archivo se derivan del propio archivo:
                     * pedirlos en el formulario sería pedirle al usuario datos
                     * que el sistema ya puede ver.
                     */
                    ->mutateDataUsing(function (array $data): array {
                        $disco = Storage::disk('documentos');
                        $ruta  = $data['ruta'];

                        $data['nombre_original'] = basename($ruta);
                        $data['disco']           = 'documentos';
                        $data['mime']            = $disco->mimeType($ruta);
                        $data['tamano']          = $disco->size($ruta);
                        /**
                         * hash_file() lee el archivo por partes; hash() con
                         * ->get() lo cargaba ENTERO en memoria. Con PDFs de
                         * 30-40 MB eso desperdicia memoria sin necesidad y
                         * puede tumbar la petición.
                         */
                        $data['hash']            = hash_file('sha256', $disco->path($ruta));
                        $data['subido_por']      = auth()->id();
                        $data['vigente']         = true;

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('descargar')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => $record->urlDescarga(), shouldOpenInNewTab: true),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Sin documentos')
            ->emptyStateDescription($this->queSubir());
    }
}
