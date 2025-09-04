<?php
namespace Gokulsingh\LaravelPayhub\Support;
abstract class BaseNormalizer
{
    protected function normalize(array $response, array $map): array
    {
        return [
            'id' => $response[$map['id']] ?? null,
            'type' => $map['type'] ?? 'generic',
            'amount' => (int) ($response[$map['amount']] ?? 0),
            'currency' => $response[$map['currency']] ?? 'INR',
            'status' => $this->mapStatus((string)($response[$map['status']] ?? 'pending')),
            'gateway' => $map['gateway'] ?? 'unknown',
            'raw' => $response,
            'metadata' => $this->extractCustomFields($response, $map['custom'] ?? []) ,
        ];
    }
    protected function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'succeeded','success','paid','captured','settled','processed' => 'success',
            'pending','processing','created','open' => 'pending',
            'failed','declined','canceled','error' => 'failed',
            'refunded','partial_refund','partially_refunded' => 'refunded',
            default => 'pending',
        };
    }
    protected function extractCustomFields(array $response, array $customKeys): array
    {
        $custom = [];
        foreach ($customKeys as $k=>$v){
            if (is_numeric($k)){ $custom[$v] = $response[$v] ?? null; }
            else { $custom[$k] = $response[$v] ?? null; }
        }
        return $custom;
    }
}

