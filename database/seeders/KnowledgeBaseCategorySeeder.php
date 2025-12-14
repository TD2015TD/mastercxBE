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
            ['code' => 'customs_declaration', 'name_en' => 'Customs Declaration', 'name_ms' => 'Deklarasi Kastam'],
            ['code' => 'tax_duties', 'name_en' => 'Tax & Duties', 'name_ms' => 'Cukai & Duti'],
            ['code' => 'import_export', 'name_en' => 'Import/Export', 'name_ms' => 'Import/Eksport'],
            ['code' => 'regulations_policies', 'name_en' => 'Regulations & Policies', 'name_ms' => 'Peraturan & Polisi'],
            ['code' => 'faqs', 'name_en' => 'FAQs', 'name_ms' => 'Soalan Lazim'],
            ['code' => 'user_guides', 'name_en' => 'User Guides', 'name_ms' => 'Panduan Pengguna'],
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
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            );
        }
    }
}
