<?php

declare(strict_types=1);

namespace App\Modules\Status\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Status\Services\AlexaRequestVerifier;
use App\Modules\Status\Services\StatusService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Self-hosted Alexa custom-skill endpoint.
 *
 * Alexa (Amazon cloud) POSTs the user's utterance here. Authentication is by
 * Amazon request signature + skill id, NOT by JWT/API key — hence this route is
 * mounted OUTSIDE the auth-protected group. Every request is verified before
 * any data is read.
 *
 * This increment handles READ intents only (status queries). Control intents
 * (start/stop/restart with spoken confirmation) are added in a later step.
 */
class AlexaController
{
    public function __construct(
        private readonly StatusService $statusService,
        private readonly AlexaRequestVerifier $verifier
    ) {}

    public function webhook(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // --- 1. Read the RAW body (signature is over the exact bytes) ---
        $body = $request->getBody();
        $body->rewind();
        $raw = $body->getContents();

        $certUrl = $request->getHeaderLine('SignatureCertChainUrl');
        $signature = $request->getHeaderLine('Signature-256');
        if ($signature === '') {
            $signature = $request->getHeaderLine('Signature');
        }

        // --- 2. Verify the request comes from Amazon (skippable only in dev) ---
        if (!$this->verificationDisabled()) {
            $result = $this->verifier->verify($raw, $certUrl, $signature);
            if (!$result['ok']) {
                return $this->plainError($response, 'Signature verification failed: ' . $result['error']);
            }
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return $this->plainError($response, 'Malformed request body');
        }

        // --- 3. Timestamp freshness (replay protection) ---
        $timestamp = $payload['request']['timestamp'] ?? null;
        if (!$this->verificationDisabled() && !$this->verifier->isTimestampFresh($timestamp)) {
            return $this->plainError($response, 'Stale request timestamp');
        }

        // --- 4. Skill id must match the configured skill ---
        $appId = $payload['session']['application']['applicationId']
            ?? $payload['context']['System']['application']['applicationId']
            ?? '';
        $expectedAppId = (string) ($_ENV['ALEXA_SKILL_ID'] ?? '');
        if ($expectedAppId !== '' && !hash_equals($expectedAppId, (string) $appId)) {
            return $this->plainError($response, 'Unexpected skill id');
        }

        // --- 5. Route the request ---
        $supportsApl = $this->deviceSupportsApl($payload);
        $type = $payload['request']['type'] ?? '';

        try {
            $out = match ($type) {
                'LaunchRequest' => $this->handleLaunch($supportsApl),
                'IntentRequest' => $this->handleIntent($payload, $supportsApl),
                'SessionEndedRequest' => $this->tell('Bis bald!', null, true),
                default => $this->tell('Das habe ich nicht verstanden.', null, false),
            };
        } catch (\Throwable $e) {
            $out = $this->tell('Beim Abrufen der Serverdaten ist ein Fehler aufgetreten.', null, true);
        }

        return JsonResponse::create($out, 200);
    }

    // ------------------------------------------------------------------
    // Intent handling (read-only)
    // ------------------------------------------------------------------

    private function handleLaunch(bool $apl): array
    {
        $ov = $this->statusService->getOverview($this->scopeUserId());
        $speech = $this->summarySpeech($ov, true);
        return $this->tell($speech, $apl ? $this->overviewApl($ov) : null, false, 'Frag mich zum Beispiel: Wie voll ist die Festplatte?');
    }

    private function handleIntent(array $payload, bool $apl): array
    {
        $intent = $payload['request']['intent']['name'] ?? '';

        return match ($intent) {
            'ServerStatusIntent', 'OverviewIntent' => (function () use ($apl) {
                $ov = $this->statusService->getOverview($this->scopeUserId());
                return $this->tell($this->summarySpeech($ov, false), $apl ? $this->overviewApl($ov) : null, true);
            })(),
            'ContainerStatusIntent' => (function () use ($payload) {
                return $this->tell($this->containerSpeech($payload), null, true);
            })(),
            'DiskStatusIntent' => (function () {
                return $this->tell($this->diskSpeech(), null, true);
            })(),
            'ServicesStatusIntent' => (function () {
                return $this->tell($this->servicesSpeech(), null, true);
            })(),
            'AMAZON.HelpIntent' => $this->tell(
                'Ich kann dir den Zustand deines Servers und deiner Docker-Container nennen. ' .
                'Frag zum Beispiel: Wie geht es dem Server? Oder: Laufen alle Container?',
                null,
                false
            ),
            'AMAZON.StopIntent', 'AMAZON.CancelIntent' => $this->tell('Okay.', null, true),
            default => $this->tell('Diese Frage kenne ich noch nicht.', null, false),
        };
    }

    // ------------------------------------------------------------------
    // Speech builders
    // ------------------------------------------------------------------

    private function summarySpeech(array $ov, bool $greeting): string
    {
        $s = $ov['server'];
        $d = $ov['docker']['summary'];
        $parts = [];
        if ($greeting) {
            $parts[] = 'Hallo!';
        }

        $parts[] = sprintf(
            'Die CPU-Auslastung liegt bei %s Prozent, der Arbeitsspeicher bei %s Prozent.',
            $this->num($s['cpu']['percent']),
            $this->num($s['memory']['percent'])
        );

        if ($ov['docker']['available']) {
            $unhealthy = $d['unhealthy'] > 0
                ? sprintf(', %d %s ungesund', $d['unhealthy'], $this->plural((int) $d['unhealthy'], 'ist', 'sind'))
                : '';
            $parts[] = sprintf('%d von %d Containern laufen%s.', $d['running'], $d['total'], $unhealthy);
        }

        $alerts = $ov['alerts'];
        if (empty($alerts)) {
            $parts[] = 'Es gibt keine Probleme.';
        } else {
            $parts[] = 'Achtung: ' . implode('. ', array_map(fn($a) => $a['message'], array_slice($alerts, 0, 3))) . '.';
        }

        return implode(' ', $parts);
    }

    private function containerSpeech(array $payload): string
    {
        $data = $this->statusService->getContainers();
        if (!$data['available']) {
            return 'Docker ist gerade nicht erreichbar.';
        }

        // Optional container-name slot
        $name = $this->slotValue($payload, 'container');
        if ($name !== null) {
            foreach ($data['containers'] as $c) {
                if (strcasecmp($c['name'], $name) === 0) {
                    $state = $c['state'] === 'running' ? 'läuft' : 'ist gestoppt';
                    $load = $c['cpu_percent'] !== null
                        ? sprintf(' CPU %s Prozent, RAM %s Prozent.', $this->num($c['cpu_percent']), $this->num($c['mem_percent']))
                        : '';
                    return "Der Container {$c['name']} {$state}.{$load}";
                }
            }
            return "Einen Container mit dem Namen {$name} habe ich nicht gefunden.";
        }

        $sum = $data['summary'];
        $speech = sprintf('%d von %d Containern laufen.', $sum['running'], $sum['total']);
        if ($sum['stopped'] > 0) {
            $stopped = array_values(array_filter($data['containers'], fn($c) => $c['state'] !== 'running'));
            $names = implode(', ', array_map(fn($c) => $c['name'], array_slice($stopped, 0, 5)));
            $speech .= " Gestoppt: {$names}.";
        }
        if ($sum['unhealthy'] > 0) {
            $speech .= sprintf(
                ' %d %s ungesund.',
                $sum['unhealthy'],
                $this->plural((int) $sum['unhealthy'], 'Container ist', 'Container sind')
            );
        }
        return $speech;
    }

    private function diskSpeech(): string
    {
        $server = $this->statusService->getServer();
        $lines = [];
        foreach ($server['disks'] as $disk) {
            $lines[] = sprintf('%s ist zu %s Prozent belegt', $disk['mount'], $this->num($disk['percent']));
        }
        if (empty($lines)) {
            return 'Ich konnte keine Festplatten auslesen.';
        }
        return implode('. ', $lines) . '.';
    }

    private function servicesSpeech(): string
    {
        $svc = $this->statusService->getServices($this->scopeUserId());
        $u = $svc['uptime']['summary'];
        $ssl = $svc['ssl']['summary'];
        $cron = $svc['cron']['summary'];

        $parts = [];
        $parts[] = $u['down'] === 0
            ? sprintf('Alle %d überwachten Dienste sind online.', $u['total'])
            : sprintf(
                '%d %s offline: %s.',
                $u['down'],
                $this->plural((int) $u['down'], 'Dienst ist', 'Dienste sind'),
                implode(', ', $u['down_names'])
            );

        $sslProblem = $ssl['expiring_soon'] + $ssl['expired'];
        if ($sslProblem > 0) {
            $parts[] = sprintf(
                '%d %s Aufmerksamkeit.',
                $sslProblem,
                $this->plural($sslProblem, 'SSL-Zertifikat braucht', 'SSL-Zertifikate brauchen')
            );
        }
        if ($cron['failed'] > 0) {
            $parts[] = sprintf(
                '%d %s fehlgeschlagen.',
                $cron['failed'],
                $this->plural((int) $cron['failed'], 'Cron-Job ist', 'Cron-Jobs sind')
            );
        }
        return implode(' ', $parts);
    }

    // ------------------------------------------------------------------
    // Alexa response envelope + APL (Show 8 display)
    // ------------------------------------------------------------------

    /**
     * Build an Alexa response. When $reprompt is null the session ends.
     */
    private function tell(string $speech, ?array $aplDirective, bool $endSession, ?string $reprompt = null): array
    {
        $response = [
            'outputSpeech' => ['type' => 'SSML', 'ssml' => '<speak>' . $this->escapeSsml($speech) . '</speak>'],
            'shouldEndSession' => $endSession,
        ];

        if ($reprompt !== null) {
            $response['reprompt'] = [
                'outputSpeech' => ['type' => 'SSML', 'ssml' => '<speak>' . $this->escapeSsml($reprompt) . '</speak>'],
            ];
            $response['shouldEndSession'] = false;
        }

        if ($aplDirective !== null) {
            $response['directives'] = [$aplDirective];
        }

        return ['version' => '1.0', 'response' => $response];
    }

    /**
     * Minimal APL document rendering the overview as headline + stat lines for
     * screen devices such as the Echo Show 8.
     */
    private function overviewApl(array $ov): array
    {
        $s = $ov['server'];
        $d = $ov['docker']['summary'];

        $lines = [
            sprintf('CPU: %s %%   RAM: %s %%', $this->num($s['cpu']['percent']), $this->num($s['memory']['percent'])),
            sprintf('Container: %d/%d aktiv%s', $d['running'], $d['total'], $d['unhealthy'] > 0 ? "  ({$d['unhealthy']} ungesund)" : ''),
            'Uptime: ' . $s['uptime'],
        ];
        foreach (array_slice($ov['alerts'], 0, 3) as $a) {
            $lines[] = '⚠ ' . $a['message'];
        }

        $textItems = array_map(fn($t) => [
            'type' => 'Text',
            'text' => $this->escapeSsml($t),
            'fontSize' => '28dp',
            'paddingBottom' => '8dp',
        ], $lines);

        return [
            'type' => 'Alexa.Presentation.APL.RenderDocument',
            'token' => 'kyuubiStatus',
            'document' => [
                'type' => 'APL',
                'version' => '2022.1',
                'mainTemplate' => [
                    'items' => [[
                        'type' => 'Container',
                        'width' => '100vw',
                        'height' => '100vh',
                        'paddingLeft' => '48dp',
                        'paddingTop' => '40dp',
                        'items' => array_merge([[
                            'type' => 'Text',
                            'text' => 'KyuubiSoft — Serverstatus',
                            'fontSize' => '38dp',
                            'fontWeight' => '700',
                            'paddingBottom' => '20dp',
                        ]], $textItems),
                    ]],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function scopeUserId(): ?string
    {
        $id = (string) ($_ENV['ALEXA_USER_ID'] ?? '');
        return $id !== '' ? $id : null;
    }

    private function deviceSupportsApl(array $payload): bool
    {
        return isset($payload['context']['System']['device']['supportedInterfaces']['Alexa.Presentation.APL']);
    }

    private function slotValue(array $payload, string $slot): ?string
    {
        $value = $payload['request']['intent']['slots'][$slot]['value'] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function num(float|int|null $v): string
    {
        return number_format((float) $v, 0, ',', '.');
    }

    private function plural(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? $singular : $plural;
    }

    private function escapeSsml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Verification may be disabled ONLY outside production and only when the
     * operator explicitly opts in — for local skill testing.
     */
    private function verificationDisabled(): bool
    {
        $env = strtolower((string) ($_ENV['APP_ENV'] ?? 'production'));
        $flag = filter_var($_ENV['ALEXA_SKIP_VERIFICATION'] ?? false, FILTER_VALIDATE_BOOL);
        return $flag && $env !== 'production';
    }

    private function plainError(ResponseInterface $response, string $message): ResponseInterface
    {
        // Alexa treats any non-200 as a failure; a 400 keeps rogue callers out.
        return JsonResponse::error($message, 400);
    }
}
