<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(route('home'));
    }
}; ?>

<div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')"/>

    <form wire:submit="login">
        <div
            class="mt-7 bg-white border border-gray-200 rounded-xl shadow-xs dark:bg-neutral-900 dark:border-neutral-700 max-w-lg mx-auto">
            <div class="p-4 sm:p-8">
                <!-- ヘッダー -->
                <div class="text-center">
                    <h1 class="block text-3xl font-extrabold text-gray-800 dark:text-white">ログイン</h1>
                    <p class="text-sm text-gray-600 dark:text-neutral-400 mt-4">
                        会員登録を済ませていませんか？
                        <br class="md:hidden">
                        <a class="text-blue-600 decoration-2 hover:underline focus:outline-hidden focus:underline font-medium dark:text-blue-500"
                           href="{{ 'register' }}">
                            会員登録へ
                        </a>
                    </p>
                </div>

                <!-- Email Address -->
                <div class="mt-4">
                    <x-input-label for="email" :value="__('Email')"/>
                    <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email"
                                  required autofocus autocomplete="username"/>
                    <x-input-error :messages="$errors->get('form.email')" class="mt-2"/>
                </div>

                <!-- Password -->
                <div class="mt-4">
                    <x-input-label for="password" :value="__('Password')"/>

                    <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                                  type="password"
                                  name="password"
                                  required autocomplete="current-password"/>

                    <x-input-error :messages="$errors->get('form.password')" class="mt-2"/>
                    <div class="mt-2">
                        @if (Route::has('password.request'))
                            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                               href="{{ route('password.request') }}" wire:navigate>
                                {{ __('Forgot your password?') }}
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="block mt-4">
                    <label for="remember" class="inline-flex items-center">
                        <input wire:model="form.remember" id="remember" type="checkbox"
                               class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
                               name="remember">
                        <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                    </label>
                </div>

                <div class="flex items-center justify-end">
                    <x-submit-button class="ms-4">
                        ログイン
                    </x-submit-button>
                </div>
            </div>
        </div>
    </form>
</div>
