<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>

<script>
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

    // Pesan error ditampilkan inline di bawah filter, sama seperti master items.
    function showFilterError(message) {
        $('#filter-error').text(message).show();
    }

    function hideFilterError() {
        $('#filter-error').hide().text('');
    }

    function getData() {
        hideFilterError();
        $('#loading-filter').show();
        var dataTableObj = $('#table').DataTable();
        var filter_kode = $('#filter-kode').val()
        var filter_nama = $('#filter-nama').val()

        $.ajax({
            url: '{{url("kategori-items/search")}}',
            dataType: 'json',
            tryCount: 0,
            retryLimit: 3,
            // Dikirim sebagai objek supaya nilainya di-encode dengan benar oleh jQuery.
            data: {
                kode: filter_kode,
                nama: filter_nama,
            },
            success: function(results) {
                var data = results.data

                dataTableObj.clear()
                $.each(data, function(index, item) {
                    var html = `<a href="{{url('kategori-items/view/')}}/` + item.kode + `" class="btn btn-primary">View</a>`

                    // Urutan harus sama dengan <thead> di table.blade.php:
                    // Kode, Nama, Jumlah Item, View
                    var array_temp = [
                        item.kode,
                        item.nama,
                        item.master_items_count,
                        html
                    ];

                    dataTableObj.row.add(array_temp);
                });
                dataTableObj.draw();
                $('#loading-filter').hide();
            },
            error: function(xhr, textStatus, errorThrown) {
                // Error dari server (4xx): jangan di-retry, tampilkan inline.
                if (xhr.status >= 400 && xhr.status < 500) {
                    showFilterError((xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : 'Filter tidak dapat diproses.');
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
            }
        })
    }
</script>
