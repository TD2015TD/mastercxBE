<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('categories')->delete();

        $categories = [
            // SALES TAX: GENERAL
            [
                'department' => 'SALES TAX',
                'name' => 'GENERAL',
                'name_ms' => 'AM',
                'subcategories' => [
                    ['name' => 'Amendment', 'name_ms' => 'Pindaan'],
                    ['name' => 'Cancellation', 'name_ms' => 'Pembatalan'],
                    ['name' => 'Registration', 'name_ms' => 'Pendaftaran'],
                    ['name' => 'Tariff Code & Tax Rate Enquiries', 'name_ms' => 'Pertanyaan Kod Tarif & Kadar Cukai'],
                ]
            ],
            // SALES TAX: EXEMPTION
            [
                'department' => 'SALES TAX',
                'name' => 'EXEMPTION',
                'name_ms' => 'PENGECUALIAN',
                'subcategories' => [
                    ['name' => 'Exemption Sch A', 'name_ms' => 'Pengecualian Jadual A'],
                    ['name' => 'Exemption Sch B', 'name_ms' => 'Pengecualian Jadual B'],
                    ['name' => 'Exemption Sch C', 'name_ms' => 'Pengecualian Jadual C'],
                ]
            ],
            // SALES TAX: PAYMENT
            [
                'department' => 'SALES TAX',
                'name' => 'PAYMENT',
                'name_ms' => 'BAYARAN',
                'subcategories' => [
                    ['name' => 'Payment', 'name_ms' => 'Bayaran'],
                    ['name' => 'Penalty', 'name_ms' => 'Penalti'],
                    ['name' => 'Tax Return Declaration', 'name_ms' => 'Perisytiharan Penyata Cukai'],
                    ['name' => 'Others', 'name_ms' => 'Lain-lain'],
                ]
            ],
            // SALES TAX: REMISSION/REFUND/DRAWBACK
            [
                'department' => 'SALES TAX',
                'name' => 'REMISSION/REFUND/DRAWBACK',
                'name_ms' => 'PELEPASAN/BAYARAN BALIK/DRAWBEK',
                'subcategories' => [
                    ['name' => 'Drawback', 'name_ms' => 'Drawbek'],
                    ['name' => 'Remission', 'name_ms' => 'Pelepasan'],
                    ['name' => 'Refund Others', 'name_ms' => 'Bayaran Balik Lain-lain'],
                ]
            ],
            // SERVICE TAX: GENERAL
            [
                'department' => 'SERVICE TAX',
                'name' => 'GENERAL',
                'name_ms' => 'AM',
                'subcategories' => [
                    ['name' => 'Amendment', 'name_ms' => 'Pindaan'],
                    ['name' => 'Cancellation', 'name_ms' => 'Pembatalan'],
                    ['name' => 'Exemption', 'name_ms' => 'Pengecualian'],
                    ['name' => 'Registration', 'name_ms' => 'Pendaftaran'],
                    ['name' => 'Service Type Code & Tax Rate', 'name_ms' => 'Kod Jenis Perkhidmatan & Kadar Cukai'],
                    ['name' => 'Enquiries', 'name_ms' => 'Pertanyaan'],
                ]
            ],
            // SERVICE TAX: GROUP ENQ.
            [
                'department' => 'SERVICE TAX',
                'name' => 'GROUP ENQ.',
                'name_ms' => 'PERTANYAAN KUMPULAN',
                'subcategories' => [
                    ['name' => 'Rental or Leasing Services', 'name_ms' => 'Perkhidmatan Sewaan atau Pajakan'],
                    ['name' => 'Construction Work Services', 'name_ms' => 'Perkhidmatan Kerja Pembinaan'],
                    ['name' => 'Private Healthcare Services', 'name_ms' => 'Perkhidmatan Penjagaan Kesihatan Swasta'],
                    ['name' => 'Financial Services', 'name_ms' => 'Perkhidmatan Kewangan'],
                    ['name' => 'Private Education Services', 'name_ms' => 'Perkhidmatan Pendidikan Swasta'],
                ]
            ],
            // SERVICE TAX: PAYMENT
            [
                'department' => 'SERVICE TAX',
                'name' => 'PAYMENT',
                'name_ms' => 'BAYARAN',
                'subcategories' => [
                    ['name' => 'Payment', 'name_ms' => 'Bayaran'],
                    ['name' => 'Penalty', 'name_ms' => 'Penalti'],
                    ['name' => 'Tax Return Declaration Others', 'name_ms' => 'Perisytiharan Penyata Cukai Lain-lain'],
                ]
            ],
            // SERVICE TAX: REMISSION/REFUND
            [
                'department' => 'SERVICE TAX',
                'name' => 'REMISSION/REFUND',
                'name_ms' => 'PELEPASAN/BAYARAN BALIK',
                'subcategories' => [
                    ['name' => 'Remission', 'name_ms' => 'Pelepasan'],
                    ['name' => 'Refund', 'name_ms' => 'Bayaran Balik'],
                    ['name' => 'Others', 'name_ms' => 'Lain-lain'],
                ]
            ],
            // IMP/EXP & INDUSTRIES
            [
                'department' => 'IMP/EXP & INDUSTRIES',
                'name' => 'GENERAL',
                'name_ms' => 'AM',
                'subcategories' => [
                    ['name' => 'Courier/Post', 'name_ms' => 'Kurier/Pos'],
                    ['name' => 'Declaration/MyCIEDS', 'name_ms' => 'Perisytiharan/MyCIEDS'],
                    ['name' => 'Facilities', 'name_ms' => 'Kemudahan'],
                    ['name' => 'Licensing', 'name_ms' => 'Pelesenan'],
                    ['name' => 'Payment', 'name_ms' => 'Bayaran'],
                    ['name' => 'Tariff Code & Rate of Duties Others', 'name_ms' => 'Kod Tarif & Kadar Duti Lain-lain'],
                ]
            ],
            // IMP/EXP&IND: FREEZONE(FIZ/FTZ)
            [
                'department' => 'IMP/EXP & INDUSTRIES',
                'name' => 'FREEZONE (FIZ/FTZ)',
                'name_ms' => 'ZON BEBAS (FIZ/FTZ)',
                'subcategories' => [
                    ['name' => 'Registration', 'name_ms' => 'Pendaftaran'],
                    ['name' => 'Disposal', 'name_ms' => 'Pelupusan'],
                    ['name' => 'Export Out', 'name_ms' => 'Eksport Keluar'],
                    ['name' => 'Import In', 'name_ms' => 'Import Masuk'],
                    ['name' => 'Transhipments Others', 'name_ms' => 'Pemindahan Kargo Lain-lain'],
                ]
            ],
            // IMP/EXP&IND: EXEMPTIONS
            [
                'department' => 'IMP/EXP & INDUSTRIES',
                'name' => 'EXEMPTIONS',
                'name_ms' => 'PENGECUALIAN',
                'subcategories' => [
                    ['name' => 'Facility', 'name_ms' => 'Kemudahan'],
                    ['name' => 'Procedures / Permit Others', 'name_ms' => 'Prosedur / Permit Lain-lain'],
                ]
            ],
            // IMP/EXP&IND: DRAWBACK
            [
                'department' => 'IMP/EXP & INDUSTRIES',
                'name' => 'DRAWBACK',
                'name_ms' => 'DRAWBEK',
                'subcategories' => [
                    ['name' => 'Disposal of Import Goods', 'name_ms' => 'Pelupusan Barang Import'],
                    ['name' => 'Imported Manufacturing Materials & Export Finished Goods', 'name_ms' => 'Bahan Pembuatan Import & Barang Siap Eksport'],
                    ['name' => 'Re-Export of Imported Goods Others', 'name_ms' => 'Eksport Semula Barang Import Lain-lain'],
                ]
            ],
            // IMP/EXP&IND: LMW
            [
                'department' => 'IMP/EXP & INDUSTRIES',
                'name' => 'LMW',
                'name_ms' => 'LMW',
                'subcategories' => [
                    ['name' => 'Registration Exemption', 'name_ms' => 'Pengecualian Pendaftaran'],
                    ['name' => 'Payment', 'name_ms' => 'Bayaran'],
                    ['name' => 'Others', 'name_ms' => 'Lain-lain'],
                ]
            ],
            // IMP/EXP&IND: WAREHOUSE LICENSING
            [
                'department' => 'IMP/EXP & INDUSTRIES',
                'name' => 'WAREHOUSE LICENSING',
                'name_ms' => 'PELESENAN GUDANG',
                'subcategories' => [
                    ['name' => 'Agent Warehouse', 'name_ms' => 'Gudang Ejen'],
                    ['name' => 'License Renewal/Amend/Cancellation', 'name_ms' => 'Pembaharuan/Pindaan/Pembatalan Lesen'],
                    ['name' => 'PEKEMA', 'name_ms' => 'PEKEMA'],
                    ['name' => 'Public Warehouse', 'name_ms' => 'Gudang Awam'],
                    ['name' => 'Premise Relocation Others', 'name_ms' => 'Pemindahan Premis Lain-lain'],
                ]
            ],
            // GST: CANCELLATION
            [
                'department' => 'GST',
                'name' => 'CANCELLATION',
                'name_ms' => 'PEMBATALAN',
                'subcategories' => [
                    ['name' => 'Cancellation', 'name_ms' => 'Pembatalan'],
                ]
            ],
            // GST: ENQUIRIES
            [
                'department' => 'GST',
                'name' => 'ENQUIRIES',
                'name_ms' => 'PERTANYAAN',
                'subcategories' => [
                    ['name' => 'Enquiries', 'name_ms' => 'Pertanyaan'],
                    ['name' => 'Reset Password Others', 'name_ms' => 'Tetap Semula Kata Laluan Lain-lain'],
                ]
            ],
            // GST: REFUND
            [
                'department' => 'GST',
                'name' => 'REFUND',
                'name_ms' => 'BAYARAN BALIK',
                'subcategories' => [
                    ['name' => 'Refund', 'name_ms' => 'Bayaran Balik'],
                ]
            ],
            // GST: PAYMENT
            [
                'department' => 'GST',
                'name' => 'PAYMENT',
                'name_ms' => 'BAYARAN',
                'subcategories' => [
                    ['name' => 'Payment', 'name_ms' => 'Bayaran'],
                    ['name' => 'Compound', 'name_ms' => 'Kompaun'],
                    ['name' => 'Remission', 'name_ms' => 'Pelepasan'],
                    ['name' => 'Others', 'name_ms' => 'Lain-lain'],
                ]
            ],
            // EXCISE
            [
                'department' => 'EXCISE',
                'name' => 'GENERAL',
                'name_ms' => 'AM',
                'subcategories' => [
                    ['name' => 'Passenger / Traveller', 'name_ms' => 'Penumpang / Pelancong'],
                    ['name' => 'Tax Rate', 'name_ms' => 'Kadar Cukai'],
                    ['name' => 'Drawback', 'name_ms' => 'Drawbek'],
                    ['name' => 'Remission', 'name_ms' => 'Pelepasan'],
                    ['name' => 'Refund', 'name_ms' => 'Bayaran Balik'],
                    ['name' => 'Others', 'name_ms' => 'Lain-lain'],
                ]
            ],
            // EXCISE: MYEXCISE PAYMENT
            [
                'department' => 'EXCISE',
                'name' => 'MYEXCISE PAYMENT',
                'name_ms' => 'BAYARAN MYEXCISE',
                'subcategories' => [
                    ['name' => 'Payment', 'name_ms' => 'Bayaran'],
                    ['name' => 'Declarations', 'name_ms' => 'Perisytiharan'],
                    ['name' => 'Others', 'name_ms' => 'Lain-lain'],
                ]
            ],
            // EXCISE: FACILITIES
            [
                'department' => 'EXCISE',
                'name' => 'FACILITIES',
                'name_ms' => 'KEMUDAHAN',
                'subcategories' => [
                    ['name' => 'Movement from Customs Warehouse/Excise Warehouse', 'name_ms' => 'Pergerakan dari Gudang Kastam/Gudang Eksais'],
                    ['name' => 'Others', 'name_ms' => 'Lain-lain'],
                ]
            ],
            // EXCISE: LICENSING
            [
                'department' => 'EXCISE',
                'name' => 'LICENSING',
                'name_ms' => 'PELESENAN',
                'subcategories' => [
                    ['name' => 'Registration', 'name_ms' => 'Pendaftaran'],
                    ['name' => 'Amendment / Cancellation', 'name_ms' => 'Pindaan / Pembatalan'],
                    ['name' => 'License Type Others', 'name_ms' => 'Jenis Lesen Lain-lain'],
                ]
            ],
            // LEVY: MYSAWIT
            [
                'department' => 'LEVY',
                'name' => 'MYSAWIT',
                'name_ms' => 'MYSAWIT',
                'subcategories' => [
                    ['name' => 'Registration', 'name_ms' => 'Pendaftaran'],
                    ['name' => 'Payment Others', 'name_ms' => 'Bayaran Lain-lain'],
                ]
            ],
            // PASSENGER LEVY
            [
                'department' => 'PASSENGER LEVY',
                'name' => 'GENERAL',
                'name_ms' => 'AM',
                'subcategories' => [
                    ['name' => 'Registration', 'name_ms' => 'Pendaftaran'],
                    ['name' => 'Payment Others', 'name_ms' => 'Bayaran Lain-lain'],
                ]
            ],
            // TTX: REGISTRATION
            [
                'department' => 'TTX',
                'name' => 'REGISTRATION',
                'name_ms' => 'PENDAFTARAN',
                'subcategories' => [
                    ['name' => 'Registration', 'name_ms' => 'Pendaftaran'],
                    ['name' => 'Amendment', 'name_ms' => 'Pindaan'],
                    ['name' => 'Cancellation Others', 'name_ms' => 'Pembatalan Lain-lain'],
                ]
            ],
            // TTX: PAYMENT
            [
                'department' => 'TTX',
                'name' => 'PAYMENT',
                'name_ms' => 'BAYARAN',
                'subcategories' => [
                    ['name' => 'Payment', 'name_ms' => 'Bayaran'],
                    ['name' => 'Remission', 'name_ms' => 'Pelepasan'],
                    ['name' => 'Others', 'name_ms' => 'Lain-lain'],
                ]
            ],
            // TTX: GENERAL ENQUIRY
            [
                'department' => 'TTX',
                'name' => 'GENERAL ENQUIRY',
                'name_ms' => 'PERTANYAAN AM',
                'subcategories' => [
                    ['name' => 'Enquiries', 'name_ms' => 'Pertanyaan'],
                ]
            ],
            // OTHERS ENQUIRY
            [
                'department' => 'OTHERS',
                'name' => 'ENQUIRY',
                'name_ms' => 'PERTANYAAN',
                'subcategories' => [
                    ['name' => 'LVG (Low Value Goods)', 'name_ms' => 'LVG (Barang Bernilai Rendah)'],
                    ['name' => 'MySTODS (Digital Tax)', 'name_ms' => 'MySTODS (Cukai Digital)'],
                    ['name' => 'MYOGA', 'name_ms' => 'MYOGA'],
                    ['name' => 'MyDLV', 'name_ms' => 'MyDLV'],
                    ['name' => 'MyVDP', 'name_ms' => 'MyVDP'],
                ]
            ],
            // TECHNICAL DIVISION
            [
                'department' => 'TECHNICAL DIVISION',
                'name' => 'GENERAL',
                'name_ms' => 'AM',
                'subcategories' => [
                    ['name' => 'Customs Ruling', 'name_ms' => 'Keputusan Kastam'],
                    ['name' => 'Classification', 'name_ms' => 'Klasifikasi'],
                    ['name' => 'Tariff Code Others', 'name_ms' => 'Kod Tarif Lain-lain'],
                ]
            ],
            // GENERAL ENQ. ADMINISTRATIVE
            [
                'department' => 'GENERAL',
                'name' => 'ADMINISTRATIVE',
                'name_ms' => 'PENTADBIRAN',
                'subcategories' => [
                    ['name' => 'Practical Training', 'name_ms' => 'Latihan Praktikal'],
                    ['name' => 'Complaint', 'name_ms' => 'Aduan'],
                    ['name' => 'Others', 'name_ms' => 'Lain-lain'],
                ]
            ],
            // PREVENTIVE (PENGUATKUASAAN)
            [
                'department' => 'PREVENTIVE',
                'name' => 'ENFORCEMENT',
                'name_ms' => 'PENGUATKUASAAN',
                'subcategories' => [
                    ['name' => 'Disposal Of Vehicle/Tender', 'name_ms' => 'Pelupusan Kenderaan/Tender'],
                    ['name' => 'Information/Reward', 'name_ms' => 'Maklumat/Ganjaran'],
                    ['name' => 'Search/Raid/Detain', 'name_ms' => 'Geledah/Serbuan/Tahanan'],
                    ['name' => 'Others', 'name_ms' => 'Lain-lain'],
                ]
            ],
        ];

        foreach ($categories as $categoryData) {
            // Create parent category
            $parentCategory = Category::create([
                'department' => $categoryData['department'],
                'name' => $categoryData['name'],
                'name_ms' => $categoryData['name_ms'],
                'level' => 1,
                'is_active' => true,
                'sort_order' => 0,
            ]);

            // Create subcategories
            $sortOrder = 1;
            foreach ($categoryData['subcategories'] as $subcat) {
                Category::create([
                    'department' => $categoryData['department'],
                    'parent_id' => $parentCategory->id,
                    'name' => $subcat['name'],
                    'name_ms' => $subcat['name_ms'],
                    'level' => 2,
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        $this->command->info('Categories and subcategories seeded successfully!');
    }
}
