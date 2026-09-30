<div id="filter-container">
    <h4>Filter</h4>
    <div class="row">
        <div class="col-6">
            <div class="form-group">
                <label for="filter-kode">Kode</label>
                <input type="text" class="form-control" id="filter-kode" autocomplete="off">
            </div>
        </div>
        <div class="col-6">
            <div class="form-group">
                <label for="filter-nama">Nama</label>
                <input type="text" class="form-control" id="filter-nama" autocomplete="off">
            </div>
        </div>
    </div>
    {{-- Sama dengan master items: area pesan error (validasi/gagal request),
         ditampilkan lewat showFilterError() di js.blade.php. --}}
    <div class="text-danger mt-1" id="filter-error" style="display: none;" role="alert"></div>
    <button class="btn btn-primary mt-1 btn-get-data">Filter</button>
    <span id="loading-filter" style="display: none;">Loading...</span>
</div>
