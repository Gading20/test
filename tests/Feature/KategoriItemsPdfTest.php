<?php

namespace Tests\Feature;

use App\Http\Controllers\KategoriItemsController;
use App\Models\KategoriItem;
use App\Models\MasterItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

class KategoriItemsPdfTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode kategori baru mengikuti aturan generateKode(): max (termasuk trashed) + 1. */
    private function kodeKategoriBaru(): string
    {
        $maxKode = KategoriItem::withTrashed()->max('kode');

        return ctype_digit((string) $maxKode)
            ? str_pad(((int) $maxKode) + 1, 5, '0', STR_PAD_LEFT)
            : '00001';
    }

    /** Kategori + item contoh untuk kebutuhan cetak. */
    private function siapkanData(): array
    {
        // Kode mengikuti generateKode() supaya tidak bentrok dengan data di DB.
        $kategori = KategoriItem::create(['kode' => $this->kodeKategoriBaru(), 'nama' => 'Obat Keras']);

        $maxItem = MasterItem::withTrashed()->max('kode');
        $nextItem = ctype_digit((string) $maxItem)
            ? str_pad(((int) $maxItem) + 1, 5, '0', STR_PAD_LEFT)
            : '00001';

        $item = new MasterItem([
            'kode' => $nextItem,
            'nama' => 'Paracetamol 500mg',
            'harga_beli' => 7500,
            'laba' => 10,
            'supplier' => 'Tokopaedi',
            'jenis' => 'Obat',
        ]);
        $item->save();
        $item->kategori()->sync([$kategori->id]);

        return [$kategori, $item];
    }

    /** Panggil helper waktu cetak produksi lewat refleksi. */
    private function waktuCetakProduksi(): string
    {
        $method = new ReflectionMethod(KategoriItemsController::class, 'waktuCetak');
        $method->setAccessible(true);

        return $method->invoke(new KategoriItemsController);
    }

    public function test_tombol_download_pdf_ada_di_halaman_detail()
    {
        [$kategori] = $this->siapkanData();

        $this->get('/kategori-items/view/'.$kategori->kode)
            ->assertStatus(200)
            ->assertSee('href="'.url('kategori-items/pdf/'.$kategori->kode).'"', false)
            ->assertSee('Download PDF');
    }

    public function test_endpoint_mengembalikan_file_pdf()
    {
        [$kategori] = $this->siapkanData();

        $response = $this->get('/kategori-items/pdf/'.$kategori->kode);

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('kategori-'.$kategori->kode.'.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent(), 'Balasan harus benar-benar file PDF');
    }

    public function test_pdf_kategori_tidak_ada_mengembalikan_404()
    {
        $this->get('/kategori-items/pdf/TIDAK-ADA')->assertStatus(404);
    }

    public function test_isi_pdf_memuat_kode_dan_nama_kategori()
    {
        [$kategori] = $this->siapkanData();
        $items = $kategori->masterItems()->get();

        $this->view('kategori_items.single.pdf', [
            'data' => $kategori,
            'items' => $items,
            'waktu_cetak' => $this->waktuCetakProduksi(),
        ])
            ->assertSee('Kode Kategori')
            ->assertSee($kategori->kode, false)
            ->assertSee('Nama Kategori')
            ->assertSee('Obat Keras');
    }

    public function test_isi_pdf_memuat_tabel_daftar_item()
    {
        [$kategori] = $this->siapkanData();
        $items = $kategori->masterItems()->get();

        $this->view('kategori_items.single.pdf', [
            'data' => $kategori,
            'items' => $items,
            'waktu_cetak' => $this->waktuCetakProduksi(),
        ])
            ->assertSeeInOrder(['Daftar Item pada Kategori Ini', 'Kode', 'Nama', 'Jenis', 'Harga Beli', 'Harga Jual', 'Supplier'])
            ->assertSee('Paracetamol 500mg')
            ->assertSee('7500')
            ->assertSee('8250') // harga jual 7500 + 10%
            ->assertSee('Tokopaedi');
    }

    public function test_pdf_kategori_kosong_menampilkan_pesan()
    {
        $kategori = KategoriItem::create(['kode' => $this->kodeKategoriBaru(), 'nama' => 'Belum Ada Item']);

        $this->view('kategori_items.single.pdf', [
            'data' => $kategori,
            'items' => collect(),
            'waktu_cetak' => $this->waktuCetakProduksi(),
        ])->assertSee('Belum ada item pada kategori ini.');
    }

    public function test_footer_pdf_memuat_tanggal_dan_waktu_cetak()
    {
        [$kategori] = $this->siapkanData();
        $waktu = $this->waktuCetakProduksi();

        $html = (string) $this->view('kategori_items.single.pdf', [
            'data' => $kategori,
            'items' => $kategori->masterItems()->get(),
            'waktu_cetak' => $waktu,
        ]);

        // Footer berisi stempel waktu lengkap: hari, tanggal, jam, dan zona WIB.
        $this->assertStringContainsString('Dicetak pada: '.$waktu, $html);
        $this->assertMatchesRegularExpression(
            '/Dicetak pada: [A-Za-z]+, \d{1,2} [A-Za-z]+ \d{4} \d{2}:\d{2}:\d{2} WIB/',
            $html,
            'Footer harus memuat tanggal + jam dalam WIB'
        );

        // Memakai hari ini (bukan waktu server UTC yang tertinggal 7 jam).
        $hariIni = now()->setTimezone('Asia/Jakarta')->locale('id')->isoFormat('D MMMM YYYY');
        $this->assertStringContainsString($hariIni, $html, 'Tanggal cetak harus hari ini menurut WIB');

        // position: fixed -> footer ikut tercetak di setiap halaman PDF.
        $this->assertStringContainsString('position: fixed', $html);
    }
}
