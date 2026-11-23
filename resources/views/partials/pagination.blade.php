@if ($paginator->hasPages())
    <nav class="bps-pagination" role="navigation" aria-label="Navigasi Halaman Data">
        <ul class="bps-pagination-list">
            {{-- Tombol Previous --}}
            @if ($paginator->onFirstPage())
                <li class="bps-page-item disabled" aria-disabled="true">
                    <span class="bps-page-link" aria-label="Halaman Sebelumnya">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: -1px; margin-right: 3px;"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        Sebelumnya
                    </span>
                </li>
            @else
                <li class="bps-page-item">
                    <a class="bps-page-link" href="{{ $paginator->appends(request()->query())->previousPageUrl() }}" rel="prev" aria-label="Halaman Sebelumnya">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: -1px; margin-right: 3px;"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        Sebelumnya
                    </a>
                </li>
            @endif

            {{-- Elemen Nomor Halaman --}}
            @foreach ($elements as $element)
                {{-- Pemisah Tiga Titik "..." --}}
                @if (is_string($element))
                    <li class="bps-page-item disabled" aria-disabled="true"><span class="bps-page-link ellipsis">{{ $element }}</span></li>
                @endif

                {{-- Array Link Halaman --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="bps-page-item active" aria-current="page"><span class="bps-page-link">{{ $page }}</span></li>
                        @else
                            <li class="bps-page-item"><a class="bps-page-link" href="{{ $paginator->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Tombol Next --}}
            @if ($paginator->hasMorePages())
                <li class="bps-page-item">
                    <a class="bps-page-link" href="{{ $paginator->appends(request()->query())->nextPageUrl() }}" rel="next" aria-label="Halaman Selanjutnya">
                        Selanjutnya
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: -1px; margin-left: 3px;"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                </li>
            @else
                <li class="bps-page-item disabled" aria-disabled="true">
                    <span class="bps-page-link" aria-label="Halaman Selanjutnya">
                        Selanjutnya
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: -1px; margin-left: 3px;"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </span>
                </li>
            @endif
        </ul>
    </nav>
@endif
