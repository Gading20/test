<?php

namespace Tests\Feature;

use App\Models\KategoriItem;
use App\Models\MasterItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class MasterItemsExcelTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKategori(string $nama): KategoriItem
    {
        return KategoriItem::create([
            'kode' => $this->kodeKategoriBaru(),
            'nama' => $nama,
        ]);
    }

    private function kodeKategoriBaru(): string
    {
        $maxKode = KategoriItem::withTrashed()->max('kode');

        return ctype_digit((string) $maxKode)
            ? str_pad(((int) $maxKode) + 1, 5, '0', STR_PAD_LEFT)
            : '00001';
    }

    private function buatMasterItem(string $nama, array $kategoriIds = [], array $overrides = []): MasterItem
    {
        $maxKode = MasterItem::withTrashed()->max('kode');
        $kode = ctype_digit((string) $maxKode)
            ? str_pad(((int) $maxKode) + 1, 5, '0', STR_PAD_LEFT)
            : '00001';

        $item = new MasterItem(array_merge([
            'kode' => $kode,
            'nama' => $nama,
            'harga_beli' => 7500,
            'laba' => 10,
            'supplier' => 'Tokopaedi',
            'jenis' => 'Obat',
        ], $overrides));
        $item->save();
        $item->kategori()->sync($kategoriIds);

        return $item;
    }

    /** Baca isi file Excel dari response download. */
    private function isiExcel($response): array
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path, 'File Excel harus dibuat');

        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    private function unduhExcel()
    {
        return $this->get('/master-items/excel');
    }

    public function test_tombol_download_excel_ada_di_index()
    {
        $this->get('/master-items')
            ->assertStatus(200)
            ->assertSee('href="'.url('master-items/excel').'"', false)
            ->assertSee('Download Excel');
    }

    public function test_endpoint_mengembalikan_file_xlsx()
    {
        $response = $this->unduhExcel();

        $response->assertStatus(200);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

        $tanggal = now()->setTimezone('Asia/Jakarta')->format('Y-m-d');
        $this->assertStringContainsString('master-items-'.$tanggal.'.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_header_excel_sesuai_ketentuan()
    {
        $rows = $this->isiExcel($this->unduhExcel());

        $this->assertSame(
            ['No', 'Nama Kategori', 'Nama Items', 'Nama Supplier', 'Harga', 'Laba', 'Harga Jual'],
            $rows[0],
            'Urutan kolom harus persis sesuai ketentuan'
        );
    }

    public function test_isi_excel_memuat_item_dengan_banyak_kategori_dipisah_koma()
    {
        $obat = $this->buatKategori('Obat Keras');
        $resmi = $this->buatKategori('Resep Dokter');
        $this->buatMasterItem('Paracetamol 500mg', [$obat->id, $resmi->id], [
            'harga_beli' => 7500,
            'laba' => 10,
            'supplier' => 'Bukulapuk',
        ]);

        $rows = $this->isiExcel($this->unduhExcel());
        $data = array_values(array_filter($rows, fn ($row) => ($row[2] ?? null) === 'Paracetamol 500mg'));

        $this->assertCount(1, $data, 'Item uji harus ada di Excel');
        $this->assertSame([
            'Obat Keras, Resep Dokter',
            'Paracetamol 500mg',
            'Bukulapuk',
            7500,
            10,
            8250,
        ], array_slice($data[0], 1), 'Kolom 2-7 harus sesuai ketentuan');

        // No = posisi baris pada sheet (data lama bisa lebih dulu, jadi bukan
        // selalu 1 untuk item uji; urutan berurutan diuji test terpisah).
        $semuaData = array_slice($rows, 1);
        $posisi = array_search($data[0], $semuaData, true);
        $this->assertSame($posisi + 1, $data[0][0], 'Kolom No harus sama dengan posisi barisnya');
    }

    public function test_item_tanpa_kategori_memakai_strip()
    {
        $this->buatMasterItem('Item Tanpa Kategori');

        $rows = $this->isiExcel($this->unduhExcel());
        $data = array_values(array_filter($rows, fn ($row) => ($row[2] ?? null) === 'Item Tanpa Kategori'));

        $this->assertCount(1, $data);
        $this->assertSame('-', $data[0][1], 'Kategori kosong ditandai strip');
    }

    public function test_item_soft_delete_tidak_masuk_excel()
    {
        $this->buatMasterItem('Item Dihapus')->delete();
        $this->buatMasterItem('Item Aktif');

        $rows = $this->isiExcel($this->unduhExcel());
        $nama = array_column(array_slice($rows, 1), 2);

        $this->assertContains('Item Aktif', $nama);
        $this->assertNotContains('Item Dihapus', $nama, 'Item soft delete tidak boleh ikut terexport');
    }

    public function test_nomor_urut_berurutan()
    {
        $this->buatMasterItem('Item A');
        $this->buatMasterItem('Item B');
        $this->buatMasterItem('Item C');

        $rows = array_slice($this->isiExcel($this->unduhExcel()), 1);

        $this->assertSame(
            range(1, count($rows)),
            array_map(fn ($row) => (int) $row[0], $rows),
            'Kolom No harus berurutan dari 1'
        );
    }
}
