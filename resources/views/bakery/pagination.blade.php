@if($pageCount > 1)
    <nav class="pager" aria-label="Страницы списка">
        @for($number = 1; $number <= $pageCount; $number++)
            <a href="{{ $url.'?'.http_build_query($query + ['page' => $number]) }}" @if($page === $number) aria-current="page" @endif>{{ $number }}</a>
        @endfor
    </nav>
@endif
