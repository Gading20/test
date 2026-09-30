<?php

namespace App\Http\Controllers;

use App\Exports\MasterItemsExport;
use App\Models\KategoriItem;
use App\Models\MasterItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class MasterItemsController extends Controller
{
    public function index()
    {
        return view('master_items.index.index');
    }

    public function search(Request $request)
    {
        $kode = $request->kode;
        $nama = $request->nama;

        // Harga boleh ditulis dengan pemisah ribuan (1.500.000 / 1,500) dan wajib
        // non-negatif. Nilai kosong -> null (filter tidak dipasang), nilai tidak
        // valid -> 422 lewat ValidationException.
        $hargamin = $this->parseHarga($request->input('hargamin'), 'hargamin', 'Harga Min');
        $hargamax = $this->parseHarga($request->input('hargamax'), 'hargamax', 'Harga Max');

        if ($hargamin !== null && $hargamax !== null && $hargamin > $hargamax) {
            throw ValidationException::withMessages([
                'hargamin' => 'Harga Min tidak boleh lebih besar dari Harga Max.',
            ]);
        }

        $data_search = MasterItem::query();

        if (! empty($kode)) {
            $data_search = $data_search->where('kode', $kode);
        }
        if (! empty($nama)) {
            $data_search = $data_search->where('nama', 'LIKE', '%'.$nama.'%');
        }
        // Pakai !== null, bukan empty(): empty('0') bernilai true sehingga filter
        // "harga maks 0" sebelumnya tidak pernah dipasang.
        if ($hargamin !== null) {
            $data_search = $data_search->where('harga_beli', '>=', $hargamin);
        }
        if ($hargamax !== null) {
            $data_search = $data_search->where('harga_beli', '<=', $hargamax);
        }

        // with('kategori') butuh id di select, karena id itu kunci join ke pivot.
        // Satu query untuk semua kategori (eager, bukan N+1) dan diurutkan nama
        // supaya urutannya sama dengan halaman view & form.
        $data_search = $data_search->with(['kategori' => fn ($q) => $q->orderBy('nama')])
            ->select('id', 'kode', 'nama', 'jenis', 'harga_beli', 'laba', 'supplier', 'foto')
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => 200,
            'data' => $data_search,
        ]);
    }

    /**
     * Normalisasi filter harga dari request.
     * "Rp1.500.000" / "1,500" -> 1500000 / 1500, string kosong -> null,
     * selain itu (huruf, negatif, pecahan berlebihan, array) -> 422.
     */
    private function parseHarga($value, string $key, string $label): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            throw ValidationException::withMessages([$key => $label.' tidak valid.']);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        // "Rp1.500.000" -> "1.500.000"
        $raw = preg_replace('/^rp\.?\s*/i', '', $raw) ?? '';

        // Selain angka dan pemisah ribuan (titik/koma/spasi) berarti bukan angka.
        if ($raw === '' || preg_match('/^[0-9.,\s]+$/', $raw) !== 1) {
            throw ValidationException::withMessages([
                $key => $label.' hanya boleh berisi angka (boleh memakai pemisah ribuan).',
            ]);
        }

        $angka = preg_replace('/\D+/', '', $raw) ?? '';
        if ($angka === '') {
            throw ValidationException::withMessages([
                $key => $label.' tidak valid.',
            ]);
        }

        // "000500" -> "500", karena filter_var menolak angka ber-nol di depan.
        $angka = ltrim($angka, '0');

        // Sekaligus menolak angka di luar rentang integer (negatif sudah hilang
        // karena hanya digit yang dipakai).
        $hasil = filter_var($angka === '' ? '0' : $angka, FILTER_VALIDATE_INT);
        if ($hasil === false) {
            throw ValidationException::withMessages([
                $key => $label.' tidak valid.',
            ]);
        }

        return $hasil;
    }

    /**
     * Download seluruh data master items ke Excel (.xlsx).
     * Sengaja memakai seluruh data (bukan hasil filter) supaya tombol ini
     * tidak bergantung pada nilai filter yang mungkin sedang tidak valid.
     */
    public function downloadExcel()
    {
        $namaFile = 'master-items-'.now()->setTimezone('Asia/Jakarta')->format('Y-m-d').'.xlsx';

        return Excel::download(new MasterItemsExport, $namaFile);
    }

    public function formView($method, $id = 0)
    {
        if ($method == 'new') {
            $item = [];
            $selected_kategori_ids = [];
        } else {
            $item = MasterItem::find($id);
            abort_if(! $item, 404);
            $selected_kategori_ids = $item->kategori()->orderBy('kategori_items.nama')->pluck('kategori_items.id')->all();
        }

        $data['item'] = $item;
        $data['method'] = $method;
        $data['kategori_list'] = KategoriItem::orderBy('nama')->get();
        $data['selected_kategori_ids'] = $selected_kategori_ids;

        return view('master_items.form.index', $data);
    }

    public function singleView($kode)
    {
        // Eager loading relasi kategori (diurutkan nama, sama seperti pilihan
        // di form) supaya view tidak memicu lazy loading.
        $data['data'] = MasterItem::where('kode', $kode)->first();
        abort_if(! $data['data'], 404);

        return view('master_items.single.index', $data);
    }

    public function formSubmit(Request $request, $method, $id = 0)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'harga_beli' => 'required|integer|min:0',
            'laba' => 'required|integer|min:0|max:100',
            'supplier' => 'required|string|max:255',
            'jenis' => 'required|string|max:255',
            'kategori_ids' => 'nullable|array',
            'kategori_ids.*' => ['integer', Rule::exists('kategori_items', 'id')->whereNull('deleted_at')],
            // max:2048 (KB) sengaja disamakan dengan upload_max_filesize (2M) di php.ini,
            // karena file yang lebih besar ditolak PHP sebelum sampai ke Laravel.
            'foto' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        if ($method == 'new') {
            $data_item = new MasterItem;
            $kode = $this->generateKode();
        } else {
            $data_item = MasterItem::find($id);
            abort_if(! $data_item, 404);
            $kode = $data_item->kode;
        }

        $data_item->nama = $request->nama;
        $data_item->harga_beli = $request->harga_beli;
        $data_item->laba = $request->laba;
        $data_item->kode = $kode;
        $data_item->supplier = $request->supplier;
        $data_item->jenis = $request->jenis;

        // Simpan foto ke disk 'public' (storage/app/public/master-items/), path relatif disimpan di DB.
        // File lama dihapus belakangan (lihat bawah), jadi kalau save gagal foto lama masih utuh.
        $foto_lama = $data_item->foto;

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('master-items', 'public');
            if ($path) {
                $data_item->foto = $path;
            }
        } elseif ($request->boolean('hapus_foto')) {
            $data_item->foto = null;
        }

        $data_item->save();

        // Relasi many-to-many: sync() menambah/menghapus baris pivot sesuai pilihan
        // di form. Kolom lain (foto) sudah tersimpan di atas, jadi aman dipanggil setelah save.
        $data_item->kategori()->sync($request->input('kategori_ids', []));

        // Baru hapus file lama setelah perubahan tersimpan dan file-nya memang berganti.
        if (! empty($foto_lama) && $foto_lama !== $data_item->foto) {
            Storage::disk('public')->delete($foto_lama);
        }

        return redirect('master-items');
    }

    /**
     * Bikin kode barang baru (00001, 00002, ...).
     * Ambil kode terbesar (termasuk yang soft delete) supaya tidak duplikat.
     */
    private function generateKode()
    {
        $maxKode = MasterItem::withTrashed()->max('kode');

        $next = 1;
        if (! empty($maxKode) && ctype_digit((string) $maxKode)) {
            $next = (int) $maxKode + 1;
        }

        return str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    public function delete($id)
    {
        // findOrFail: id tidak dikenal -> 404, bukan error "delete() on null" (500).
        MasterItem::findOrFail($id)->delete();

        return redirect('master-items');
    }

    public function updateRandomData()
    {
        $data = MasterItem::get();
        foreach ($data as $item) {
            $kode = $item->id;
            $kode = str_pad($kode, 5, '0', STR_PAD_LEFT);

            $item->harga_beli = rand(100, 1000000);
            $item->laba = rand(10, 99);
            $item->kode = $kode;
            $item->supplier = $this->getRandomSupplier();
            $item->jenis = $this->getRandomJenis();
            $item->save();
        }

        return redirect('master-items');
    }

    private function getRandomSupplier()
    {
        $array = ['Tokopaedi', 'Bukulapuk', 'TokoBagas', 'E Commurz', 'Blublu'];
        $random = rand(0, 4);

        return $array[$random];
    }

    private function getRandomJenis()
    {
        $array = ['Obat', 'Alkes', 'Matkes', 'Umum', 'ATK'];
        $random = rand(0, 4);

        return $array[$random];
    }
}
