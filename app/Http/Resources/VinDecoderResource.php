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
                        ],
                        'wheels' => $this->buildWheelsData($vehicleData),
                        'tires' => $this->buildTiresData($vehicleData),
                        'exterior_colors' => $this->buildExteriorColorsData($vehicleData),
                        'interior_colors' => $this->buildInteriorColorsData($vehicleData)
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
    
    /**
     * Build wheels data from vehicle data
     */
    private function buildWheelsData($vehicleData)
    {
        $wheelsData = $vehicleData['wheels'] ?? [];
        
        return [
            'rear_wheel_material' => $wheelsData['rear_wheel_material'] ?? '',
            'front_wheel_material' => $wheelsData['front_wheel_material'] ?? '',
            'spare_wheel_material' => $wheelsData['spare_wheel_material'] ?? ''
        ];
    }
    
    /**
     * Build tires data from vehicle data
     */
    private function buildTiresData($vehicleData)
    {
        $tiresData = $vehicleData['tires'] ?? [];
        
        return [
            'type' => $tiresData['type'] ?? '',
            'rear_tire_size' => $tiresData['rear_tire_size'] ?? '',
            'front_tire_size' => $tiresData['front_tire_size'] ?? '',
            'spare_tire_size' => $tiresData['spare_tire_size'] ?? ''
        ];
    }
    
    /**
     * Build exterior colors data from vehicle data
     */
    private function buildExteriorColorsData($vehicleData)
    {
        $exteriorColors = $vehicleData['exterior_colors'] ?? [];
        $result = [];
        
        foreach ($exteriorColors as $color) {
            $result[] = [
                'hex_value' => $color['hex_value'] ?? '',
                'rgb_value' => $color['rgb_value'] ?? '',
                'color_code' => $color['color_code'] ?? '',
                'color_type' => $color['color_type'] ?? '',
                'color_price' => $color['color_price'] ?? '',
                'description' => $color['description'] ?? '',
                'generic_name' => $color['generic_name'] ?? '',
                'color_invoice' => $color['color_invoice'] ?? ''
            ];
        }
        
        return $result;
    }
    
    /**
     * Build interior colors data from vehicle data
     */
    private function buildInteriorColorsData($vehicleData)
    {
        $interiorColors = $vehicleData['interior_colors'] ?? [];
        $result = [];
        
        foreach ($interiorColors as $color) {
            $result[] = [
                'hex_value' => $color['hex_value'] ?? '',
                'rgb_value' => $color['rgb_value'] ?? '',
                'color_code' => $color['color_code'] ?? '',
                'color_type' => $color['color_type'] ?? '',
                'color_price' => $color['color_price'] ?? '',
                'description' => $color['description'] ?? '',
                'generic_name' => $color['generic_name'] ?? '',
                'color_invoice' => $color['color_invoice'] ?? ''
            ];
        }
        
        return $result;
    }
}
