<?php

declare(strict_types=1);

namespace App\Controller; // <-- an deine Namespace-Struktur anpassen

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Alexa-Webhook für den "server status"-Skill (Echo Show 8).
 *
 * Einbau in Slim 4:
 *   $app->post('/api/v1/alexa/webhook', [App\Controller\AlexaController::class, 'webhook']);
 *
 * Anpassen:
 *  - Namespace + `use`-Pfade auf $status (dein Status-Service) und $verifier (dein AlexaRequestVerifier).
 *  - APL_DOC-Pfad auf den Ort, wo dashboard-pager.json liegt.
 *  - $status->overview()   muss das "data"-Objekt von /api/v1/status/overview liefern.
 *  - $status->containers() muss das "data"-Objekt von /api/v1/status/containers liefern.
 *  - Deine bestehende Steuerung (Start/Stop/Restart + Ja/Nein) NICHT ersetzen:
 *    siehe die markierten Zweige weiter unten -> dort deinen vorhandenen Handler aufrufen.
 */
final class AlexaController
{
    /** Pfad zum kombinierten Pager-Dokument – an deine Struktur anpassen. */
    private const APL_DOC = __DIR__ . '/../resources/alexa/dashboard-pager.json';

    public function __construct(
        private readonly \App\Service\StatusService $status,
        private readonly \App\Service\AlexaRequestVerifier $verifier,
    ) {
    }

    public function webhook(Request $request, Response $response): Response
    {
        // 1) Rohbody EINMAL lesen (Signaturprüfung braucht den unveränderten Body).
        $raw = (string) $request->getBody();

        // 2) Signatur/Skill-ID/Zeitstempel prüfen mit deinem bestehenden Verifier.
        //    (Signatur ggf. anpassen: verify(Request) oder verify(Request, string $raw).)
        if (!$this->verifier->verify($request, $raw)) {
            return $this->json($response->withStatus(400), ['error' => 'invalid signature']);
        }

        $alexa  = json_decode($raw, true) ?? [];
        $type   = $alexa['request']['type'] ?? '';
        $intent = $alexa['request']['intent']['name'] ?? '';
        $apl    = isset($alexa['context']['System']['device']['supportedInterfaces']['Alexa.Presentation.APL']);

        // --- Übersicht (Startbild) ---
        if ($type === 'LaunchRequest' || $intent === 'ServerStatusIntent') {
            $ov = $this->status->overview();
            $co = $this->status->containers();
            return $this->render($response, $apl, $ov, $co, 0, $this->speakOverview($ov));
        }

        // --- Container-Seite ---
        if ($intent === 'ContainerStatusIntent') {
            $ov   = $this->status->overview();
            $co   = $this->status->containers();
            $slot = $alexa['request']['intent']['slots']['container']['value'] ?? null;
            $sum  = $co['summary'];
            $speech = "{$sum['running']} von {$sum['total']} Containern laufen"
                . ($sum['unhealthy'] > 0 ? ", {$sum['unhealthy']} sind ungesund." : ".");
            return $this->render($response, $apl, $ov, $co, 1, $speech, $slot);
        }

        // --- Festplatte ---
        if ($intent === 'DiskStatusIntent') {
            $ov = $this->status->overview();
            $co = $this->status->containers();
            $root = $ov['server']['disks'][0];
            foreach ($ov['server']['disks'] as $x) {
                if ($x['mount'] === '/') { $root = $x; break; }
            }
            $free = round(($root['free_bytes'] ?? 0) / 1073741824);
            $speech = "Die Systemplatte ist zu " . round($root['percent']) . " Prozent belegt, noch {$free} Gigabyte frei.";
            return $this->render($response, $apl, $ov, $co, 0, $speech);
        }

        // --- Dienste ---
        if ($intent === 'ServicesStatusIntent') {
            $ov  = $this->status->overview();
            $co  = $this->status->containers();
            $svc = $ov['services'];
            $issues = [];
            if (($svc['uptime']['down'] ?? 0) > 0)          $issues[] = implode(', ', $svc['uptime']['down_names']) . " offline";
            if (($svc['ssl']['expiring_soon'] ?? 0) > 0)    $issues[] = "{$svc['ssl']['expiring_soon']} Zertifikate laufen bald ab";
            if (($svc['ssl']['expired'] ?? 0) > 0)          $issues[] = "{$svc['ssl']['expired']} Zertifikate sind abgelaufen";
            $speech = $issues ? "Achtung: " . implode('; ', $issues) . "." : "Alle Dienste sind online und die Zertifikate gültig.";
            return $this->render($response, $apl, $ov, $co, 0, $speech);
        }

        // --- Steuerung: hier deinen bestehenden Handler aufrufen, nicht neu bauen ---
        if (in_array($intent, ['ContainerStartIntent', 'ContainerStopIntent', 'ContainerRestartIntent',
                               'AMAZON.YesIntent', 'AMAZON.NoIntent'], true)) {
            // return $this->control->handle($alexa, $response);   // <-- deine vorhandene Logik
            return $this->speak($response, "Steuerung ist hier noch nicht verdrahtet.", true);
        }

        // --- Standard-Intents ---
        if ($intent === 'AMAZON.HelpIntent') {
            return $this->speak($response, "Frag mich zum Beispiel: wie geht es dem Server, wie geht es den Containern, oder wie voll ist die Festplatte.", false);
        }
        if (in_array($intent, ['AMAZON.StopIntent', 'AMAZON.CancelIntent'], true)) {
            return $this->speak($response, "Bis dann.", true);
        }
        if ($type === 'SessionEndedRequest') {
            return $this->json($response, ['version' => '1.0', 'response' => new \stdClass()]);
        }

        return $this->speak($response, "Das habe ich nicht verstanden. Sag zum Beispiel: wie geht es dem Server.", false);
    }

    // ---------------------------------------------------------------- Rendering

    private function render(Response $response, bool $apl, array $overview, array $containers, int $startPage, string $speech, ?string $filter = null): Response
    {
        $payload = ['version' => '1.0', 'response' => [
            'outputSpeech'     => ['type' => 'PlainText', 'text' => $speech],
            'shouldEndSession' => true,
        ]];

        if ($apl) {
            $payload['response']['directives'][] = [
                'type'        => 'Alexa.Presentation.APL.RenderDocument',
                'token'       => 'dashboard',
                'document'    => json_decode(file_get_contents(self::APL_DOC), true),
                'datasources' => [
                    'status'     => $this->buildStatusDatasource($overview, $containers['containers'] ?? []),
                    'containers' => $this->buildContainersDatasource($containers, $filter),
                    'nav'        => ['startPage' => $startPage],
                ],
            ];
        }

        return $this->json($response, $payload);
    }

    private function speak(Response $response, string $text, bool $end): Response
    {
        return $this->json($response, ['version' => '1.0', 'response' => [
            'outputSpeech'     => ['type' => 'PlainText', 'text' => $text],
            'shouldEndSession' => $end,
        ]]);
    }

    private function json(Response $response, array|\stdClass $payload): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // ------------------------------------------------------------------ Speech

    private function speakOverview(array $overview): string
    {
        $s = $overview['server'];
        $d = $overview['docker']['summary'];
        $root = $s['disks'][0];
        foreach ($s['disks'] as $x) {
            if ($x['mount'] === '/') { $root = $x; break; }
        }

        $line = "CPU " . round($s['cpu']['percent']) . " Prozent, Arbeitsspeicher " . round($s['memory']['percent'])
            . " Prozent, Systemplatte " . round($root['percent']) . " Prozent. "
            . "{$d['running']} von {$d['total']} Containern laufen";

        $issues = [];
        if ($d['unhealthy'] > 0) {
            $issues[] = $d['unhealthy'] === 1 ? "ein Container ist ungesund" : "{$d['unhealthy']} Container sind ungesund";
        }
        $down = $overview['services']['uptime']['down'] ?? 0;
        if ($down > 0) {
            $issues[] = $down === 1 ? "ein Dienst ist offline" : "{$down} Dienste sind offline";
        }

        return $line . ($issues ? ". " . ucfirst(implode(", ", $issues)) . "." : ".");
    }

    // -------------------------------------------------------------- Datasources

    private function buildStatusDatasource(array $overview, array $containers = []): array
    {
        $srv = $overview['server'];
        $lvl = fn (float $p): string => $p >= 85 ? 'crit' : ($p >= 70 ? 'warn' : 'ok');
        $de  = fn (float $v, int $dgt = 1): string => number_format($v, $dgt, ',', '');
        $gib = fn (int $b): float => round($b / 1073741824, 1);

        $root = $srv['disks'][0];
        foreach ($srv['disks'] as $disk) {
            if ($disk['mount'] === '/') { $root = $disk; break; }
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
            'host'     => 'KyuubiSoft',
            'hostname' => 'dev.kyuubisoft.com',
            'time'     => (new \DateTime($overview['generated_at']))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('d.m.Y · H:i'),
            'overall'  => $overall,
            'cpu'  => ['pct' => $srv['cpu']['percent'], 'value' => $de($srv['cpu']['percent']), 'label' => 'CPU',
                       'sub' => $srv['cpu']['cores'] . ' Kerne · Load ' . implode(' / ', array_map(fn ($l) => number_format($l, 2, '.', ''), $srv['cpu']['load'])),
                       'level' => $lvl($srv['cpu']['percent'])],
            'ram'  => ['pct' => $srv['memory']['percent'], 'value' => $de($srv['memory']['percent']), 'label' => 'RAM',
                       'sub' => $de($gib($srv['memory']['used_bytes'])) . ' / ' . $de($gib($srv['memory']['total_bytes'])) . ' GB',
                       'level' => $lvl($srv['memory']['percent'])],
            'disk' => ['pct' => $root['percent'], 'value' => $de($root['percent']), 'label' => 'Disk /',
                       'sub' => round($gib($root['used_bytes'])) . ' / ' . round($gib($root['total_bytes'])) . ' GB',
                       'level' => $lvl($root['percent'])],
            'docker' => ['running' => $d['running'], 'total' => $d['total'], 'stopped' => $d['stopped'],
                         'unhealthy' => $d['unhealthy'], 'pct' => $d['total'] > 0 ? round($d['running'] / $d['total'] * 100, 1) : 0,
                         'names' => implode(' · ', array_slice($names, 0, 3)), 'uptime' => $uptime],
            'services' => [
                'uptime' => ['value' => "{$u['up']} / {$u['total']} online", 'level' => $u['down'] > 0 ? 'crit' : 'ok',
                             'note' => $u['down'] > 0 ? '„' . ($u['down_names'][0] ?? '') . '" offline' : ''],
                'ssl'    => ['value' => "{$s['valid']} / {$s['total']} gültig", 'level' => $s['expired'] > 0 ? 'crit' : ($s['expiring_soon'] > 0 ? 'warn' : 'ok'),
                             'note' => !empty($s['problem_names']) ? implode(', ', $s['problem_names']) : ''],
            ],
            'alerts' => $alerts,
        ];
    }

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
                'name'   => preg_replace('/^kyuubisoft[_-]?/', '', $c['name']),
                'sub'    => ($c['stack'] ? $c['stack'] . ' · ' : '') . $this->containerStatusShort($c),
                'level'  => $level,
                'cpu'    => $de($cpu), 'cpuBar' => $cpu !== null ? (int) round($cpu / $maxCpu * 100) : 0,
                'mem'    => $de($mem), 'memBar' => $mem !== null ? (int) round($mem / $maxMem * 100) : 0,
            ];
        }, $list);

        return [
            'title'   => ($filter && trim($filter) !== '') ? 'Container: ' . trim($filter) : 'Container',
            'summary' => $data['summary'] ?? ['running' => 0, 'total' => count($list), 'stopped' => 0, 'unhealthy' => 0],
            'items'   => array_values($items),
        ];
    }

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
}
