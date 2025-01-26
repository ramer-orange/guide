@extends('layouts.app')

@section('title', 'しおり作成')

@section('content')
    <div class="bg-[#fffdfa]">
        <div class="max-w-7xl mx-auto p-4 pb-8 sm:p-6 sm:pb-12 dark:bg-gray-800 min-h-screen relative">
            @auth
                <!-- 一覧を見るボタン -->
                <x-button.toIndex-button>しおり一覧へ</x-button.toIndex-button>
            @endauth
            <div class="flex justify-center mt-28">
                <div class="max-w-3xl w-full">
                    <h1 class="text-4xl font-extrabold mb-21 text-gray-900 text-center">しおり作成</h1>
                    <livewire:plans-form/>
                </div>
            </div>
        </div>
    </div>
@endsection
