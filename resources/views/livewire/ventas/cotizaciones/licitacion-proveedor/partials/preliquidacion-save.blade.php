        <div class="preliq-save">
            <button type="button" x-on:click="saveRows()" x-bind:disabled="saving || uploading || !@js((bool)$selectedRfqDocument?->supplier)" class="{{ $primaryButton }}">
                <span x-text="saving ? 'Guardando…' : 'Guardar preliquidación'"></span>
            </button>
        </div>
