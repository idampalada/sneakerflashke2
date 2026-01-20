<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class PromoOneDecadeExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting
{
    public function collection()
    {
        return DB::table('promo_onedecade_entries')
            ->where('is_verified', true)
            ->orderBy('verified_at', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Undian Code',
            'Order Number',
            'Platform',
            'Contact Info',
            'Verified At',
        ];
    }

    public function map($row): array
{
    return [
        "'".$row->undian_code,        // A - TEXT PAKSA
        "'".$row->order_number,       // B - TEXT PAKSA
        strtoupper($row->platform),   // C
        "'".$row->contact_info,       // D - TEXT PAKSA
        optional($row->verified_at)->format('Y-m-d H:i:s'), // E
    ];
}


    /**
     * 🔒 PAKSA FORMAT KOLOM JADI TEXT
     */
    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT, // Undian Code
            'B' => NumberFormat::FORMAT_TEXT, // Order Number
            'D' => NumberFormat::FORMAT_TEXT, // Contact Info (HP)
        ];
    }
}
