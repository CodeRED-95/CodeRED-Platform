<div>
    <form wire:submit.prevent="sync">
        <div>
            <label for="chosenFile">Archivo Chosen</label>
            <input type="file" id="chosenFile" wire:model="chosenFile">
            @error('chosenFile') <span class="error">{{ $message }}</span> @enderror
        </div>

        <button type="submit">Sincronizar</button>
    </form>
</div>
