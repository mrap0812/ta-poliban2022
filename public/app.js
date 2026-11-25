// Konfirmasi sebelum aksi berbahaya (hapus, dsb.) pada form ber-atribut data-confirm.
document.addEventListener('submit', (ev) => {
    const msg = ev.target.dataset.confirm;
    if (msg && !window.confirm(msg)) ev.preventDefault();
});

// Pencarian cepat sisi klien untuk tabel ber-atribut data-filter="#idTabel".
document.querySelectorAll('input[data-filter]').forEach((input) => {
    const rows = document.querySelectorAll(input.dataset.filter + ' tbody tr');
    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        rows.forEach((r) => { r.hidden = q !== '' && !r.textContent.toLowerCase().includes(q); });
    });
});
