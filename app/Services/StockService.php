<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\StockRequestStatus;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\WarehouseUnit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu-satunya tempat stok gudang boleh berubah.
 *
 * Semua operasi mengunci baris warehouse_units (lockForUpdate) di dalam transaksi,
 * supaya dua approval bersamaan tidak bisa membooking stok yang sama.
 *
 * Alur dua tahap (lihat StockRequestStatus):
 *   approve  -> qty_available turun, qty_borrowed naik   (movement: borrow)
 *   release  -> unit fisik keluar gudang, qty tidak berubah (movement: stock_out)
 *   return   -> qty_available naik, qty_borrowed turun   (movement: return)
 */
class StockService
{
    /**
     * Booking stok untuk request yang disetujui.
     *
     * @throws RuntimeException bila ada unit yang stoknya tidak cukup
     */
    public function book(StockRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $request->loadMissing('units');

            foreach ($request->units as $line) {
                $stock = $this->lockStock($request->warehouse_id, $line->unit_id);
                $qty = (int) $line->qty;

                if ($stock->qty_available < $qty) {
                    throw new RuntimeException(
                        "Stok {$line->unit?->name} tidak cukup: tersedia {$stock->qty_available}, diminta {$qty}."
                    );
                }

                $before = (int) $stock->qty_available;

                $stock->update([
                    'qty_available' => $before - $qty,
                    'qty_borrowed' => (int) $stock->qty_borrowed + $qty,
                ]);

                $this->recordMovement(
                    request: $request,
                    unitId: $line->unit_id,
                    type: StockMovementType::Borrow,
                    qty: $qty,
                    before: $before,
                    after: $before - $qty,
                    notes: 'Booking approve request #'.$request->id,
                );
            }

            $request->update([
                'status' => StockRequestStatus::Approved,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });
    }

    /**
     * Unit fisik keluar gudang. Jumlah stok tidak berubah di tahap ini —
     * baris movement hanya jejak fisik, jadi stock_before = stock_after.
     */
    public function release(StockRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $request->loadMissing('units');

            foreach ($request->units as $line) {
                $stock = $this->lockStock($request->warehouse_id, $line->unit_id);
                $available = (int) $stock->qty_available;

                $this->recordMovement(
                    request: $request,
                    unitId: $line->unit_id,
                    type: StockMovementType::StockOut,
                    qty: (int) $line->qty,
                    before: $available,
                    after: $available,
                    notes: 'Unit keluar gudang, request #'.$request->id,
                );
            }

            $request->update([
                'status' => StockRequestStatus::Released,
                'released_by' => auth()->id(),
                'released_at' => now(),
            ]);
        });
    }

    /**
     * Pengembalian sebagian atau seluruhnya.
     *
     * @param  array<int|string, int>  $quantities  stock_request_unit_id => qty dikembalikan
     *
     * @throws RuntimeException bila qty melebihi yang belum kembali
     */
    public function returnUnits(StockRequest $request, array $quantities): void
    {
        DB::transaction(function () use ($request, $quantities) {
            $request->loadMissing('units.unit');

            foreach ($request->units as $line) {
                $qty = (int) ($quantities[$line->id] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                if ($qty > $line->outstandingQty()) {
                    throw new RuntimeException(
                        "Pengembalian {$line->unit?->name} melebihi sisa: sisa {$line->outstandingQty()}, diisi {$qty}."
                    );
                }

                $stock = $this->lockStock($request->warehouse_id, $line->unit_id);
                $before = (int) $stock->qty_available;

                $stock->update([
                    'qty_available' => $before + $qty,
                    'qty_borrowed' => max(0, (int) $stock->qty_borrowed - $qty),
                ]);

                $line->update(['returned_qty' => (int) $line->returned_qty + $qty]);

                $this->recordMovement(
                    request: $request,
                    unitId: $line->unit_id,
                    type: StockMovementType::Return,
                    qty: $qty,
                    before: $before,
                    after: $before + $qty,
                    notes: 'Pengembalian request #'.$request->id,
                );
            }

            $request->load('units');

            $request->update([
                'status' => $request->isFullyReturned()
                    ? StockRequestStatus::Returned
                    : StockRequestStatus::PartiallyReturned,
            ]);
        });
    }

    /**
     * Melepas booking saat request dibatalkan setelah disetujui.
     * Hanya melepas sisa yang belum dikembalikan.
     */
    public function releaseBooking(StockRequest $request, StockRequestStatus $newStatus): void
    {
        DB::transaction(function () use ($request, $newStatus) {
            $request->loadMissing('units');

            foreach ($request->units as $line) {
                $qty = $line->outstandingQty();

                if ($qty <= 0) {
                    continue;
                }

                $stock = $this->lockStock($request->warehouse_id, $line->unit_id);
                $before = (int) $stock->qty_available;

                $stock->update([
                    'qty_available' => $before + $qty,
                    'qty_borrowed' => max(0, (int) $stock->qty_borrowed - $qty),
                ]);

                $this->recordMovement(
                    request: $request,
                    unitId: $line->unit_id,
                    type: StockMovementType::Return,
                    qty: $qty,
                    before: $before,
                    after: $before + $qty,
                    notes: 'Booking dilepas ('.$newStatus->getLabel().') request #'.$request->id,
                );
            }

            $request->update(['status' => $newStatus]);
        });
    }

    /** Stok masuk ke gudang (pembelian/kiriman baru). */
    public function stockIn(int $warehouseId, string $unitId, int $qty, ?string $notes = null): void
    {
        $this->adjustTotal($warehouseId, $unitId, $qty, StockMovementType::StockIn, $notes);
    }

    /** Koreksi stok manual, positif maupun negatif. */
    public function adjust(int $warehouseId, string $unitId, int $delta, ?string $notes = null): void
    {
        $this->adjustTotal(
            $warehouseId,
            $unitId,
            $delta,
            $delta >= 0 ? StockMovementType::AdjustmentPlus : StockMovementType::AdjustmentMinus,
            $notes,
        );
    }

    private function adjustTotal(int $warehouseId, string $unitId, int $delta, StockMovementType $type, ?string $notes): void
    {
        DB::transaction(function () use ($warehouseId, $unitId, $delta, $type, $notes) {
            $stock = $this->lockStock($warehouseId, $unitId, createIfMissing: true);
            $before = (int) $stock->qty_available;

            if ($delta < 0 && $before < abs($delta)) {
                throw new RuntimeException("Stok tersedia hanya {$before}, tidak bisa dikurangi ".abs($delta).'.');
            }

            $stock->update([
                'qty_total' => (int) $stock->qty_total + $delta,
                'qty_available' => $before + $delta,
            ]);

            $this->recordMovement(
                request: null,
                unitId: $unitId,
                type: $type,
                qty: abs($delta),
                before: $before,
                after: $before + $delta,
                notes: $notes,
                warehouseId: $warehouseId,
            );
        });
    }

    /** @throws RuntimeException bila belum ada baris stok untuk unit tersebut */
    private function lockStock(int $warehouseId, string $unitId, bool $createIfMissing = false): WarehouseUnit
    {
        $stock = WarehouseUnit::where('warehouse_id', $warehouseId)
            ->where('unit_id', $unitId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        if (! $createIfMissing) {
            throw new RuntimeException('Unit ini belum punya baris stok di gudang tersebut.');
        }

        return WarehouseUnit::create([
            'warehouse_id' => $warehouseId,
            'unit_id' => $unitId,
            'qty_total' => 0,
            'qty_available' => 0,
            'qty_borrowed' => 0,
        ]);
    }

    private function recordMovement(
        ?StockRequest $request,
        string $unitId,
        StockMovementType $type,
        int $qty,
        int $before,
        int $after,
        ?string $notes = null,
        ?int $warehouseId = null,
    ): void {
        StockMovement::create([
            'warehouse_id' => $warehouseId ?? $request?->warehouse_id,
            'unit_id' => $unitId,
            'stock_request_id' => $request?->id,
            'movement_type' => $type,
            'qty' => $qty,
            'stock_before' => $before,
            'stock_after' => $after,
            'notes' => $notes,
            'created_by' => auth()->id(),
        ]);
    }
}
