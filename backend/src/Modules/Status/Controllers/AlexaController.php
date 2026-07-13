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
    /** Cached, decoded APL pager document. */
    private ?array $aplDocument = null;

    public function __construct(
        private readonly StatusService $statusService,
        private readonly AlexaRequestVerifier $verifier,
        private readonly string $aplDocPath
    ) {}

    public function webhook(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // --- 1. Read the RAW body (signature is over the exact bytes) ---
        // RawBodyPreserveMiddleware captures the untouched bytes before the JSON
        // body parser consumes the stream. Fall back to reading the stream
        // directly if the attribute is absent (e.g. middleware not registered).
        $raw = $request->getAttribute('rawBody');
        if (!is_string($raw)) {
            $body = $request->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $raw = $body->getContents();
        }

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
        $co = $this->statusService->getContainers();
        $directive = $apl ? $this->buildPagerDirective($ov, $co, 0, null) : null;
        return $this->tell($this->speakOverview($ov), $directive, false, 'Frag mich zum Beispiel: Wie voll ist die Festplatte?');
    }

    private function handleIntent(array $payload, bool $apl): array
    {
        $intent = $payload['request']['intent']['name'] ?? '';

        return match ($intent) {
            'ServerStatusIntent', 'OverviewIntent' => $this->renderOverview($apl, 'overview'),
            'ContainerStatusIntent' => $this->renderContainers($payload, $apl),
            'DiskStatusIntent' => $this->renderOverview($apl, 'disk'),
            'ServicesStatusIntent' => $this->renderOverview($apl, 'services'),
            'ContainerStartIntent' => $this->handleControlRequest($payload, 'start'),
            'ContainerStopIntent' => $this->handleControlRequest($payload, 'stop'),
            'ContainerRestartIntent' => $this->handleControlRequest($payload, 'restart'),
            'AMAZON.YesIntent' => $this->handleControlConfirmation($payload, true),
            'AMAZON.NoIntent' => $this->handleControlConfirmation($payload, false),
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
    // Rendering: speech + the Echo Show 8 dashboard pager
    // ------------------------------------------------------------------

    /**
     * Overview-page render (page 0). $kind selects the spoken summary.
     * Data is fetched once and reused for both speech and the APL datasources.
     */
    private function renderOverview(bool $apl, string $kind): array
    {
        $ov = $this->statusService->getOverview($this->scopeUserId());
        $co = $this->statusService->getContainers();

        $speech = match ($kind) {
            'disk' => $this->diskSpeech($ov),
            'services' => $this->servicesSpeech($ov),
            default => $this->speakOverview($ov),
        };

        $directive = $apl ? $this->buildPagerDirective($ov, $co, 0, null) : null;
        return $this->tell($speech, $directive, true);
    }

    /**
     * Container-page render (page 1). Passes the optional container slot through
     * as a filter so the dashboard opens straight on the matching container(s).
     */
    private function renderContainers(array $payload, bool $apl): array
    {
        $ov = $this->statusService->getOverview($this->scopeUserId());
        $co = $this->statusService->getContainers();
        $filter = $this->slotValue($payload, 'container');

        $directive = $apl ? $this->buildPagerDirective($ov, $co, 1, $filter) : null;
        return $this->tell($this->containerSpeech($co, $filter), $directive, true);
    }

    /**
     * Build the RenderDocument directive for the two-page dashboard pager.
     * Keeps the existing 'kyuubiStatus' token. Returns null (no APL) if the
     * document can't be loaded, so the skill still answers by voice.
     */
    private function buildPagerDirective(array $ov, array $co, int $startPage, ?string $filter): ?array
    {
        $document = $this->aplDocument();
        if ($document === null) {
            return null;
        }

        return [
            'type' => 'Alexa.Presentation.APL.RenderDocument',
            'token' => 'kyuubiStatus',
            'document' => $document,
            'datasources' => [
                'status' => $this->buildStatusDatasource($ov, $co['containers'] ?? []),
                'containers' => $this->buildContainersDatasource($co, $filter),
                'nav' => ['startPage' => $startPage],
            ],
        ];
    }

    private function aplDocument(): ?array
    {
        if ($this->aplDocument !== null) {
            return $this->aplDocument;
        }
        if (!is_file($this->aplDocPath)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($this->aplDocPath), true);
        if (!is_array($decoded)) {
            return null;
        }
        return $this->aplDocument = $decoded;
    }

    // ------------------------------------------------------------------
    // Control intents (write) — spoken confirmation via session attributes
    // ------------------------------------------------------------------

    /**
     * Step 1 of a control command: read the container, then ask for spoken
     * confirmation. The pending command is stashed in the session so the
     * follow-up Yes/No can act on it. Nothing is executed here.
     */
    private function handleControlRequest(array $payload, string $action): array
    {
        if (!$this->controlEnabled()) {
            return $this->tell('Das Steuern von Containern ist nicht aktiviert.', null, true);
        }

        $container = $this->slotValue($payload, 'container');

        if ($container === null) {
            return $this->tell(
                'Ich habe nicht verstanden, welchen Container du meinst. ' .
                'Sag zum Beispiel: starte den Container Nginx.',
                null,
                false
            );
        }

        if (!$this->containerAllowed($container)) {
            return $this->tell("Der Container {$container} darf nicht per Sprache gesteuert werden.", null, true);
        }

        $verb = ['start' => 'starten', 'stop' => 'stoppen', 'restart' => 'neu starten'][$action];
        return $this->tell(
            "Soll ich den Container {$container} wirklich {$verb}? Sag ja oder nein.",
            null,
            false,
            'Sag ja, um fortzufahren, oder nein, um abzubrechen.',
            ['pending_action' => $action, 'pending_container' => $container]
        );
    }

    /**
     * Step 2: the user answered yes/no to the confirmation. On yes, execute the
     * stashed command; on no, discard it.
     */
    private function handleControlConfirmation(array $payload, bool $confirmed): array
    {
        $attributes = $payload['session']['attributes'] ?? [];
        $action = $attributes['pending_action'] ?? null;
        $container = $attributes['pending_container'] ?? null;

        if ($action === null || $container === null) {
            return $this->tell('Es gibt gerade nichts zu bestätigen.', null, true);
        }

        if (!$confirmed) {
            return $this->tell('Alles klar, ich lasse es.', null, true);
        }

        // Re-check the gate at execution time, never trust only the session.
        if (!$this->controlEnabled() || !$this->containerAllowed((string) $container)) {
            return $this->tell('Das Steuern dieses Containers ist nicht erlaubt.', null, true);
        }

        $result = $this->statusService->controlContainer((string) $container, (string) $action);
        return $this->tell($result['message'], null, true);
    }

    private function controlEnabled(): bool
    {
        return filter_var($_ENV['ALEXA_ALLOW_CONTROL'] ?? false, FILTER_VALIDATE_BOOL);
    }

    /**
     * When ALEXA_CONTROL_ALLOWLIST is set, only its (comma-separated) container
     * names may be controlled. Empty = all containers allowed.
     */
    private function containerAllowed(string $container): bool
    {
        $raw = trim((string) ($_ENV['ALEXA_CONTROL_ALLOWLIST'] ?? ''));
        if ($raw === '') {
            return true;
        }
        $allow = array_map('trim', explode(',', $raw));
        foreach ($allow as $name) {
            if ($name !== '' && strcasecmp($name, $container) === 0) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------
    // Speech builders
    // ------------------------------------------------------------------

    private function containerSpeech(array $data, ?string $name): string
    {
        if (!$data['available']) {
            return 'Docker ist gerade nicht erreichbar.';
        }

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

    private function diskSpeech(array $ov): string
    {
        $lines = [];
        foreach ($ov['server']['disks'] as $disk) {
            $lines[] = sprintf('%s ist zu %s Prozent belegt', $disk['mount'], $this->num($disk['percent']));
        }
        if (empty($lines)) {
            return 'Ich konnte keine Festplatten auslesen.';
        }
        return implode('. ', $lines) . '.';
    }

    private function servicesSpeech(array $ov): string
    {
        $u = $ov['services']['uptime'];
        $ssl = $ov['services']['ssl'];
        $cron = $ov['services']['cron'];

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
     * Build an Alexa response. When $reprompt is set the session stays open.
     * $sessionAttributes are echoed back so a follow-up turn (e.g. the Yes/No
     * confirmation) can read the pending command.
     */
    private function tell(
        string $speech,
        ?array $aplDirective,
        bool $endSession,
        ?string $reprompt = null,
        array $sessionAttributes = []
    ): array {
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

        $envelope = ['version' => '1.0', 'response' => $response];
        if (!empty($sessionAttributes)) {
            $envelope['sessionAttributes'] = $sessionAttributes;
        }
        return $envelope;
    }

    /**
     * One clean spoken overview line. Mentions unhealthy containers and offline
     * services exactly once (no duplicate "ungesund"), with singular/plural.
     */
    private function speakOverview(array $overview): string
    {
        $s = $overview['server'];
        $d = $overview['docker']['summary'];
        $root = $s['disks'][0] ?? ['percent' => 0, 'mount' => '/'];
        foreach ($s['disks'] as $x) {
            if ($x['mount'] === '/') {
                $root = $x;
                break;
            }
        }

        $line = 'CPU ' . round($s['cpu']['percent']) . ' Prozent, Arbeitsspeicher ' . round($s['memory']['percent'])
            . ' Prozent, Systemplatte ' . round($root['percent']) . ' Prozent. '
            . "{$d['running']} von {$d['total']} Containern laufen";

        $issues = [];
        if ($d['unhealthy'] > 0) {
            $issues[] = $d['unhealthy'] === 1 ? 'ein Container ist ungesund' : "{$d['unhealthy']} Container sind ungesund";
        }
        $down = $overview['services']['uptime']['down'] ?? 0;
        if ($down > 0) {
            $issues[] = $down === 1 ? 'ein Dienst ist offline' : "{$down} Dienste sind offline";
        }

        return $line . ($issues ? '. ' . ucfirst(implode(', ', $issues)) . '.' : '.');
    }

    // ------------------------------------------------------------------
    // APL datasources for the dashboard pager
    // ------------------------------------------------------------------

    /**
     * Build the "status" datasource for the overview page (rings, docker card,
     * services card, alert banner). $containers is the raw container list, used
     * only to name unhealthy containers.
     */
    private function buildStatusDatasource(array $overview, array $containers = []): array
    {
        $srv = $overview['server'];
        $lvl = fn (float $p): string => $p >= 85 ? 'crit' : ($p >= 70 ? 'warn' : 'ok');
        $de  = fn (float $v, int $dgt = 1): string => number_format($v, $dgt, ',', '');
        $gib = fn (int $b): float => round($b / 1073741824, 1);

        $root = $srv['disks'][0] ?? ['percent' => 0, 'used_bytes' => 0, 'total_bytes' => 0, 'mount' => '/'];
        foreach ($srv['disks'] as $disk) {
            if ($disk['mount'] === '/') {
                $root = $disk;
                break;
            }
        }

        preg_match('/(\d+)\s*weeks?/', $srv['uptime'], $w);
        preg_match('/(\d+)\s*days?/', $srv['uptime'], $dd);
        $up = array_filter([!empty($w[1]) ? "$w[1] Wo" : null, !empty($dd[1]) ? "$dd[1] Tg" : null]);
        $uptime = 'Uptime ' . (implode(' ', $up) ?: 'aktiv');

        $names = [];
        foreach ($containers as $c) {
            if (!empty($c['unhealthy'])) {
                $names[] = preg_replace('/^kyuubisoft[_-]?/', '', $c['name']);
            }
        }

        $u = $overview['services']['uptime'];
        $s = $overview['services']['ssl'];

        $alerts = [];
        $crit = 0;
        $warn = 0;
        foreach ($overview['alerts'] ?? [] as $a) {
            $alerts[] = $a['message'];
            if ($a['level'] === 'critical') {
                $crit++;
            } elseif ($a['level'] === 'warning') {
                $warn++;
            }
        }
        if ($crit > 0) {
            $overall = ['level' => 'crit', 'text' => $crit . ($crit === 1 ? ' kritische Meldung' : ' kritische Meldungen')];
        } elseif ($warn > 0) {
            $overall = ['level' => 'warn', 'text' => $warn . ($warn === 1 ? ' Warnung' : ' Warnungen')];
        } else {
            $overall = ['level' => 'ok', 'text' => 'Alles in Ordnung'];
        }

        $d = $overview['docker']['summary'];

        return [
            'host' => 'KyuubiSoft',
            'hostname' => 'dev.kyuubisoft.com',
            'time' => (new \DateTime($overview['generated_at']))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('d.m.Y · H:i'),
            'overall' => $overall,
            'cpu' => [
                'pct' => $srv['cpu']['percent'], 'value' => $de($srv['cpu']['percent']), 'label' => 'CPU',
                'sub' => $srv['cpu']['cores'] . ' Kerne · Load ' . implode(' / ', array_map(fn ($l) => number_format($l, 2, '.', ''), $srv['cpu']['load'])),
                'level' => $lvl($srv['cpu']['percent']),
            ],
            'ram' => [
                'pct' => $srv['memory']['percent'], 'value' => $de($srv['memory']['percent']), 'label' => 'RAM',
                'sub' => $de($gib($srv['memory']['used_bytes'])) . ' / ' . $de($gib($srv['memory']['total_bytes'])) . ' GB',
                'level' => $lvl($srv['memory']['percent']),
            ],
            'disk' => [
                'pct' => $root['percent'], 'value' => $de($root['percent']), 'label' => 'Disk /',
                'sub' => round($gib($root['used_bytes'])) . ' / ' . round($gib($root['total_bytes'])) . ' GB',
                'level' => $lvl($root['percent']),
            ],
            'docker' => [
                'running' => $d['running'], 'total' => $d['total'], 'stopped' => $d['stopped'],
                'unhealthy' => $d['unhealthy'], 'pct' => $d['total'] > 0 ? round($d['running'] / $d['total'] * 100, 1) : 0,
                'names' => implode(' · ', array_slice($names, 0, 3)), 'uptime' => $uptime,
            ],
            'services' => [
                'uptime' => [
                    'value' => "{$u['up']} / {$u['total']} online", 'level' => $u['down'] > 0 ? 'crit' : 'ok',
                    'note' => $u['down'] > 0 ? '„' . ($u['down_names'][0] ?? '') . '" offline' : '',
                ],
                'ssl' => [
                    'value' => "{$s['valid']} / {$s['total']} gültig", 'level' => $s['expired'] > 0 ? 'crit' : ($s['expiring_soon'] > 0 ? 'warn' : 'ok'),
                    'note' => !empty($s['problem_names']) ? implode(', ', $s['problem_names']) : '',
                ],
            ],
            'alerts' => $alerts,
        ];
    }

    /**
     * Build the "containers" datasource for the container page. Optionally
     * filters by name/stack, sorts unhealthy first then by load, and scales the
     * CPU/MEM bars relative to the busiest container.
     */
    private function buildContainersDatasource(array $data, ?string $filter = null): array
    {
        $list = $data['containers'] ?? [];
        if ($filter !== null && trim($filter) !== '') {
            $n = mb_strtolower(trim($filter));
            $list = array_values(array_filter($list, fn ($c) => str_contains(mb_strtolower($c['name']), $n)
                || str_contains(mb_strtolower((string) ($c['stack'] ?? '')), $n)));
        }

        $maxCpu = 0.001;
        $maxMem = 0.001;
        foreach ($list as $c) {
            $maxCpu = max($maxCpu, (float) ($c['cpu_percent'] ?? 0));
            $maxMem = max($maxMem, (float) ($c['mem_percent'] ?? 0));
        }

        $rank = fn (array $c): int => !empty($c['unhealthy']) ? 0 : (($c['state'] ?? '') === 'running' ? 1 : 2);
        usort($list, function ($a, $b) use ($rank) {
            if ($rank($a) !== $rank($b)) {
                return $rank($a) <=> $rank($b);
            }
            return ((float) ($b['cpu_percent'] ?? 0) + (float) ($b['mem_percent'] ?? 0))
                <=> ((float) ($a['cpu_percent'] ?? 0) + (float) ($a['mem_percent'] ?? 0));
        });

        $de = fn (?float $v): string => $v === null ? '–' : number_format($v, 1, ',', '');
        $items = array_map(function ($c) use ($maxCpu, $maxMem, $de) {
            $cpu = $c['cpu_percent'] !== null ? (float) $c['cpu_percent'] : null;
            $mem = $c['mem_percent'] !== null ? (float) $c['mem_percent'] : null;
            $level = !empty($c['unhealthy']) ? 'crit'
                : (($c['state'] ?? '') !== 'running' ? 'idle'
                : ((($cpu ?? 0) >= 50 || ($mem ?? 0) >= 75) ? 'warn' : 'ok'));
            return [
                'name' => preg_replace('/^kyuubisoft[_-]?/', '', $c['name']),
                'sub' => ($c['stack'] ? $c['stack'] . ' · ' : '') . $this->containerStatusShort($c),
                'level' => $level,
                'cpu' => $de($cpu), 'cpuBar' => $cpu !== null ? (int) round($cpu / $maxCpu * 100) : 0,
                'mem' => $de($mem), 'memBar' => $mem !== null ? (int) round($mem / $maxMem * 100) : 0,
            ];
        }, $list);

        return [
            'title' => ($filter && trim($filter) !== '') ? 'Container: ' . trim($filter) : 'Container',
            'summary' => $data['summary'] ?? ['running' => 0, 'total' => count($list), 'stopped' => 0, 'unhealthy' => 0],
            'items' => array_values($items),
        ];
    }

    /**
     * Short human status for a container row, e.g. "läuft · 3 Tg".
     */
    private function containerStatusShort(array $c): string
    {
        $s = $c['status'] ?? '';
        $u = ['weeks' => 'Wo', 'week' => 'Wo', 'months' => 'Mon', 'month' => 'Mon', 'days' => 'Tg', 'day' => 'Tg',
              'hours' => 'Std', 'hour' => 'Std', 'minutes' => 'Min', 'minute' => 'Min', 'seconds' => 'Sek', 'second' => 'Sek'];
        if (($c['state'] ?? '') === 'running') {
            if (stripos($s, 'Less than') !== false) {
                return 'läuft · gerade';
            }
            if (preg_match('/Up\s+(\d+)\s+(\w+)/', $s, $m)) {
                return 'läuft · ' . $m[1] . ' ' . ($u[strtolower($m[2])] ?? $m[2]);
            }
            return 'läuft';
        }
        if (preg_match('/Exited.*?(\d+)\s+(\w+)\s+ago/', $s, $m)) {
            return 'gestoppt · vor ' . $m[1] . ' ' . ($u[strtolower($m[2])] ?? $m[2]);
        }
        return 'gestoppt';
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
