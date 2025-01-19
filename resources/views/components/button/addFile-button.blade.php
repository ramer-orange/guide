<span class="relative inline-block text-base group cursor-pointer">
    <span
        class="relative z-10 block px-4 py-2 overflow-hidden font-medium leading-tight text-gray-800 transition-colors duration-300 ease-out border border-gray-900 rounded-md group-hover:text-white">
        <span
            class="absolute inset-0 w-full h-full px-4 py-2 rounded-md bg-gray-50"></span>
        <span
            class="absolute left-0 w-40 h-40 -ml-2 transition-all duration-300 origin-top-right -rotate-90 -translate-x-full translate-y-12 bg-gray-900 group-hover:-rotate-180 ease"></span>
        <span class="relative">{{ $slot }}</span>
    </span>
    <span
        class="absolute bottom-0 right-0 w-full h-8 -mb-1 -mr-1 transition-all duration-200 ease-linear bg-gray-900 rounded-md group-hover:mb-0 group-hover:mr-0"
        data-rounded="rounded-md"></span>
</span>
