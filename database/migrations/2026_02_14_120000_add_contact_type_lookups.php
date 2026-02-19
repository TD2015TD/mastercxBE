<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $contactTypes = [
            [
                'type' => 'contact_type',
                'code' => 'phone',
                'name_en' => 'Phone',
                'name_ms' => 'Telefon',
                'description_en' => 'Phone number',
                'description_ms' => 'Nombor telefon',
                'icon' => 'phone',
                'color' => 'blue',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => 'contact_type',
                'code' => 'email',
                'name_en' => 'Email',
                'name_ms' => 'E-mel',
                'description_en' => 'Email address',
                'description_ms' => 'Alamat e-mel',
                'icon' => 'mail',
                'color' => 'blue',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type' => 'contact_type',
                'code' => 'mobile',
                'name_en' => 'Mobile',
                'name_ms' => 'Mudah Alih',
                'description_en' => 'Mobile phone number',
                'description_ms' => 'Nombor telefon mudah alih',
                'icon' => 'smartphone',
                'color' => 'green',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type' => 'contact_type',
                'code' => 'fax',
                'name_en' => 'Fax',
                'name_ms' => 'Faks',
                'description_en' => 'Fax number',
                'description_ms' => 'Nombor faks',
                'icon' => 'printer',
                'color' => 'purple',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'type' => 'contact_type',
                'code' => 'office',
                'name_en' => 'Office',
                'name_ms' => 'Pejabat',
                'description_en' => 'Office address',
                'description_ms' => 'Alamat pejabat',
                'icon' => 'building',
                'color' => 'orange',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'type' => 'contact_type',
                'code' => 'whatsapp',
                'name_en' => 'WhatsApp',
                'name_ms' => 'WhatsApp',
                'description_en' => 'WhatsApp number',
                'description_ms' => 'Nombor WhatsApp',
                'icon' => 'message-circle',
                'color' => 'green',
                'sort_order' => 6,
                'is_active' => true,
            ],
        ];

        foreach ($contactTypes as $contactType) {
            $exists = DB::table('lookups')
                ->where('type', $contactType['type'])
                ->where('code', $contactType['code'])
                ->exists();

            if (!$exists) {
                DB::table('lookups')->insert($contactType);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('lookups')
            ->where('type', 'contact_type')
            ->whereIn('code', ['phone', 'email', 'mobile', 'fax', 'office', 'whatsapp'])
            ->delete();
    }
};
