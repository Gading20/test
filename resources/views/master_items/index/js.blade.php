<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>

<script>
    var start_date = '';
    var end_date = '';
    var data_per_fetch = 500;
    var data_fetched = 0;

    $(document).ready(function() {
        $('#table').DataTable({
            searching: false,
            order: [[0, 'desc']],
        });
        getData()
    });

    $('.btn-get-data').click(function() {
        getData()
    })

    function showFilterError(message) {
        $('#filter-error').text(message).show();
    }

    function hideFilterError() {
        $('#filter-error').hide().text('');
    }

    // Baca input harga. Mendukung pemisah ribuan (1.500.000 / 1,500 / Rp 1.500.000).
    // Return {value: <int>} | null (kosong, artinya filter tidak dipasang) | {error: <pesan>}.
    function readHarga(selector, label) {
        var raw = $(selector).val();
        raw = (raw === undefined || raw === null) ? '' : String(raw).trim();
        if (raw === '') return null;

        // "Rp1.500.000" -> "1.500.000"
        raw = raw.replace(/^rp\.?\s*/i, '');

        // Selain angka dan pemisah ribuan (titik/koma/spasi) dianggap salah ketik.
        if (!/^[0-9.,\s]+$/.test(raw)) {
            return {error: label + ' hanya boleh berisi angka (boleh memakai pemisah ribuan).'};
        }

        // Harga di database berupa integer, jadi sisanya tinggal digit:
        // "1.500.000" -> 1500000, "1,500" -> 1500.
        var digits = raw.replace(/\D/g, '');
        var value = parseInt(digits, 10);
        if (digits === '' || isNaN(value)) {
            return {error: label + ' tidak valid.'};
        }

        return {value: value};
    }

    function getData(){
        var filter_kode = $('#filter-kode').val()
        var filter_nama = $('#filter-nama').val()
        var filter_harga_min = readHarga('#filter-harga-min', 'Harga Min')
        var filter_harga_max = readHarga('#filter-harga-max', 'Harga Max')

        // Validasi dulu sebelum request: nilai tidak valid / min > max / negatif
        // tidak usah dikirim ke server, tabel lama biarkan tetap tampil.
        var errors = []
        if (filter_harga_min && filter_harga_min.error) errors.push(filter_harga_min.error)
        if (filter_harga_max && filter_harga_max.error) errors.push(filter_harga_max.error)
        if (errors.length === 0 && filter_harga_min && filter_harga_max && filter_harga_min.value > filter_harga_max.value) {
            errors.push('Harga Min tidak boleh lebih besar dari Harga Max.')
        }
        if (errors.length > 0) {
            showFilterError(errors.join(' '))
            return
        }
        hideFilterError()

        $('#loading-filter').show();
        var dataTableObj = $('#table').DataTable();

        $.ajax({
            url: '{{url("master-items/search")}}',
            dataType: 'json',
            tryCount: 0,
            retryLimit: 3,
            data: {
                kode: filter_kode,
                nama: filter_nama,
                // value sudah dinormalkan jadi integer (0 tetap terkirim, jadi filter
                // "maks 0" tidak lagi dianggap kosong oleh server).
                hargamin: filter_harga_min ? filter_harga_min.value : '',
                hargamax: filter_harga_max ? filter_harga_max.value : '',
            },
            success: function(results) {
                var data = results.data

                dataTableObj.clear()
                $.each(data, function(index, item) {
                    var harga_jual = item.harga_beli + item.harga_beli * item.laba / 100;
                    harga_jual = Math.round(harga_jual);

                    var html = `<a href="{{url('master-items/view/')}}/` + item.kode + `" class="btn btn-primary">View</a>`

                    var foto_html = item.foto
                        ? `<img src="{{ asset('storage') }}/` + item.foto + `" style="max-height:40px;" alt="">`
                        : '-';

                    // Kategori (bisa lebih dari satu) -> tautan ke halaman view kategorinya.
                    var kategori_html = (item.kategori && item.kategori.length)
                        ? item.kategori.map(function(kat) {
                            return `<a href="{{url('kategori-items/view/')}}/` + kat.kode + `">` + kat.nama + `</a>`;
                        }).join(', ')
                        : '-';

                    // Urutan harus sama dengan <thead> di table.blade.php:
                    // Kode, Nama, Jenis, Kategori, Harga Beli, Harga Jual, Supplier, Foto, View
                    var array_temp = [
                        item.kode,
                        item.nama,
                        item.jenis,
                        kategori_html,
                        item.harga_beli,
                        harga_jual,
                        item.supplier,
                        foto_html,
                        html
                    ];

                    dataTableObj.row.add(array_temp);
                });
                dataTableObj.draw();
                $('#loading-filter').hide();
            },
            error: function(xhr, textStatus, errorThrown) {
                // Validasi dari server (422): jangan di-retry, tampilkan pesannya.
                if (xhr.status >= 400 && xhr.status < 500) {
                    var pesan = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : 'Filter harga tidak valid.';
                    showFilterError(pesan);
                    $('#loading-filter').hide();
                    return;
                }

                this.tryCount++;
                if (this.tryCount <= this.retryLimit) {
                    $.ajax(this);
                    return;
                }
                alert('Terjadi kesalahan server, tidak dapat mengambil data')
                $('#loading-filter').hide();

                return;
            }
        })
    }
</script>