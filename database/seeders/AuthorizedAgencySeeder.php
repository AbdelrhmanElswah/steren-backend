<?php

namespace Database\Seeders;

use App\Models\AuthorizedAgency;
use App\Models\Media;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthorizedAgencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Fetching authorized agencies from Odoo API...');

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'PostmanRuntime/7.48.0',
            ])->post(config('services.odoo.base_url') . '/api/authorized-agencies', [
                'offset' => 0,
                'limit' => 100,
            ]);

            if (!$response->successful()) {
                $this->command->error('Failed to fetch authorized agencies. Status: ' . $response->status());
                Log::error('AuthorizedAgencySeeder: API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return;
            }

            $payload = $response->json();

            if (!isset($payload['result']['data']) || !is_array($payload['result']['data'])) {
                $this->command->error('Invalid response format from Odoo API');
                Log::error('AuthorizedAgencySeeder: Invalid response structure', ['response' => $payload]);
                return;
            }

            $agencies = $payload['result']['data'];
            $this->command->info('Found ' . count($agencies) . ' authorized agencies to process');

            foreach ($agencies as $agency) {
                $name = $agency['name'] ?? 'Unknown';
                $odooId = $agency['id'] ?? null;

                if (!$odooId) {
                    $this->command->warn('Skipping agency without Odoo ID: ' . $name);
                    continue;
                }

                $this->command->info('Processing agency: ' . $name);

                $authorizedAgency = AuthorizedAgency::updateOrCreate(
                    ['odoo_id' => $odooId],
                    [
                        'name' => $name,
                        'partner_id' => $agency['partner_id'] ?? null,
                        'odoo_id' => $odooId,
                    ]
                );

                if (!empty($agency['media']) && is_string($agency['media'])) {
                    $this->handleAgencyMedia($authorizedAgency, $agency['media']);
                } else {
                    $authorizedAgency->media()?->delete();
                    $this->command->info('No media found for agency: ' . $name);
                }
            }

            $this->command->info('Authorized agencies seeded successfully!');
        } catch (\Throwable $e) {
            $this->command->error('Error fetching authorized agencies: ' . $e->getMessage());
            Log::error('AuthorizedAgencySeeder: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function handleAgencyMedia(AuthorizedAgency $agency, string $base64Media): void
    {
        try {
            $agency->media()?->delete();

            $clean = trim($base64Media);
            if ($clean === '') {
                $this->command->warn('Empty media string for agency: ' . $agency->name);
                return;
            }

            if (str_contains($clean, ',')) {
                $clean = substr($clean, strpos($clean, ',') + 1);
            }

            $binary = base64_decode($clean, true);
            if ($binary === false || $binary === '') {
                $this->command->warn('Invalid base64 media for agency: ' . $agency->name);
                return;
            }

            $filename = 'authorized_agency_' . $agency->id . '_' . Str::slug($agency->name) . '_' . time() . '.png';
            $path = 'authorized_agencies/' . $filename;

            if (!Storage::disk('public')->put($path, $binary)) {
                $this->command->warn('Failed to store media for agency: ' . $agency->name);
                return;
            }

            $media = new Media([
                'media' => $path,
                'path_file_name' => $path,
                'file_type' => 'image/png',
                'info' => 'authorized_agency_media',
            ]);

            $agency->media()->save($media);
            $this->command->info('Stored media for agency: ' . $agency->name);
        } catch (\Throwable $e) {
            $this->command->error('Error handling media for agency ' . $agency->name . ': ' . $e->getMessage());
            Log::error('AuthorizedAgencySeeder: Media handling error', [
                'agency_id' => $agency->id,
                'agency_name' => $agency->name,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

