<?php
// database/seeders/KnowledgeBaseCategorySeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lookup;

class KnowledgeBaseCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'code' => 'customs_declaration',
                'name_en' => 'Customs Declaration',
                'name_ms' => 'Deklarasi Kastam',
                'description_en' => 'Information and guides about customs declarations and procedures',
                'description_ms' => 'Maklumat dan panduan tentang pengisytiharan kastam dan prosedur',
                'sort_order' => 1,
            ],
            [
                'code' => 'tax_duties',
                'name_en' => 'Tax & Duties',
                'name_ms' => 'Cukai & Duti',
                'description_en' => 'Tax and duty regulations, calculations, and payment procedures',
                'description_ms' => 'Peraturan cukai dan duti, pengiraan, dan prosedur pembayaran',
                'sort_order' => 2,
            ],
            [
                'code' => 'import_export',
                'name_en' => 'Import/Export',
                'name_ms' => 'Import/Eksport',
                'description_en' => 'Guidelines for importing and exporting goods',
                'description_ms' => 'Garis panduan untuk mengimport dan mengeksport barangan',
                'sort_order' => 3,
            ],
            [
                'code' => 'regulations_policies',
                'name_en' => 'Regulations & Policies',
                'name_ms' => 'Peraturan & Polisi',
                'description_en' => 'Regulatory requirements and policy documentation',
                'description_ms' => 'Keperluan peraturan dan dokumentasi polisi',
                'sort_order' => 4,
            ],
            [
                'code' => 'faqs',
                'name_en' => 'FAQs',
                'name_ms' => 'Soalan Lazim',
                'description_en' => 'Frequently asked questions and answers',
                'description_ms' => 'Soalan lazim dan jawapan',
                'sort_order' => 5,
            ],
            [
                'code' => 'user_guides',
                'name_en' => 'User Guides',
                'name_ms' => 'Panduan Pengguna',
                'description_en' => 'Step-by-step guides and tutorials',
                'description_ms' => 'Panduan langkah demi langkah dan tutorial',
                'sort_order' => 6,
            ],
        ];
        foreach ($categories as $cat) {
            Lookup::updateOrCreate(
                [
                    'type' => 'knowledge_base_category',
                    'code' => $cat['code'],
                ],
                [
                    'name_en' => $cat['name_en'],
                    'name_ms' => $cat['name_ms'],
                    'description_en' => $cat['description_en'],
                    'description_ms' => $cat['description_ms'],
                    'is_active' => true,
                    'sort_order' => $cat['sort_order'],
                ]
            );
        }
    }
}
