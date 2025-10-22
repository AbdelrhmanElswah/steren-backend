<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VinDecoderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get additional data if available
        $additionalData = $this->additional_data ?? [];
        
        // Extract vehicle data from additional_data
        $vehicleData = $additionalData['data'] ?? [];
        
        // Build the filtered response structure
        $response = [
            'data' => [
                'vin' => $this->vin,
                'additional_data' => [
                    'data' => [
                        'vin' => $this->vin,
                        'make' => $this->make,
                        'trim' => $this->trim,
                        'year' => $this->year,
                        'model' => $this->model,
                        'price' => $vehicleData['price'] ?? null,
                        'style' => $vehicleData['style'] ?? null,
                        'vehicle' => [
                            'doors' => $this->doors,
                            'body_type' => $this->body_type,
                            'rear_door_type' => $vehicleData['vehicle']['rear_door_type'] ?? '',
                            'epa_classification' => $vehicleData['vehicle']['epa_classification'] ?? ''
                        ],
                        'fuel' => [
                            'type' => $this->fuel_type,
                            'transmission' => [
                                'code' => $vehicleData['transmission']['code'] ?? '',
                                'type' => $this->transmission,
                                'engine' => $this->buildEngineData($vehicleData),
                                'fuel' => $this->buildFuelData($vehicleData)
                            ]
                        ],
                        'seating' => [
                            'number_of_seats' => $vehicleData['seating']['number_of_seats'] ?? $this->doors,
                            'max_seating_capacity' => $vehicleData['seating']['max_seating_capacity'] ?? $this->doors
                        ]
                    ]
                ]
            ]
        ];
        
        return $response;
    }
    
    /**
     * Build engine data from vehicle data
     */
    private function buildEngineData($vehicleData)
    {
        $engineData = $vehicleData['engine'] ?? [];
        $result = [];
        
        // Engine size
        if (isset($engineData['engine_size'])) {
            $result[] = [
                'engine_size' => [
                    [
                        'unit' => 'l',
                        'value' => $engineData['engine_size'] ?? ''
                    ]
                ]
            ];
        }
        
        // Horsepower
        if (isset($engineData['horsepower'])) {
            $result[] = [
                'horsepower' => [
                    [
                        'unit' => 'rpm',
                        'value' => $engineData['horsepower']['rpm'] ?? ''
                    ],
                    [
                        'unit' => 'hp',
                        'value' => $engineData['horsepower']['hp'] ?? ''
                    ]
                ]
            ];
        }
        
        // Torque
        if (isset($engineData['torque'])) {
            $result[] = [
                'torque' => [
                    [
                        'unit' => 'rpm',
                        'value' => $engineData['torque']['rpm'] ?? ''
                    ],
                    [
                        'unit' => 'lb.ft',
                        'value' => $engineData['torque']['lb_ft'] ?? ''
                    ]
                ]
            ];
        }
        
        // Bore
        if (isset($engineData['bore'])) {
            $result[] = [
                'bore' => [
                    [
                        'unit' => 'mm',
                        'value' => $engineData['bore']['mm'] ?? ''
                    ],
                    [
                        'unit' => 'in',
                        'value' => $engineData['bore']['in'] ?? ''
                    ]
                ]
            ];
        }
        
        // Stroke
        if (isset($engineData['stroke'])) {
            $result[] = [
                'stroke' => [
                    [
                        'unit' => 'mm',
                        'value' => $engineData['stroke']['mm'] ?? ''
                    ],
                    [
                        'unit' => 'in',
                        'value' => $engineData['stroke']['in'] ?? ''
                    ]
                ]
            ];
        }
        
        return $result;
    }
    
    /**
     * Build fuel data from vehicle data
     */
    private function buildFuelData($vehicleData)
    {
        $fuelData = $vehicleData['fuel'] ?? [];
        $result = [];
        
        // Tank capacity
        if (isset($fuelData['tank_capacity'])) {
            $result[] = [
                'tank_capacity' => [
                    [
                        'unit' => 'gal',
                        'value' => $fuelData['tank_capacity']['gal'] ?? ''
                    ],
                    [
                        'unit' => 'l',
                        'value' => $fuelData['tank_capacity']['l'] ?? ''
                    ]
                ]
            ];
        }
        
        return $result;
    }
}
