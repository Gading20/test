<form method="POST" enctype="multipart/form-data">
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
        <label>Kode Barang</label>
        <input type="text" class="form-control" name="kode_barang" required readonly value="{{$item->kode ?? ''}}">
    </div>
    @endif

    {{-- old() dipakai supaya input tidak hilang kalau validasi (misal foto gagal) memantul balik. --}}
    <div class="form-group">
        <label>Nama</label>
        <input type="text" class="form-control" name="nama" required value="{{ old('nama', $item->nama ?? '') }}">
    </div>

    <div class="form-group">
        <label>Harga Beli</label>
        <input type="number" class="form-control" name="harga_beli" required value="{{ old('harga_beli', $item->harga_beli ?? '') }}">
    </div>

    <div class="form-group">
        <label>Laba (dalam persen)</label>
        <input type="number" class="form-control" name="laba" required value="{{ old('laba', $item->laba ?? '') }}">
    </div>

    @php $selected = old('supplier', $item->supplier ?? ''); @endphp
    <div class="form-group">
        <label>Supplier</label>
        <select class="form-control" required name="supplier">
            <option @if($selected == '') selected @endif value="">--Pilih--</option>
            <option @if($selected == 'Tokopaedi') selected @endif>Tokopaedi</option>
            <option @if($selected == 'Bukulapuk') selected @endif>Bukulapuk</option>
            <option @if($selected == 'TokoBagas') selected @endif>TokoBagas</option>
            <option @if($selected == 'E Commurz') selected @endif>E Commurz</option>
            <option @if($selected == 'Blublu') selected @endif>Blublu</option>
        </select>
    </div>

    @php $selected = old('jenis', $item->jenis ?? ''); @endphp
    <div class="form-group">
        <label>Jenis</label>
        <select class="form-control" required name="jenis">
            <option @if($selected == '') selected @endif value="">--Pilih--</option>
            <option @if($selected == 'Obat') selected @endif>Obat</option>
            <option @if($selected == 'Alkes') selected @endif>Alkes</option>
            <option @if($selected == 'Matkes') selected @endif>Matkes</option>
            <option @if($selected == 'Umum') selected @endif>Umum</option>
            <option @if($selected == 'ATK') selected @endif>ATK</option>
        </select>
    </div>

    @php
        // old() supaya pilihan kategori tidak hilang kalau validasi memantul balik.
        $selected_kategori = old('kategori_ids', $selected_kategori_ids ?? []);
    @endphp
    <div class="form-group">
        <label for="kategori-ids">Kategori</label>
        <select class="form-control" id="kategori-ids" name="kategori_ids[]" multiple size="5">
            @forelse($kategori_list ?? [] as $kat)
            <option value="{{ $kat->id }}" @if(in_array($kat->id, $selected_kategori)) selected @endif>{{ $kat->kode }} - {{ $kat->nama }}</option>
            @empty
            <option value="" disabled>Belum ada kategori. Buat dulu lewat menu Kategori Items.</option>
            @endforelse
        </select>
        <small class="form-text text-muted">Boleh pilih lebih dari satu (tahan Ctrl / ⌘). Kosongkan jika item tanpa kategori.</small>
    </div>

    <div class="form-group">
        <label for="foto">Foto</label>
        @if(!empty($item->foto ?? ''))
        <div class="mb-2">
            <img src="{{ asset('storage/'.$item->foto) }}" alt="{{ $item->nama }}" class="img-thumbnail" style="max-height:150px;">
        </div>
        <div class="form-check mb-2">
            <input type="checkbox" name="hapus_foto" value="1" class="form-check-input" id="hapus-foto" @if(old('hapus_foto')) checked @endif>
            <label class="form-check-label" for="hapus-foto">Hapus foto saat ini</label>
        </div>
        @endif
        {{-- Pratinjau foto yang baru dipilih, tanpa menunggu upload. --}}
        <div id="foto-preview" class="mb-2"></div>
        <input type="file" name="foto" id="foto" class="form-control" accept="image/jpeg,image/png,image/webp">
        <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah foto. Format: JPG/PNG/WEBP, maks 2 MB.</small>
    </div>

    <button class="btn btn-primary mt-3">Submit</button>

</form>

<script>
    (function() {
        var input = document.getElementById('foto');
        var preview = document.getElementById('foto-preview');
        if (!input || !preview) return;

        input.addEventListener('change', function() {
            var file = this.files && this.files[0];
            if (!file) {
                preview.innerHTML = '';
                return;
            }
            preview.innerHTML = '<img src="' + URL.createObjectURL(file) + '" class="img-thumbnail" style="max-height:150px;" alt="Pratinjau foto">';
        });
    })();
</script>