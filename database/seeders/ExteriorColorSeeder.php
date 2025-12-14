<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExteriorColor;

class ExteriorColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            [
                'name' => 'White',
                'name_ar' => 'أبيض',
                'name_kr' => 'سپی',
                'hex_code' => '#FFFFFF',
            ],
            [
                'name' => 'Black',
                'name_ar' => 'أسود',
                'name_kr' => 'ڕەش',
                'hex_code' => '#000000',
            ],
            [
                'name' => 'Red',
                'name_ar' => 'أحمر',
                'name_kr' => 'سوور',
                'hex_code' => '#FF0000',
            ],
            [
                'name' => 'Grey',
                'name_ar' => 'رمادي',
                'name_kr' => 'خۆڵەمێشی',
                'hex_code' => '#808080',
            ],
            [
                'name' => 'Blue',
                'name_ar' => 'أزرق',
                'name_kr' => 'شین',
                'hex_code' => '#0000FF',
            ],
            [
                'name' => 'Green',
                'name_ar' => 'أخضر',
                'name_kr' => 'سەوز',
                'hex_code' => '#008000',
            ],
            [
                'name' => 'Purple',
                'name_ar' => 'بنفسجي',
                'name_kr' => 'مۆر',
                'hex_code' => '#800080',
            ],
            [
                'name' => 'Other',
                'name_ar' => 'أخرى',
                'name_kr' => 'دیکە',
                'hex_code' => '#CCCCCC',
            ],
            [
                'name' => 'Gold',
                'name_ar' => 'ذهبي',
                'name_kr' => 'زێڕین',
                'hex_code' => '#FFD700',
            ],
            [
                'name' => 'Turquoise',
                'name_ar' => 'فيروزي',
                'name_kr' => 'فیرۆزە',
                'hex_code' => '#40E0D0',
            ],
            [
                'name' => 'Silver',
                'name_ar' => 'فضي',
                'name_kr' => 'زیو',
                'hex_code' => '#C0C0C0',
            ],
            [
                'name' => 'Beige',
                'name_ar' => 'بيج',
                'name_kr' => 'بێژ',
                'hex_code' => '#F5F5DC',
            ],
            [
                'name' => 'Brown',
                'name_ar' => 'بني',
                'name_kr' => 'قاوەیی',
                'hex_code' => '#A52A2A',
            ],
            [
                'name' => 'Bronze',
                'name_ar' => 'برونزي',
                'name_kr' => 'برۆنزی',
                'hex_code' => '#CD7F32',
            ],
            [
                'name' => 'Yellow',
                'name_ar' => 'أصفر',
                'name_kr' => 'زەرد',
                'hex_code' => '#FFFF00',
            ],
            [
                'name' => 'Orange',
                'name_ar' => 'برتقالي',
                'name_kr' => 'پرتەقاڵی',
                'hex_code' => '#FFA500',
            ],
            [
                'name' => 'Burgundy',
                'name_ar' => 'بورجوندي',
                'name_kr' => 'بورجوندی',
                'hex_code' => '#800020',
            ],
            [
                'name' => 'Maroon',
                'name_ar' => 'كستنائي',
                'name_kr' => 'کاستنایی',
                'hex_code' => '#800000',
            ],
            [
                'name' => 'Teal',
                'name_ar' => 'فيروزي فاتح',
                'name_kr' => 'فیرۆزە',
                'hex_code' => '#008080',
            ],
            [
                'name' => 'Tan',
                'name_ar' => 'أسمر',
                'name_kr' => 'تان',
                'hex_code' => '#D2B48C',
            ],
            [
                'name' => 'Pink',
                'name_ar' => 'وردي',
                'name_kr' => 'پەمبەیی',
                'hex_code' => '#FFC0CB',
            ],
        ];

        foreach ($colors as $color) {
            ExteriorColor::updateOrCreate(
                ['name' => $color['name']],
                $color
            );
        }
    }
}
