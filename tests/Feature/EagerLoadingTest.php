<?php

namespace Tests\Feature;

use App\Models\KategoriItem;
use App\Models\MasterItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guard anti N+1 (Eloquent + eager loading).
 *
 * Pengukurannya: hitung query yang menyentuh pivot kategori_master_item saat
 * request. Dengan eager loading jumlahnya tetap (1 query untuk semua baris),
 * sedangkan lazy loading = 1 query per baris, jadi langsung ketahuan.
 *
 * Model::preventLazyLoading() juga dinyalakan; untuk jalur multi-baris seperti
 * ini Laravel benar-benar memasang flag-nya, sehingga lazy loading langsung
 * melempar LazyLoadingViolationException.
 */
class EagerLoadingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading();
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

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

    private function buatItem(string $nama, int $kategoriId): MasterItem
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
        $item->kategori()->attach($kategoriId);

        return $item;
    }

    /**
     * Jumlah query yang menyentuh pivot kategori_master_item ketika GET $url.
     */
    private function jumlahQueryPivot(string $url): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->get($url)->assertStatus(200);

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return collect($log)
            ->filter(fn ($query) => stripos($query['query'], 'kategori_master_item') !== false)
            ->count();
    }

    public function test_search_master_items_tidak_n_plus_satu()
    {
        $kategori = KategoriItem::create(['kode' => $this->kodeKategoriBaru(), 'nama' => 'Obat Keras']);
        foreach (range(1, 5) as $i) {
            $this->buatItem('Item N+1 nomor '.$i, $kategori->id);
        }

        $jumlah = $this->jumlahQueryPivot('/master-items/search');

        $this->assertLessThanOrEqual(
            2,
            $jumlah,
            'Relasi kategori harus di-eager-load (1 query untuk semua baris), bukan N+1'
        );
    }

    public function test_excel_master_items_tidak_n_plus_satu()
    {
        $kategori = KategoriItem::create(['kode' => $this->kodeKategoriBaru(), 'nama' => 'Alkes']);
        foreach (range(1, 5) as $i) {
            $this->buatItem('Item Excel nomor '.$i, $kategori->id);
        }

        $jumlah = $this->jumlahQueryPivot('/master-items/excel');

        $this->assertLessThanOrEqual(
            2,
            $jumlah,
            'Export Excel harus memakai with(kategori) sekali, bukan query per baris'
        );
    }

    public function test_search_kategori_items_mengembalikan_jumlah_item()
    {
        $kategori = KategoriItem::create(['kode' => $this->kodeKategoriBaru(), 'nama' => 'Dihitung Aggregate']);
        foreach (range(1, 3) as $i) {
            $this->buatItem('Item hitung nomor '.$i, $kategori->id);
        }

        $response = $this->getJson('/kategori-items/search?'.http_build_query(['nama' => 'Dihitung Aggregate']));
        $response->assertOk();

        $baris = collect($response->json('data'))->firstWhere('kode', $kategori->kode);
        $this->assertNotNull($baris, 'Kategori uji harus ada di hasil search');
        $this->assertSame(3, (int) ($baris['master_items_count'] ?? -1), 'Jumlah item harus ikut terkirim (withCount)');
    }

    public function test_search_kategori_items_memakai_aggregate_count()
    {
        $kategori = KategoriItem::create(['kode' => $this->kodeKategoriBaru(), 'nama' => 'Umum']);
        foreach (range(1, 5) as $i) {
            $this->buatItem('Item kategori nomor '.$i, $kategori->id);
        }

        $jumlah = $this->jumlahQueryPivot('/kategori-items/search');

        $this->assertLessThanOrEqual(
            2,
            $jumlah,
            'Jumlah item per kategori harus pakai withCount (aggregate), bukan hitung per baris'
        );
    }
}
