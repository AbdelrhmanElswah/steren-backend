<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NewsArticle;

class NewsArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $articles = [
            [
                'title' => 'Toyota Brings the Need for Speed to Long Beach',
                'content' => 'From Long Beach to New York City, Toyota\'s two new GR special edition vehicles are making waves in the automotive world. These limited-edition models combine performance and style in a way that only Toyota can deliver.',
                'excerpt' => 'From Long Beach to New York City, Toyota\'s two new GR special edition vehicles....',
                'category' => 'Ford News',
                'author' => 'Automotive News',
                'is_featured' => true,
                'published_at' => now()->subDays(2),
            ],
            [
                'title' => 'Four Reasons Why You Should Consider a Hybrid Vehicle',
                'content' => 'With rising fuel costs and environmental concerns, hybrid vehicles are becoming increasingly popular. Here are four compelling reasons why you should consider making the switch to a hybrid vehicle for your next car purchase.',
                'excerpt' => 'With rising fuel costs and environmental concerns, hybrid vehicles are becoming increasingly popular....',
                'category' => 'Ford News',
                'author' => 'Green Auto Magazine',
                'is_featured' => false,
                'published_at' => now()->subDays(5),
            ],
            [
                'title' => 'Toyota Brightens the City with New Electric Models',
                'content' => 'Toyota is revolutionizing urban transportation with its latest lineup of electric vehicles. These new models are designed to meet the needs of city dwellers while contributing to a cleaner environment.',
                'excerpt' => 'Toyota is revolutionizing urban transportation with its latest lineup of electric vehicles....',
                'category' => 'Ford News',
                'author' => 'Electric Vehicle Weekly',
                'is_featured' => false,
                'published_at' => now()->subDays(8),
            ],
            [
                'title' => 'The Future of Autonomous Driving: What to Expect',
                'content' => 'Autonomous driving technology is advancing rapidly, and major manufacturers are investing heavily in this revolutionary technology. Learn about the latest developments and what the future holds for self-driving cars.',
                'excerpt' => 'Autonomous driving technology is advancing rapidly, and major manufacturers are investing heavily....',
                'category' => 'Ford News',
                'author' => 'Tech Auto Review',
                'is_featured' => true,
                'published_at' => now()->subDays(10),
            ],
            [
                'title' => 'SUV Market Trends: What\'s Driving the Popularity',
                'content' => 'SUVs continue to dominate the automotive market, with consumers preferring their versatility and safety features. Explore the latest trends and see which models are leading the pack in this competitive segment.',
                'excerpt' => 'SUVs continue to dominate the automotive market, with consumers preferring their versatility....',
                'category' => 'Ford News',
                'author' => 'Market Analysis Today',
                'is_featured' => false,
                'published_at' => now()->subDays(12),
            ],
        ];

        foreach ($articles as $article) {
            NewsArticle::updateOrCreate(
                ['title' => $article['title']],
                $article
            );
        }
    }
}
