<?php

namespace MertAydin\LaravelQueueMonitor\Supervisor;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

final class SupervisorClient
{
    public function __construct(private readonly array $configuration) {}

    public function processes(): array
    {
        $processes = $this->call('supervisor.getAllProcessInfo');

        if (is_array($processes) === false) {
            throw new RuntimeException('Supervisor returned an invalid process list.');
        }

        return $processes;
    }

    public function start(string $process): void
    {
        $this->call('supervisor.startProcess', [$process, true]);
    }

    public function stop(string $process): void
    {
        $this->call('supervisor.stopProcess', [$process, true]);
    }

    private function call(string $method, array $arguments = []): mixed
    {
        $endpoint = (string) ($this->configuration['url'] ?? '');

        if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('The configured Supervisor URL is invalid.');
        }

        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https'
            && ($this->configuration['allow_insecure_http'] ?? false) === false) {
            throw new RuntimeException('Supervisor HTTP access is disabled. Use HTTPS or explicitly allow trusted local HTTP.');
        }

        $body = '<?xml version="1.0"?>'
            .'<methodCall><methodName>'.htmlspecialchars($method, ENT_XML1).'</methodName><params>';

        foreach ($arguments as $argument) {
            $body .= '<param><value>'.$this->encodeValue($argument).'</value></param>';
        }

        $body .= '</params></methodCall>';

        try {
            $request = Http::timeout(max(1, (int) ($this->configuration['timeout'] ?? 5)))
                ->withHeaders(['Content-Type' => 'text/xml'])
                ->withOptions(['verify' => (bool) ($this->configuration['verify_tls'] ?? true)]);

            if (empty($this->configuration['username']) === false) {
                $request = $request->withBasicAuth(
                    (string) $this->configuration['username'],
                    (string) ($this->configuration['password'] ?? '')
                );
            }

            $response = $request->post($endpoint, $body);

            if ($response->successful() === false) {
                $responseBody = trim(strip_tags($response->body()));

                throw new RuntimeException(sprintf(
                    'Supervisor returned HTTP %d: %s',
                    $response->status(),
                    mb_substr($responseBody, 0, 400)
                ));
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not communicate with the configured Supervisor server.', previous: $exception);
        }

        return $this->decodeResponse($response->body());
    }

    private function encodeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return '<boolean>'.($value ? '1' : '0').'</boolean>';
        }

        if (is_int($value)) {
            return '<int>'.$value.'</int>';
        }

        return '<string>'.htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</string>';
    }

    private function decodeResponse(string $xml): mixed
    {
        if (function_exists('simplexml_load_string') === false) {
            throw new RuntimeException('The PHP SimpleXML extension is required for Supervisor integration.');
        }

        if (str_contains($xml, '<!DOCTYPE') || str_contains($xml, '<!ENTITY')) {
            throw new RuntimeException('Supervisor returned an unsafe XML response.');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);

            if ($document === false) {
                throw new RuntimeException('Supervisor returned malformed XML.');
            }

            if (isset($document->fault->value)) {
                $fault = $this->decodeValue($document->fault->value);
                throw new RuntimeException((string) ($fault['faultString'] ?? 'Supervisor reported an XML-RPC fault.'));
            }

            if (isset($document->params->param->value) === false) {
                throw new RuntimeException('Supervisor returned an empty XML-RPC response.');
            }

            return $this->decodeValue($document->params->param->value);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function decodeValue(SimpleXMLElement $value): mixed
    {
        if (count($value->children()) === 0) {
            return (string) $value;
        }

        $type = $value->children()[0];
        $name = $type->getName();

        return match ($name) {
            'string', 'dateTime.iso8601' => (string) $type,
            'int', 'i4' => (int) $type,
            'boolean' => (string) $type === '1',
            'double' => (float) $type,
            'nil' => null,
            'array' => $this->decodeArray($type),
            'struct' => $this->decodeStruct($type),
            default => (string) $type,
        };
    }

    private function decodeArray(SimpleXMLElement $array): array
    {
        $items = [];

        foreach ($array->data->value as $value) {
            $items[] = $this->decodeValue($value);
        }

        return $items;
    }

    private function decodeStruct(SimpleXMLElement $struct): array
    {
        $result = [];

        foreach ($struct->member as $member) {
            $result[(string) $member->name] = $this->decodeValue($member->value);
        }

        return $result;
    }
}
