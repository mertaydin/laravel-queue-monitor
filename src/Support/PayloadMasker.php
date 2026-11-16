<?php

namespace MertAydin\LaravelQueueMonitor\Support;

final class PayloadMasker
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $sensitiveKeys
     * @return array<string, mixed>
     */
    public function mask(array $payload, array $sensitiveKeys, string $replacement = '[REDACTED]'): array
    {
        $normalizedKeys = array_map('strtolower', $sensitiveKeys);

        return $this->maskArray($payload, $normalizedKeys, $replacement);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  list<string>  $sensitiveKeys
     * @return array<array-key, mixed>
     */
    private function maskArray(array $values, array $sensitiveKeys, string $replacement): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $values[$key] = $replacement;

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->maskArray($value, $sensitiveKeys, $replacement);
            }
        }

        return $values;
    }
}
