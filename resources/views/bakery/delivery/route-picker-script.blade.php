<script>
const routeStores = @json($stores->map(fn ($store) => ['id' => $store->id, 'name' => $store->name, 'address' => $store->address])->values());
document.querySelectorAll('.route-form').forEach(form => {
    let ids = JSON.parse(form.dataset.selected || '[]').map(Number);
    const pool = form.querySelector('.route-pool'), selected = form.querySelector('.route-selected'), search = form.querySelector('.route-search');
    let dragged = null;
    function render() {
        pool.replaceChildren(); selected.replaceChildren();
        const query = search.value.trim().toLocaleLowerCase('ru');
        routeStores.filter(store => !ids.includes(store.id) && (store.name + ' ' + store.address).toLocaleLowerCase('ru').includes(query)).forEach(store => {
            const button = document.createElement('button'); button.type = 'button'; button.className = 'route-store';
            const name = document.createElement('strong'); name.textContent = store.name; button.append(name);
            if (store.address && store.address !== store.name) { const address = document.createElement('span'); address.textContent = store.address; button.append(address); }
            button.onclick = () => { ids.push(store.id); render(); }; button.draggable = true; button.ondragstart = () => { dragged = store.id; }; pool.append(button);
        });
        ids.forEach((id, index) => {
            const store = routeStores.find(item => item.id === id); if (!store) return;
            const item = document.createElement('li'); item.draggable = true;
            const info = document.createElement('span'); info.className = 'route-stop-info'; const name = document.createElement('strong'); name.textContent = store.name; info.append(name);
            if (store.address && store.address !== store.name) { const address = document.createElement('small'); address.textContent = store.address; info.append(address); }
            item.append(info);
            const input = document.createElement('input'); input.type = 'hidden'; input.name = 'stores[]'; input.value = id; item.append(input);
            const controls = document.createElement('span'); controls.className = 'route-stop-actions';
            for (const [label, offset] of [['↑', -1], ['↓', 1]]) { const move = document.createElement('button'); move.type = 'button'; move.textContent = label; move.setAttribute('aria-label', (offset < 0 ? 'Выше: ' : 'Ниже: ') + store.name); move.disabled = index + offset < 0 || index + offset >= ids.length; move.onclick = () => { [ids[index], ids[index + offset]] = [ids[index + offset], ids[index]]; render(); }; controls.append(move); }
            const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = '×'; remove.setAttribute('aria-label', 'Убрать: ' + store.name); remove.onclick = () => { ids.splice(index, 1); render(); }; controls.append(remove); item.append(controls);
            item.ondragstart = () => { dragged = id; }; item.ondragover = event => event.preventDefault(); item.ondrop = event => { event.preventDefault(); if (dragged === id) return; const from = ids.indexOf(dragged); if (from >= 0) ids.splice(from, 1); ids.splice(ids.indexOf(id), 0, dragged); render(); };
            selected.append(item);
        });
    }
    selected.ondragover = event => event.preventDefault(); selected.ondrop = event => { event.preventDefault(); if (dragged !== null && !ids.includes(dragged)) { ids.push(dragged); render(); } };
    search.oninput = render;
    render();
});
const routeCopySelect = document.getElementById('route-copy-select');
if (routeCopySelect) routeCopySelect.onchange = () => {
    const params = new URLSearchParams();
    if (routeCopySelect.value) params.set('copy', routeCopySelect.value);
    const date = document.querySelector('.route-form input[name="date"]')?.value;
    if (date) params.set('date', date);
    window.location.href = routeCopySelect.dataset.url + '?' + params;
};
</script>
