@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between" diff:key="pagination-nav-{{ $paginator->currentPage() }}">
        <div class="flex-1 flex justify-between md:hidden">
            <button 
                diff:click="previousPage" 
                class="relative inline-flex items-center px-4 py-2 text-sm font-medium bg-white border border-gray-300 leading-5 rounded-md transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed {{ $paginator->onFirstPage() ? 'text-gray-500 cursor-default' : 'text-gray-700 hover:text-gray-500 focus:outline-none focus:ring ring-gray-300 active:bg-gray-100 active:text-gray-700' }}" 
                diff:loading
                @if($paginator->onFirstPage()) disabled @endif
            >
                {!! __('pagination.previous') !!}
            </button>

            <button 
                diff:click="nextPage" 
                class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium bg-white border border-gray-300 leading-5 rounded-md transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed {{ $paginator->hasMorePages() ? 'text-gray-700 hover:text-gray-500 focus:outline-none focus:ring ring-gray-300 active:bg-gray-100 active:text-gray-700' : 'text-gray-500 cursor-default' }}" 
                diff:loading
                @if(!$paginator->hasMorePages()) disabled @endif
            >
                {!! __('pagination.next') !!}
            </button>
        </div>

        <div class="hidden md:flex-1 md:flex md:items-center md:justify-between">
            <div>
                <p class="text-sm text-gray-700 leading-5">
                    {!! __('Showing') !!}
                    @if ($paginator->firstItem())
                        <span class="font-medium">{{ $paginator->firstItem() }}</span>
                        {!! __('to') !!}
                        <span class="font-medium">{{ $paginator->lastItem() }}</span>
                    @else
                        {{ $paginator->count() }}
                    @endif
                    {!! __('of') !!}
                    <span class="font-medium">{{ $paginator->total() }}</span>
                    {!! __('results') !!}
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex shadow-sm rounded-md">
                    {{-- Previous Page Link --}}
                    <button 
                        diff:click="previousPage" 
                        rel="prev"
                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium bg-white border border-gray-300 rounded-l-md leading-5 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed {{ $paginator->onFirstPage() ? 'text-gray-500 cursor-default' : 'text-gray-500 hover:text-gray-400 focus:z-10 focus:outline-none focus:ring ring-gray-300 active:bg-gray-100 active:text-gray-500' }}" 
                        aria-label="{{ __('pagination.previous') }}" 
                        diff:loading
                        @if($paginator->onFirstPage()) disabled @endif
                    >
                        &laquo;
                    </button>

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-700 bg-white border border-gray-300 cursor-default leading-5">{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-500 bg-white border border-gray-300 cursor-default leading-5">{{ $page }}</span>
                                    </span>
                                @else
                                    <button diff:click="goToPage({{ $page }})" class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-700 bg-white border border-gray-300 leading-5 hover:text-gray-500 focus:z-10 focus:outline-none focus:ring ring-gray-300 active:bg-gray-100 active:text-gray-700 transition ease-in-out duration-150" aria-label="{{ __('Go to page :page', ['page' => $page]) }}" diff:loading.attr.disabled>
                                        {{ $page }}
                                    </button>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    <button 
                        diff:click="nextPage" 
                        rel="next" 
                        class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium bg-white border border-gray-300 rounded-r-md leading-5 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed {{ $paginator->hasMorePages() ? 'text-gray-500 hover:text-gray-400 focus:z-10 focus:outline-none focus:ring ring-gray-300 active:bg-gray-100 active:text-gray-500' : 'text-gray-500 cursor-default' }}" 
                        aria-label="{{ __('pagination.next') }}" 
                        diff:loading
                        @if(!$paginator->hasMorePages()) disabled @endif
                    >
                        &raquo;
                    </button>
                </span>
            </div>
        </div>
    </nav>
@endif

