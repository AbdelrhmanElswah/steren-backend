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
            ],
            [
                'name' => 'Black',
                'name_ar' => 'أسود',
                'name_kr' => 'ڕەش',
            ],
            [
                'name' => 'Red',
                'name_ar' => 'أحمر',
                'name_kr' => 'سوور',
            ],
            [
                'name' => 'Grey',
                'name_ar' => 'رمادي',
                'name_kr' => 'خۆڵەمێشی',
            ],
            [
                'name' => 'Blue',
                'name_ar' => 'أزرق',
                'name_kr' => 'شین',
            ],
            [
                'name' => 'Green',
                'name_ar' => 'أخضر',
                'name_kr' => 'سەوز',
            ],
            [
                'name' => 'Purple',
                'name_ar' => 'بنفسجي',
                'name_kr' => 'مۆر',
            ],
            [
                'name' => 'Other',
                'name_ar' => 'أخرى',
                'name_kr' => 'دیکە',
            ],
            [
                'name' => 'Gold',
                'name_ar' => 'ذهبي',
                'name_kr' => 'زێڕین',
            ],
            [
                'name' => 'Turquoise',
                'name_ar' => 'فيروزي',
                'name_kr' => 'فیرۆزە',
            ],
            [
                'name' => 'Silver',
                'name_ar' => 'فضي',
                'name_kr' => 'زیو',
            ],
            [
                'name' => 'Beige',
                'name_ar' => 'بيج',
                'name_kr' => 'بێژ',
            ],
            [
                'name' => 'Brown',
                'name_ar' => 'بني',
                'name_kr' => 'قاوەیی',
            ],
            [
                'name' => 'Bronze',
                'name_ar' => 'برونزي',
                'name_kr' => 'برۆنزی',
            ],
            [
                'name' => 'Yellow',
                'name_ar' => 'أصفر',
                'name_kr' => 'زەرد',
            ],
            [
                'name' => 'Orange',
                'name_ar' => 'برتقالي',
                'name_kr' => 'پرتەقاڵی',
            ],
            [
                'name' => 'Burgundy',
                'name_ar' => 'بورجوندي',
                'name_kr' => 'بورجوندی',
            ],
            [
                'name' => 'Maroon',
                'name_ar' => 'كستنائي',
                'name_kr' => 'کاستنایی',
            ],
            [
                'name' => 'Teal',
                'name_ar' => 'فيروزي فاتح',
                'name_kr' => 'فیرۆزە',
            ],
            [
                'name' => 'Tan',
                'name_ar' => 'أسمر',
                'name_kr' => 'تان',
            ],
            [
                'name' => 'Pink',
                'name_ar' => 'وردي',
                'name_kr' => 'پەمبەیی',
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
