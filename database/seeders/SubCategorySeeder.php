<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subCategories = collect([
            [
                'name' => 'pastry', 'category_id' => 1
            ],
            [
                'name' => 'protein hewani', 'category_id' => 1
            ],
            [
                'name' => 'karbo/nasi', 'category_id' => 1
            ],
            [
                'name' => 'sayur', 'category_id' => 1
            ],
            [
                'name' => 'beras', 'category_id' => 2
            ],
            [
                'name' => 'mie', 'category_id' => 2
            ],
            [
                'name' => 'protein hewani kalengan', 'category_id' => 2
            ],
            [
                'name' => 'susu', 'category_id' => 2
            ],
            [
                'name' => 'bumbu dapur', 'category_id' => 2
            ],
            [
                'name' => 'kopi', 'category_id' => 2
            ],
            [
                'name' => 'teh', 'category_id' => 2
            ],
            [
                'name' => 'minuman lainnya', 'category_id' => 2
            ]
        ]);
        $subCategories->each(function ($subCategory) {
            DB::table('sub_categories')->insert(
                ['name' => $subCategory['name'], 'category_id' => $subCategory['category_id']]
            );
        });
    }
}
