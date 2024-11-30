<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

class UploadFiles extends Component
{
    use WithFileUploads;

    public $file;

    public function save()
    {
        $this->file->store(path: 'files');
    }
    public function render()
    {
        return view('livewire.upload-files');
    }
}
