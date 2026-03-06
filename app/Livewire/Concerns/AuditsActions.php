<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Log;

trait AuditsActions
{
    protected function auditLog(string $message): void
    {
        Log::info('User '.auth()->id().' ('.auth()->user()->name.") {$message}");
    }
}
