<div class="mt-20 flex justify-end">
    <a href="{{ route('itineraries.index') }}" class="relative inline-block px-4 py-2 font-medium group"
       aria-label="{{ $slot }}" title="{{ $slot }}">
        <span
            class="absolute inset-0 w-full h-full transition duration-200 ease-out transform translate-x-1 translate-y-1 bg-black group-hover:-translate-x-0 group-hover:-translate-y-0"></span>
        <span
            class="absolute inset-0 w-full h-full bg-white border-2 border-black group-hover:bg-black"></span>

        <span class="relative flex items-center text-black group-hover:text-white">
            {{ $slot }}
            <!-- 右矢印アイコン -->
            <svg xmlns="http://www.w3.org/2000/svg"
                 class="h-5 w-5 mr-2"
                 viewBox="0 0 20 20"
                 fill="currentColor"
                 aria-hidden="true">
                <path fill-rule="evenodd"
                      d="M10.293 15.707a1 1 0 010-1.414L13.586 10 10.293 6.707a1 1 0 011.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z"
                      clip-rule="evenodd"/>
            </svg>
        </span>
    </a>
</div>
