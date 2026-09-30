<form method="POST">
    @csrf

    @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if($method == 'edit')
    <div class="form-group">
        <label for="kode">Kode Kategori</label>
        <input type="text" class="form-control" id="kode" name="kode" readonly value="{{ $item->kode ?? '' }}">
    </div>
    @endif

    {{-- old() dipakai supaya input tidak hilang kalau validasi memantul balik. --}}
    <div class="form-group">
        <label for="nama">Nama</label>
        <input type="text" class="form-control" id="nama" name="nama" required value="{{ old('nama', $item->nama ?? '') }}">
        <small class="form-text text-muted">Kode kategori dibuat otomatis (00001, 00002, ...).</small>
    </div>

    <button class="btn btn-primary mt-3">Submit</button>

</form>
