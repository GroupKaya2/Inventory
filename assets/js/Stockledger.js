'use strict';

class StockLedger {
    constructor(initialBatches = []) {
        this._batches = initialBatches.map(b => ({ ...b }));
    }

    pushBatch(batch) {
        if (batch.qty <= 0) throw new RangeError('Batch quantity must be > 0');
        this._batches.push({ ...batch });
    }

    consumeFIFO(qtyNeeded) {
        return this._consume(qtyNeeded, true);
    }

    consumeLIFO(qtyNeeded) {
        return this._consume(qtyNeeded, false);
    }

    _consume(qtyNeeded, fifo) {
        let remaining = qtyNeeded;
        const layers = [];
        let totalCost = 0;

        while (remaining > 0 && this._batches.length > 0) {
            const idx   = fifo ? 0 : this._batches.length - 1;
            const batch = this._batches[idx];
            const take  = Math.min(batch.qty, remaining);

            layers.push({
                batchId:   batch.batchId,
                date:      batch.date,
                qtyUsed:   take,
                unitCost:  batch.unitCost,
                lineTotal: +(take * batch.unitCost).toFixed(2),
            });

            totalCost  += take * batch.unitCost;
            batch.qty  -= take;
            remaining  -= take;

            if (batch.qty === 0) this._batches.splice(idx, 1);
        }

        return {
            layers,
            totalCost: +totalCost.toFixed(2),
            shortfall: remaining,
        };
    }

    get totalStock() {
        return this._batches.reduce((sum, b) => sum + b.qty, 0);
    }

    costOf(qty, method = 'FIFO') {
        const clone = new StockLedger(this._batches);
        return method === 'LIFO' ? clone.consumeLIFO(qty) : clone.consumeFIFO(qty);
    }

    peek(method = 'FIFO') {
        if (!this._batches.length) return null;
        const b = method === 'FIFO' ? this._batches[0] : this._batches[this._batches.length - 1];
        return { ...b };
    }

    snapshot() {
        return this._batches.map(b => ({ ...b }));
    }
}