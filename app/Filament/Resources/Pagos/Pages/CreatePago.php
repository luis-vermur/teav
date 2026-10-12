<?php

namespace App\Filament\Resources\Pagos\Pages;

use App\Filament\Resources\Pagos\PagoResource;
use App\Models\Pago;
use App\Services\PagoService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * Alta de un pago desde el módulo de Pagos.
 *
 * Había dos caminos para registrar un pago y no hacían lo mismo:
 *
 *   • Desde el contrato, la acción llamaba a PagoService::registrar(), que
 *     crea el pago Y lo reparte entre recargos y exhibiciones.
 *   • Desde aquí, el alta era la que genera Filament: guardaba el registro
 *     y nada más. El dinero entraba a la base pero el contrato seguía
 *     reclamando la exhibición como pendiente, y nadie relacionaba una cosa
 *     con la otra hasta la conciliación.
 *
 * Es el mismo defecto que dejó un pago de $40,000 sin acreditar. Ahora los
 * dos caminos terminan en PagoService y se comportan igual.
 */
class CreatePago extends CreateRecord
{
    protected static string $resource = PagoResource::class;

    /** Cuánto alcanzó a acreditarse, para decirlo en la notificación. */
    protected float $acreditado = 0;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Quién lo capturó no se teclea: sale de la sesión.
        $data['registrado_por'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Pago $pago */
        $pago = $this->getRecord();

        // Un pago rechazado no se acredita a nada, y sin contrato no hay
        // exhibiciones contra las cuales repartirlo.
        if ($pago->estatus === 'rechazado' || $pago->contrato === null) {
            return;
        }

        $antes = $pago->montoDisponible();

        app(PagoService::class)->aplicar($pago);

        $pago->refresh();

        $this->acreditado = round($antes - $pago->montoDisponible(), 2);
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var Pago $pago */
        $pago = $this->getRecord();

        if ($this->acreditado <= 0) {
            return Notification::make()
                ->title("Pago {$pago->folio} registrado")
                ->body($pago->contrato === null
                    ? 'No está ligado a un contrato, así que no se acreditó a ninguna exhibición.'
                    : 'Quedó registrado sin acreditar. Usa «Aplicar al contrato» cuando corresponda.')
                ->warning();
        }

        return Notification::make()
            ->title("Pago {$pago->folio} registrado y aplicado")
            ->body('Se acreditaron $' . number_format($this->acreditado, 2)
                . ($pago->saldo_a_favor > 0
                    ? '. Quedaron $' . number_format((float) $pago->saldo_a_favor, 2) . ' a favor del cliente.'
                    : '.'))
            ->success();
    }

    /** Termina en la ficha del pago: ahí se ve el reparto que acaba de hacerse. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
