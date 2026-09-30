<?php

namespace Tests\Feature;

use App\Models\KategoriItem;
use App\Models\MasterItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tampilan halaman detail: master items (dengan/tanpa kategori), kategori items
 * (dengan daftar item), dan PDF kategori. Memastikan view baru mengikuti pola
 * halaman master items dan menampilkan data relasinya dengan benar.
 */
class DetailViewTest extends TestCase
{
    use DatabaseTransactions;

    private function kodeKategoriBaru(): string
    {
        $maxKode = KategoriItem::withTrashed()->max('kode');

        return ctype_digit((string) $maxKode)
            ? str_pad(((int) $maxKode) + 1, 5, '0', STR_PAD_LEFT)
            : '00001';
    }

    private function kodeItemBaru(): string
    {
        $maxKode = MasterItem::withTrashed()->max('kode');

        return ctype_digit((string) $maxKode)
            ? str_pad(((int) $maxKode) + 1, 5, '0', STR_PAD_LEFT)
            : '00001';
    }

    private function buatItem(string $nama): MasterItem
    {
        $item = new MasterItem([
            'kode' => $this->kodeItemBaru(),
            'nama' => $nama,
            'harga_beli' => 7500,
            'laba' => 10,
            'supplier' => 'Tokopaedi',
            'jenis' => 'Obat',
        ]);
        $item->save();

        return $item;
    }

    public function test_detail_master_item_menampilkan_kategorinya()
    {
        $kategori = KategoriItem::create(['kode' => $this->kodeKategoriBaru(), 'nama' => 'Obat Keras']);
        $item = $this->buatItem('Paracetamol 500mg');
        $item->kategori()->sync([$kategori->id]);

        $this->get('/master-items/view/'.$item->kode)
            ->assertStatus(200)
            ->assertSee($item->nama)
            ->assertSee($kategori->nama)
            // Tautan ke halaman view kategorinya, persis seperti tabel index.
            ->assertSee('kategori-items/view/'.$kategori->kode, false);
    }

    public function test_detail_master_item_tanpa_kategori_tetap_tampil()
    {
        $item = $this->buatItem('Item Tanpa Kategori');

        $this->get('/master-items/view/'.$item->kode)
            ->assertStatus(200)
            ->assertSee('-Tidak ada kategori-');
    }

    public function test_detail_kategori_menampilkan_daftar_itemnya()
    {
        $kategori = KategoriItem::create(['kode' => $this->kodeKategoriBaru(), 'nama' => 'Resep Dokter']);
        $itemA = $this->buatItem('Item A untuk Kategori');
        $itemB = $this->buatItem('Item B untuk Kategori');
        $itemA->kategori()->sync([$kategori->id]);
        $itemB->kategori()->sync([$kategori->id]);

        $this->get('/kategori-items/view/'.$kategori->kode)
            ->assertStatus(200)
            ->assertSee($kategori->nama)
            ->assertSee($kategori->kode)
            ->assertSee('Jumlah Item')
            ->assertSee($itemA->nama)
            ->assertSee($itemB->nama)
            // Struktur tombol mengikuti halaman detail master items
            // (Download PDF + Edit + Delete, tombol Delete tetap berbentuk form).
            ->assertSee('kategori-items/pdf/'.$kategori->kode, false)
            ->assertSee('kategori-items/form/edit/'.$kategori->id, false)
            ->assertSee('kategori-items/delete/'.$kategori->id, false);
    }
}
