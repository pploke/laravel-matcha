<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RajaOngkirService
{
    private string $baseUrl;
    private string $apiKey;
    private string $originId;
    private int $cacheTtl;

    public function __construct()
    {
        $config = config('services.rajaongkir', []);
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://rajaongkir.komerce.id/api/v1'), '/');
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->originId = (string) ($config['origin_id'] ?? '');
        $this->cacheTtl = max(60, (int) ($config['cache_ttl'] ?? 600));
    }

    /**
     * 4.3.4 Pencarian tujuan domestik dengan cache 10 menit (600 detik).
     */
    public function searchDestination(string $query): array
    {
        $cleanQuery = trim($query);
        $cacheKey = 'rajaongkir:dest:' . md5(Str::lower($cleanQuery));

        return Cache::remember($cacheKey, 600, function () use ($cleanQuery) {
            $response = Http::withHeaders([
                'key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout(8)
                ->retry(2, 200)
                ->get($this->baseUrl . '/destination/domestic-destination', [
                    'search' => $cleanQuery,
                    'limit' => 15,
                    'offset' => 0,
                ]);

            $response->throw();

            $payload = $response->json();
            $items = data_get($payload, 'data', []);
            if (!is_array($items)) {
                $items = [];
            }

            return array_values(array_map(function ($row) {
                $id = (string) ($row['id'] ?? $row['destination_id'] ?? '');
                $subdistrict = $row['subdistrict_name'] ?? $row['district_name'] ?? '';
                $city = $row['city_name'] ?? '';
                $province = $row['province_name'] ?? '';
                $zipCode = $row['zip_code'] ?? $row['postal_code'] ?? '';

                $label = $row['label']
                    ?? trim(implode(', ', array_filter([$subdistrict, $city, $province])), ', ');

                return [
                    'id' => $id,
                    'label' => $label,
                    'subdistrict_name' => $subdistrict,
                    'city_name' => $city,
                    'province_name' => $province,
                    'zip_code' => $zipCode,
                ];
            }, $items));
        });
    }

    /**
     * 4.3.5 Perhitungan ongkir dengan normalisasi, timeout 8s + retry 1x, dan cache 5-10 menit.
     */
    public function getRates(string $destinationId, int $weightGrams, array $couriers = ['jne', 'jnt', 'sicepat']): array
    {
        $cleanDest = trim($destinationId);
        $safeWeight = max(1, $weightGrams);
        $normalizedCouriers = array_values(array_unique(array_filter(array_map(
            fn($c) => Str::lower(trim((string) $c)),
            $couriers
        ))));

        if (empty($normalizedCouriers)) {
            $normalizedCouriers = ['jne', 'jnt', 'sicepat'];
        }

        sort($normalizedCouriers);
        $courierString = implode(':', $normalizedCouriers);
        $ttl = min(600, max(300, $this->cacheTtl));
        $cacheKey = "rajaongkir:rates:{$this->originId}:{$cleanDest}:{$safeWeight}:{$courierString}";

        return Cache::remember($cacheKey, $ttl, function () use ($cleanDest, $safeWeight, $courierString) {
            $response = Http::asForm()
                ->withHeaders([
                    'key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->timeout(8)
                ->retry(2, 200)
                ->post($this->baseUrl . '/calculate/domestic-cost', [
                    'origin' => $this->originId,
                    'destination' => $cleanDest,
                    'weight' => $safeWeight,
                    'courier' => $courierString,
                ]);

            $response->throw();

            $payload = $response->json();
            $rawRates = data_get($payload, 'data', []);
            if (!is_array($rawRates)) {
                $rawRates = [];
            }

            $normalized = [];
            foreach ($rawRates as $item) {
                $courierCode = Str::lower((string) ($item['code'] ?? $item['courier_code'] ?? ''));
                $courierName = (string) ($item['name'] ?? $item['courier_name'] ?? strtoupper($courierCode));
                $serviceCode = (string) ($item['service'] ?? $item['service_code'] ?? '');
                $serviceName = (string) ($item['description'] ?? $item['service_name'] ?? $serviceCode);
                $cost = (int) ($item['cost'] ?? $item['value'] ?? 0);
                $etd = (string) ($item['etd'] ?? '');

                $normalized[] = [
                    'courier' => $courierCode,
                    'courier_name' => $courierName,
                    'service' => $serviceCode,
                    'service_name' => $serviceName,
                    'cost' => $cost,
                    'etd' => $etd,
                ];
            }

            return $normalized;
        });
    }
}
