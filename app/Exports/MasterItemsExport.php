<?php

namespace App\Exports;

use App\Models\MasterItem;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export seluruh master items ke .xlsx.
 * Kolom mengikuti ketentuan: No, Nama Kategori (dipisah koma), Nama Items,
 * Nama Supplier, Harga, Laba, Harga Jual.
 */
class MasterItemsExport implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles
{
    /** Nomor urut baris data; direset otomatis karena instance baru tiap download. */
    private int $no = 0;

    public function collection()
    {
        // Soft-deleted otomatis kecualikan (global scope SoftDeletes).
        // Relasi kategori ikut di-eager-load dan diurutkan nama, supaya satu
        // query untuk semua item dan urutan kategori sama dengan halaman view.
        return MasterItem::with(['kategori' => fn ($q) => $q->orderBy('nama')])
            ->orderBy('kode')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Kategori',
            'Nama Items',
            'Nama Supplier',
            'Harga',
            'Laba',
            'Harga Jual',
        ];
    }

    public function map($item): array
    {
        $this->no++;

        // Bisa lebih dari satu kategori -> digabung jadi satu sel pakai koma.
        // Tidak ada kategori -> "-" (sama dengan tampilan di tabel index).
        $kategori = $item->kategori->pluck('nama')->join(', ');
        if ($kategori === '') {
            $kategori = '-';
        }

        return [
            $this->no,
            $kategori,
            $item->nama,
            $item->supplier,
            $item->harga_beli,
            $item->laba,
            // Rumus sama dengan index/js.blade.php dan halaman view (dibulatkan).
            (int) round($item->harga_beli + $item->harga_beli * $item->laba / 100),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Baris judul (heading) ditebalkan.
            1 => ['font' => ['bold' => true]],
        ];
    }

    // Catatan: kunci columnWidths() harus huruf kolom ('A'..'G'),
    // sedangkan kunci styles() memakai nomor baris.
    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No
            'B' => 32,  // Nama Kategori
            'C' => 32,  // Nama Items
            'D' => 20,  // Nama Supplier
            'E' => 14,  // Harga
            'F' => 8,   // Laba
            'G' => 14,  // Harga Jual
        ];
    }
}
