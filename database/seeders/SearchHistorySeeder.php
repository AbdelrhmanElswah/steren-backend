<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SearchHistory;
use App\Models\User;

class SearchHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::take(5)->get();

        foreach ($users as $user) {
            // Create some sample search history
            $searches = [
                [
                    'title' => 'Mazda rx7',
                    'search_query' => 'Mazda rx7',
                    'filters' => [
                        'exterior_color' => 1, // Blue
                        'year' => '2018',
                        'condition_type' => 'used',
                        'body_type' => 1, // Coupe
                        'transmission' => 1, // Automatic
                        'drive_type' => 1, // Sport
                    ],
                    'sort_options' => ['sort' => 'latest'],
                    'is_saved' => true,
                    'notifications_enabled' => true,
                    'search_count' => 3,
                ],
                [
                    'title' => 'BMW M3',
                    'search_query' => 'BMW M3',
                    'filters' => [
                        'exterior_color' => 2, // Black
                        'year' => '2020',
                        'condition_type' => 'used',
                        'body_type' => 2, // Sedan
                        'transmission' => 1, // Automatic
                    ],
                    'sort_options' => ['sort' => 'price_low'],
                    'is_saved' => true,
                    'notifications_enabled' => false,
                    'search_count' => 5,
                ],
                [
                    'title' => 'Toyota Camry',
                    'search_query' => 'Toyota Camry',
                    'filters' => [
                        'condition_type' => 'new',
                        'body_type' => 2, // Sedan
                        'transmission' => 1, // Automatic
                    ],
                    'sort_options' => ['sort' => 'year_high'],
                    'is_saved' => false,
                    'notifications_enabled' => false,
                    'search_count' => 1,
                ],
            ];

            foreach ($searches as $search) {
                SearchHistory::create(array_merge($search, [
                    'user_id' => $user->id,
                    'last_searched_at' => now()->subDays(rand(1, 30)),
                ]));
            }
        }
    }
}
