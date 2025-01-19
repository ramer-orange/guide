<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirectIntended(route('itineraries.index'));
    }
}; ?>

<div>
    <form wire:submit="register">
        <div class="mt-7 bg-white border border-gray-200 rounded-xl shadow-xs dark:bg-neutral-900 dark:border-neutral-700 max-w-lg mx-auto">
            <div class="p-4 sm:p-8">
                <!-- ヘッダー -->
                <div class="text-center">
                    <h1 class="block text-3xl font-extrabold text-gray-800 dark:text-white">会員登録</h1>
                    <p class="text-sm text-gray-600 dark:text-neutral-400 mt-4">
                        すでにアカウントを持っていますか？
                        <br class="md:hidden">
                        <a class="text-blue-600 decoration-2 hover:underline focus:outline-hidden focus:underline font-medium dark:text-blue-500"
                           href="{{ 'login' }}">
                            ログインへ
                        </a>
                    </p>
                </div>

                <!-- Name -->
                <div class="mt-4">
                    <x-input-label for="name" :value="__('Name')"/>
                    <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required
                                  autofocus autocomplete="name"/>
                    <x-input-error :messages="$errors->get('name')" class="mt-2"/>
                </div>

                <!-- Email Address -->
                <div class="mt-4">
                    <x-input-label for="email" :value="__('Email')"/>
                    <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email"
                                  required
                                  autocomplete="username"/>
                    <x-input-error :messages="$errors->get('email')" class="mt-2"/>
                </div>

                <!-- Password -->
                <div class="mt-4">
                    <x-input-label for="password" :value="__('Password')"/>

                    <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                                  type="password"
                                  name="password"
                                  required autocomplete="new-password"/>

                    <x-input-error :messages="$errors->get('password')" class="mt-2"/>
                </div>

                <!-- Confirm Password -->
                <div class="mt-4">
                    <x-input-label for="password_confirmation" :value="__('Confirm Password')"/>

                    <x-text-input wire:model="password_confirmation" id="password_confirmation"
                                  class="block mt-1 w-full"
                                  type="password"
                                  name="password_confirmation" required autocomplete="new-password"/>

                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2"/>
                </div>

                <div class="flex items-center justify-end">
                    <x-submit-button class="ms-4">
                        登録
                    </x-submit-button>
                </div>
            </div>
        </div>
    </form>
</div>
