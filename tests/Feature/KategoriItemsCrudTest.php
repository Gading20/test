<?php

namespace Tests\Feature;

use App\Models\KategoriItem;
use App\Models\MasterItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class KategoriItemsCrudTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKategori(string $nama): KategoriItem
    {
        $this->post('/kategori-items/form/new', ['nama' => $nama])
            ->assertSessionHasNoErrors();

        return KategoriItem::where('nama', $nama)->firstOrFail();
    }

    private function buatMasterItem(string $nama, array $extra = []): MasterItem
    {
        $this->post('/master-items/form/new', array_merge([
            'nama' => $nama,
            'harga_beli' => 1000,
            'laba' => 10,
            'supplier' => 'Tokopaedi',
            'jenis' => 'Obat',
        ], $extra))->assertSessionHasNoErrors();

        return MasterItem::where('nama', $nama)->firstOrFail();
    }

    public function test_halaman_index_kategori_memuat_filter_dan_navigasi()
    {
        $response = $this->get('/kategori-items');

        $response->assertStatus(200);
        $response->assertSee('id="filter-kode"', false);
        $response->assertSee('id="filter-nama"', false);
        $response->assertSee('Daftar Kategori Items');
        // Link navbar untuk kedua menu ikut tampil.
        $response->assertSee('href="'.url('master-items').'"', false);
        $response->assertSee('href="'.url('kategori-items').'"', false);
    }

    public function test_kategori_baru_dibuat_dengan_kode_otomatis()
    {
        $kategori = $this->buatKategori('Obat Keras');

        $this->assertMatchesRegularExpression('/^\d{5}$/', $kategori->kode, 'Kode kategori harus 5 digit');
        $this->assertSame('Obat Keras', $kategori->nama);
        $this->assertNull($kategori->deleted_at);
    }

    public function test_validasi_nama_wajib_diisi()
    {
        $sebelum = KategoriItem::count();

        $this->post('/kategori-items/form/new', ['nama' => ''])
            ->assertSessionHasErrors('nama');

        $this->assertSame($sebelum, KategoriItem::count(), 'Kategori tidak valid tidak boleh tersimpan');
    }

    public function test_search_kategori_memfilter_kode_dan_nama()
    {
        $obat = $this->buatKategori('Obat Keras');
        $alkes = $this->buatKategori('Alat Kesehatan');

        $byNama = $this->getJson('/kategori-items/search?nama=Keras')->assertOk()->json('data');
        $this->assertSame([$obat->kode], array_column($byNama, 'kode'));

        $byKode = $this->getJson('/kategori-items/search?kode='.$alkes->kode)->assertOk()->json('data');
        $this->assertSame(['Alat Kesehatan'], array_column($byKode, 'nama'));

        // Jumlah item dikirim untuk kolom "Jumlah Item".
        $this->assertArrayHasKey('master_items_count', $byNama[0]);
    }

    public function test_halaman_single_menampilkan_identitas_dan_daftar_item()
    {
        $kategori = $this->buatKategori('Obat Keras');
        $item = $this->buatMasterItem('Paracetamol 500mg', ['kategori_ids' => [$kategori->id]]);

        $response = $this->get('/kategori-items/view/'.$kategori->kode);

        $response->assertStatus(200);
        $response->assertSee($kategori->kode);
        $response->assertSee('Obat Keras');
        $response->assertSee('Paracetamol 500mg');
        $response->assertSee('Daftar Item');
    }

    public function test_halaman_single_kosong_bila_belum_ada_item()
    {
        $kategori = $this->buatKategori('Tanpa Item');

        $this->get('/kategori-items/view/'.$kategori->kode)
            ->assertStatus(200)
            ->assertSee('Belum ada item pada kategori ini.');
    }

    public function test_edit_kategori_mengubah_nama_dan_mempertahankan_kode()
    {
        $kategori = $this->buatKategori('Nama Lama');
        $kodeLama = $kategori->kode;

        $this->post('/kategori-items/form/edit/'.$kategori->id, ['nama' => 'Nama Baru'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('kategori-items');

        $kategori->refresh();
        $this->assertSame('Nama Baru', $kategori->nama);
        $this->assertSame($kodeLama, $kategori->kode, 'Kode tidak boleh berubah saat edit');
    }

    public function test_delete_kategori_menghapus_dari_daftar_dan_dari_item()
    {
        $kategori = $this->buatKategori('Akan Dihapus');
        $item = $this->buatMasterItem('Item Terkait', ['kategori_ids' => [$kategori->id]]);

        $this->assertCount(1, $item->kategori);

        $this->post('/kategori-items/delete/'.$kategori->id)
            ->assertRedirect('kategori-items');

        $this->assertNull(KategoriItem::find($kategori->id), 'Kategori harus soft-delete');
        $this->assertNotNull(KategoriItem::withTrashed()->find($kategori->id));

        // Hilang dari hasil pencarian...
        $this->assertSame([], $this->getJson('/kategori-items/search?nama=Akan Dihapus')->json('data'));

        // ...dan tidak lagi nempel di itemnya (global scope SoftDeletes di query relasi).
        $this->assertCount(0, $item->fresh()->kategori);
    }

    public function test_master_item_dapat_mempunyai_lebih_dari_satu_kategori()
    {
        $obat = $this->buatKategori('Obat Keras');
        $resmi = $this->buatKategori('Resep Dokter');

        $item = $this->buatMasterItem('Amoxicillin', ['kategori_ids' => [$obat->id, $resmi->id]]);

        $this->assertEqualsCanonicalizing(
            [$obat->id, $resmi->id],
            $item->kategori()->pluck('kategori_items.id')->all()
        );

        // Dari sisi kategori juga kelihatan itemnya.
        $this->assertTrue($obat->masterItems->contains('id', $item->id));
    }

    public function test_edit_master_item_menyinkronkan_kategori()
    {
        $obat = $this->buatKategori('Obat Keras');
        $alkes = $this->buatKategori('Alat Kesehatan');
        $item = $this->buatMasterItem('Item Sync', ['kategori_ids' => [$obat->id, $alkes->id]]);

        // Edit tinggal memilih satu kategori -> yang lain otomatis lepas.
        $this->post('/master-items/form/edit/'.$item->id, [
            'nama' => 'Item Sync',
            'harga_beli' => 2000,
            'laba' => 5,
            'supplier' => 'Bukulapuk',
            'jenis' => 'Alkes',
            'kategori_ids' => [$alkes->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$alkes->id], $item->fresh()->kategori()->pluck('kategori_items.id')->all());
    }

    public function test_kategori_tidak_ada_pada_master_item_ditolak()
    {
        $response = $this->post('/master-items/form/new', [
            'nama' => 'Item Kategori Ngawur',
            'harga_beli' => 1000,
            'laba' => 10,
            'supplier' => 'Tokopaedi',
            'jenis' => 'Obat',
            'kategori_ids' => [999999],
        ]);

        $response->assertSessionHasErrors('kategori_ids.0');
        $this->assertNull(MasterItem::where('nama', 'Item Kategori Ngawur')->first());
    }

    public function test_search_master_item_membawa_data_kategori()
    {
        $kategori = $this->buatKategori('Obat Keras');
        $item = $this->buatMasterItem('Item Dengan Kategori', ['kategori_ids' => [$kategori->id]]);

        $data = $this->getJson('/master-items/search?nama=Item Dengan Kategori')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($item->kode, $data[0]['kode']);
        $this->assertCount(1, $data[0]['kategori']);
        $this->assertSame($kategori->id, $data[0]['kategori'][0]['id']);
        $this->assertSame($kategori->kode, $data[0]['kategori'][0]['kode']);
        $this->assertSame('Obat Keras', $data[0]['kategori'][0]['nama']);
    }

    public function test_halaman_form_master_item_menyediakan_pilihan_kategori()
    {
        $kategori = $this->buatKategori('Obat Keras');

        $this->get('/master-items/form/new')
            ->assertStatus(200)
            ->assertSee('name="kategori_ids[]"', false)
            ->assertSee('Obat Keras');

        $item = $this->buatMasterItem('Item Edit Kategori', ['kategori_ids' => [$kategori->id]]);

        $response = $this->get('/master-items/form/edit/'.$item->id);
        $response->assertStatus(200);

        // Option kategori yang sudah dipilih harus punya atribut selected.
        $this->assertMatchesRegularExpression(
            '/<option value="'.$kategori->id.'"[^>]*\bselected\b/',
            $response->getContent(),
            'Kategori item harus terpilih di form edit'
        );
    }
}
