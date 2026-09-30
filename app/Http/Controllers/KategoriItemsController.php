<?php

namespace App\Http\Controllers;

use App\Models\KategoriItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class KategoriItemsController extends Controller
{
    public function index()
    {
        return view('kategori_items.index.index');
    }

    public function search(Request $request)
    {
        $kode = $request->kode;
        $nama = $request->nama;

        $data_search = KategoriItem::query();

        if (! empty($kode)) {
            $data_search = $data_search->where('kode', $kode);
        }
        if (! empty($nama)) {
            $data_search = $data_search->where('nama', 'LIKE', '%'.$nama.'%');
        }

        $data_search = $data_search->withCount('masterItems')->orderBy('id')->get(['id', 'kode', 'nama']);

        return response()->json([
            'status' => 200,
            'data' => $data_search,
        ]);
    }

    public function formView($method, $id = 0)
    {
        if ($method == 'new') {
            $item = [];
        } else {
            $item = KategoriItem::find($id);
            abort_if(! $item, 404);
        }

        $data['item'] = $item;
        $data['method'] = $method;

        return view('kategori_items.form.index', $data);
    }

    /**
     * Halaman detail kategori: identitas kategori + daftar item yang memakainya.
     */
    public function singleView($kode)
    {
        $kategori = $this->kategoriDenganItem($kode);

        $data['data'] = $kategori;
        $data['items'] = $kategori->masterItems;

        return view('kategori_items.single.index', $data);
    }

    /**
     * Download PDF data kategori (identitas kategori + tabel daftar itemnya).
     * Memakai barryvdh/laravel-dompdf yang memang sudah ada di project ini.
     */
    public function cetakPdf($kode)
    {
        $kategori = $this->kategoriDenganItem($kode);

        $data['data'] = $kategori;
        $data['items'] = $kategori->masterItems;
        $data['waktu_cetak'] = $this->waktuCetak();

        return Pdf::loadView('kategori_items.single.pdf', $data)
            ->setPaper('A4', 'portrait')
            ->download('kategori-'.$kode.'.pdf');
    }

    /**
     * Ambil kategori beserta itemnya lewat eager loading (satu query untuk
     * relasi), supaya halaman detail dan PDF tidak melakukan lazy loading.
     * Kode tidak ada -> 404.
     */
    private function kategoriDenganItem(string $kode): KategoriItem
    {
        $kategori = KategoriItem::with(['masterItems' => fn ($q) => $q->orderBy('id')])
            ->where('kode', $kode)
            ->first();
        abort_if(! $kategori, 404);

        return $kategori;
    }

    /**
     * Stempel waktu untuk footer PDF: hari/tanggal Indonesia, zona WIB.
     * Timezone aplikasi (UTC) tidak diubah supaya timestamp di database tetap konsisten.
     */
    private function waktuCetak(): string
    {
        return now()
            ->setTimezone('Asia/Jakarta')
            ->locale('id')
            ->isoFormat('dddd, D MMMM YYYY HH:mm:ss').' WIB';
    }

    public function formSubmit(Request $request, $method, $id = 0)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        if ($method == 'new') {
            $data_item = new KategoriItem;
            $kode = $this->generateKode();
        } else {
            $data_item = KategoriItem::find($id);
            abort_if(! $data_item, 404);
            $kode = $data_item->kode;
        }

        $data_item->nama = $request->nama;
        $data_item->kode = $kode;
        $data_item->save();

        return redirect('kategori-items');
    }

    public function delete($id)
    {
        // findOrFail: id tidak dikenal -> 404, bukan error "delete() on null" (500).
        KategoriItem::findOrFail($id)->delete();

        return redirect('kategori-items');
    }

    /**
     * Bikin kode kategori baru (00001, 00002, ...).
     * Ambil kode terbesar (termasuk yang soft delete) supaya tidak duplikat.
     */
    private function generateKode()
    {
        $maxKode = KategoriItem::withTrashed()->max('kode');

        $next = 1;
        if (! empty($maxKode) && ctype_digit((string) $maxKode)) {
            $next = (int) $maxKode + 1;
        }

        return str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
