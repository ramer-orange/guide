<div>
    <div>
        <input type="file" wire:model="file">

        @error('file') <span class="error">{{ $message }}</span> @enderror
    </div>

</div>
