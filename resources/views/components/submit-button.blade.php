<div class="mt-6 flex justify-center">
    <button type="submit"
            class="relative inline-block px-8 py-4 font-semibold text-xl group">
        <span
            class="absolute inset-0 w-full h-full transition duration-300 ease-out transform translate-x-2 translate-y-2 bg-black group-hover:translate-x-0 group-hover:translate-y-0"></span>
        <span
            class="absolute inset-0 w-full h-full bg-white border-2 border-black transition duration-300 ease-out group-hover:bg-black"></span>
        <span class="relative flex items-center text-black group-hover:text-white">
            {{ $slot }}
        </span>
    </button>
</div>
