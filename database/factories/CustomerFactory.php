<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        $customerType = fake()->randomElement(['individual', 'business']);

        return [
            'name' => $customerType === 'business'
                ? fake()->company()
                : fake()->name(),

            'email' => fake()->unique()->safeEmail(),

            'id_type' => fake()->randomElement(['MyKad', 'Passport', 'MyPR', 'MyTentera', 'MyPolis']),

            'id_no' => fake()->unique()->numerify('######-##-####'),

            'company_name' => $customerType === 'business'
                ? fake()->company()
                : null,

            'address' => fake()->streetAddress(),

            'city' => fake()->randomElement([
                'Kuala Lumpur',
                'Shah Alam',
                'Petaling Jaya',
                'Subang Jaya',
                'Johor Bahru',
                'Penang',
                'Ipoh',
                'Melaka'
            ]),

            'state' => fake()->randomElement([
                'Wilayah Persekutuan',
                'Selangor',
                'Johor',
                'Pulau Pinang',
                'Perak',
                'Melaka',
                'Negeri Sembilan'
            ]),

            'postal_code' => fake()->postcode(),

            'country' => 'Malaysia',

            'customer_type' => $customerType,

            'business_registration_no' => $customerType === 'business'
                ? 'ROC' . fake()->unique()->numerify('############')
                : null,

            'preferred_language' => fake()->randomElement(['ms', 'en']),

            'status' => fake()->randomElement(['active', 'inactive']),

            'tags' => json_encode(fake()->randomElements([
                'vip',
                'regular-customer',
                'corporate',
                'importer',
                'exporter',
                'manufacturer',
                'retailer',
                'logistics'
            ], fake()->numberBetween(1, 3))),
        ];
    }
}