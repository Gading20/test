<?php

namespace Tests\Feature;

use App\Models\MasterItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MasterItemSearchFilterTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Buat item uji. Database ini dipakai bersama (tes memakai DatabaseTransactions,
     * jadi barisnya ikut ter-rollback), jadi assertion selalu mengecek milik sendiri.
     */
    private function buatItem(int $hargaBeli): MasterItem
    {
        $uniq = uniqid();

        return MasterItem::create([
            'kode' => 'FT'.strtoupper($uniq),
            'nama' => 'Item Uji Filter '.$uniq,
            'harga_beli' => $hargaBeli,
            'laba' => 10,
            'supplier' => 'Tokopaedi',
            'jenis' => 'Obat',
        ]);
    }

    /** @return string[] Kode-kode yang balik dari endpoint search. */
    private function kodeHasil(array $query): array
    {
        $response = $this->getJson('/master-items/search?'.http_build_query($query));
        $response->assertOk();

        return collect($response->json('data'))->pluck('kode')->all();
    }

    public function test_hargamax_nol_tetap_dipakai_bukan_dianggap_kosong()
    {
        $gratis = $this->buatItem(0);
        $berbayar = $this->buatItem(500);

        $kode = $this->kodeHasil(['hargamax' => 0]);

        $this->assertContains($gratis->kode, $kode, 'Item berharga 0 harus muncul saat filter maks 0');
        $this->assertNotContains($berbayar->kode, $kode, 'Item 500 tidak boleh muncul saat filter maks 0');
    }

    public function test_hargamin_nol_mengembalikan_semua_data()
    {
        $item = $this->buatItem(500);

        $kode = $this->kodeHasil(['hargamin' => 0]);

        $this->assertContains($item->kode, $kode, 'Filter min 0 harusnya tidak menyaring apa pun');
    }

    public function test_pemisah_ribuan_dipahami_untuk_harga_min()
    {
        $kecil = $this->buatItem(500);
        $besar = $this->buatItem(1500000);

        $kode = $this->kodeHasil(['hargamin' => '1.500.000']);

        $this->assertContains($besar->kode, $kode, '1.500.000 harus dibaca sebagai 1500000');
        $this->assertNotContains($kecil->kode, $kode, 'Item 500 harus tersaring oleh min 1500000');
    }

    public function test_pemisah_ribuan_koma_dipahami()
    {
        $kecil = $this->buatItem(500);
        $besar = $this->buatItem(1500000);

        $kode = $this->kodeHasil(['hargamin' => '1,500']);

        $this->assertContains($besar->kode, $kode, '1,500 harus dibaca sebagai 1500');
        $this->assertNotContains($kecil->kode, $kode, 'Item 500 harus tersaring oleh min 1500');
    }

    public function test_rentang_harga_min_dan_max_dipakai_bersamaan()
    {
        $termurah = $this->buatItem(500);
        $tengah = $this->buatItem(1000);
        $termahal = $this->buatItem(2000);

        $kode = $this->kodeHasil(['hargamin' => 500, 'hargamax' => 1000]);

        $this->assertContains($termurah->kode, $kode);
        $this->assertContains($tengah->kode, $kode);
        $this->assertNotContains($termahal->kode, $kode, 'Item 2000 di luar rentang 500-1000');
    }

    public function test_harga_min_lebih_besar_dari_max_ditolak()
    {
        $this->buatItem(1000);

        $this->getJson('/master-items/search?hargamin=2000&hargamax=1000')
            ->assertStatus(422);
    }

    public function test_harga_negatif_ditolak()
    {
        $this->getJson('/master-items/search?hargamin=-500')
            ->assertStatus(422);

        $this->getJson('/master-items/search?hargamax=-1')
            ->assertStatus(422);
    }

    public function test_harga_bukan_angka_ditolak()
    {
        $this->getJson('/master-items/search?hargamax=abc')
            ->assertStatus(422);

        $this->getJson('/master-items/search?hargamin=Rp')
            ->assertStatus(422);
    }
}
