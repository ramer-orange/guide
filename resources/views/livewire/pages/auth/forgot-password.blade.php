<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div class="mt-7 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-neutral-900 dark:border-neutral-700 max-w-lg mx-auto">
    <div class="p-4 sm:p-8">
        <!-- ヘッダー -->
        <div>
            <p class="text-sm text-gray-600 dark:text-neutral-400 mt-4">
                パスワードをお忘れですか？<br>
                ご登録時のメールアドレスを入力頂ければ、<br>パスワードリセット用のリンクをメールで送信いたします。
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')"/>

        <form wire:submit="sendPasswordResetLink">
            <!-- Email Address -->
            <div class="mt-4">
                <x-input-label for="email" :value="__('Email')"/>
                <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required
                              autofocus/>
                <x-input-error :messages="$errors->get('email')" class="mt-2"/>
            </div>

            <div class="flex items-center justify-end">
                <x-submit-button class="ms-4">
                    メールを送信
                </x-submit-button>
            </div>
        </form>
    </div>
</div>
