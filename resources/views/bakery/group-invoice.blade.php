<section class="invoice group-invoice">
    <h2>Сводная накладная группы «{{ $group['name'] }}» на {{ \Illuminate\Support\Carbon::parse($date)->format('d.m.Y') }}</h2>
    <p class="muted small">Сформировано {{ $generatedAt->timezone('Europe/Moscow')->format('d.m.Y H:i') }} МСК. Состав группы на момент формирования приведён ниже.</p>
    <p><strong>Не прибавлять к общему итогу</strong> — заказы участников уже включены в обычные накладные.</p>
    @php($hasReturns = $group['returned_quantity'] > 0)
    <table><thead><tr><th>Товар</th><th>Заказано, шт.</th>@if($hasReturns)<th>Возврат, шт.</th>@endif<th>Цена, ₽</th><th>Сумма, ₽</th>@if($hasReturns)<th>После возврата, ₽</th>@endif</tr></thead><tbody>
        @foreach($group['products'] as $product)
            <tr><td>{{ $product['name'] }}</td><td>{{ $product['quantity'] }}</td>@if($hasReturns)<td>{{ $product['returned'] }}</td>@endif<td>{{ number_format($product['unit_kopecks'] / 100, 2, ',', ' ') }}</td><td>{{ number_format($product['gross'] / 100, 2, ',', ' ') }}</td>@if($hasReturns)<td>{{ number_format($product['net'] / 100, 2, ',', ' ') }}</td>@endif</tr>
        @endforeach
    </tbody></table>
    <div class="invoice-footer">Итого: {{ number_format($group['gross'] / 100, 2, ',', ' ') }} ₽@if($hasReturns) · Возврат: {{ number_format($group['returns'] / 100, 2, ',', ' ') }} ₽ · После возврата: {{ number_format($group['net'] / 100, 2, ',', ' ') }} ₽@endif</div>
    <h3>Точки группы</h3>
    <table><thead><tr><th>Точка</th><th>Изделий, шт.</th><th>Заказ, ₽</th><th>Возврат, ₽</th><th>После возврата, ₽</th></tr></thead><tbody>
        @foreach($group['members'] as $member)
            <tr><td>{{ $member['name'] }} · {{ $member['address'] }}@if(!$member['ordered']) · нет заказа@endif</td>
                <td>{{ $member['ordered'] ? $member['quantity'] : '—' }}</td>
                <td>{{ $member['ordered'] ? number_format($member['gross'] / 100, 2, ',', ' ') : '—' }}</td>
                <td>{{ $member['ordered'] ? number_format($member['returns'] / 100, 2, ',', ' ') : '—' }}</td>
                <td>{{ $member['ordered'] ? number_format($member['net'] / 100, 2, ',', ' ') : '—' }}</td></tr>
        @endforeach
        <tr><th>Итого</th><th>{{ $group['quantity'] }}</th><th>{{ number_format($group['gross'] / 100, 2, ',', ' ') }}</th><th>{{ number_format($group['returns'] / 100, 2, ',', ' ') }}</th><th>{{ number_format($group['net'] / 100, 2, ',', ' ') }}</th></tr>
    </tbody></table>
    @foreach($group['members'] as $member)
        @if($member['comment'])<p class="invoice-comment"><strong>Комментарий · {{ $member['name'] }}:</strong> {{ $member['comment'] }}</p>@endif
    @endforeach
</section>
