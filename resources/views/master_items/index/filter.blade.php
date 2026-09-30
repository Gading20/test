<div id="filter-container">
    <h4>Filter</h4>
    <div class="row">
        <div class="col-4">
            <div class="form-group">
                <label for="filter-kode">Kode</label>
                <input type="text" class="form-control" id="filter-kode" autocomplete="off">
            </div>
        </div>
        <div class="col-4">
            <div class="form-group">
                <label for="filter-nama">Nama</label>
                <input type="text" class="form-control" id="filter-nama" autocomplete="off">
            </div>
        </div>
        {{-- type="text" (bukan number) supaya angka berpemisah ribuan seperti
             "1.500.000" / "1,500" bisa diketik; nilainya dinormalkan di js.blade.php. --}}
        <div class="col-2">
            <div class="form-group">
                <label for="filter-harga-min">Harga Min</label>
                <input type="text" class="form-control" id="filter-harga-min" inputmode="numeric" autocomplete="off" placeholder="0">
            </div>
        </div>
        <div class="col-2">
            <div class="form-group">
                <label for="filter-harga-max">Harga Max</label>
                <input type="text" class="form-control" id="filter-harga-max" inputmode="numeric" autocomplete="off" placeholder="0">
            </div>
        </div>
    </div>
    <div class="text-danger mt-1" id="filter-error" style="display: none;" role="alert"></div>
    <button class="btn btn-primary mt-1 btn-get-data">Filter</button>
    <span id="loading-filter" style="display: none;">Loading...</span>
</div>