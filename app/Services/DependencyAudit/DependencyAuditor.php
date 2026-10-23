<?php

namespace App\Services\DependencyAudit;

use App\Models\DependencyReport;
use App\Models\DependencyReportPackage;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class DependencyAuditor
{
    public function __construct(private readonly Filesystem $files) {}

    /** @return array<int, array<string, mixed>> */
    public function audit(DependencyReport $report): array
    {
        $inventories = [];
        foreach ($report->dependencies as $package) {
            $manager = $package->manager ?: match (true) {
                str_starts_with(strtolower($report->language_version), 'php') => 'composer',
                preg_match('/^node(?:\.js|js|\s|$)/i', $report->language_version) === 1 => 'npm',
                default => null,
            };

            if (! in_array($manager, ['composer', 'npm'], true)) {
                continue;
            }

            $version = $package->installation_observed ? $package->installed : ($package->lock_observed ? $package->locked : null);
            if ($version === null) {
                continue;
            }

            $inventories[$manager][$package->name.'@'.$version] = $package;
        }

        $findings = [];
        foreach ($inventories as $manager => $packages) {
            $directory = sys_get_temp_dir().'/dependency-audit-'.Str::uuid();
            $this->files->makeDirectory($directory, 0700);

            try {
                $findings = [...$findings, ...$this->auditInventory($manager, array_values($packages), $directory)];
            } finally {
                $this->files->deleteDirectory($directory);
            }
        }

        return $findings;
    }

    /**
     * @param  array<int, DependencyReportPackage>  $packages
     * @return array<int, array<string, mixed>>
     */
    private function auditInventory(string $manager, array $packages, string $directory): array
    {
        $inventory = [];
        foreach ($packages as $package) {
            $version = $package->installation_observed ? $package->installed : $package->locked;
            $inventory[] = ['name' => $package->name, 'version' => $version];
        }

        $command = $manager === 'composer'
            ? $this->composerWorkspace($inventory, $directory)
            : $this->npmWorkspace($inventory, $directory);

        $result = Process::path($directory)->timeout(config('dependency-audit.timeout'))->env([
            'HOME' => $directory,
            'COMPOSER_HOME' => $directory.'/composer-home',
            'COMPOSER_CACHE_DIR' => $directory.'/composer-cache',
            'COMPOSER_AUTH' => false,
            'COMPOSER' => false,
            'COMPOSER_NO_DEV' => false,
            'COMPOSER_VENDOR_DIR' => false,
            'NPM_CONFIG_CACHE' => $directory.'/npm-cache',
            'NPM_CONFIG_USERCONFIG' => $directory.'/npm-userconfig',
            'NPM_CONFIG_GLOBALCONFIG' => $directory.'/npm-globalconfig',
            'NODE_ENV' => false,
        ])->run($command);

        $output = json_decode($result->output(), true);
        if (! is_array($output) || isset($output['error'])) {
            throw new RuntimeException($manager.' audit failed: '.Str::limit($result->errorOutput() ?: $result->output(), 2000));
        }

        $findings = $manager === 'composer'
            ? $this->composerFindings($output, $inventory)
            : $this->npmFindings($output, $inventory);

        if ($result->failed() && $findings === []) {
            throw new RuntimeException($manager.' audit returned exit code '.$result->exitCode().': '.Str::limit($result->errorOutput(), 2000));
        }

        return $findings;
    }

    private function writeJson(string $path, array $data): void
    {
        $this->files->put($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function composerWorkspace(array $inventory, string $directory): array
    {
        $packages = [];
        foreach ($inventory as $package) {
            if (! preg_match('~^[a-z0-9_.-]+/[a-z0-9_.-]+$~iD', $package['name'])) {
                throw new RuntimeException('Invalid Composer package name: '.$package['name']);
            }
            $packages[] = ['name' => $package['name'], 'version' => $package['version']];
        }

        $this->writeJson($directory.'/composer.json', ['name' => 'backtik/dependency-audit', 'require' => (object) []]);
        $this->writeJson($directory.'/composer.lock', ['packages' => $packages, 'packages-dev' => []]);

        return ['composer', '--no-plugins', '--no-scripts', '--no-interaction', '--no-ansi', 'audit', '--locked', '--format=json', '--abandoned=report'];
    }

    private function npmWorkspace(array $inventory, string $directory): array
    {
        $dependencies = [];
        $packages = [];
        foreach ($inventory as $index => $package) {
            if (! preg_match('~^(?:@[a-z0-9][a-z0-9._-]*/)?[a-z0-9][a-z0-9._-]*$~iD', $package['name'])
                || ! preg_match('/^v?\d+\.\d+\.\d+(?:-[0-9a-z.-]+)?(?:\+[0-9a-z.-]+)?$/iD', $package['version'])) {
                throw new RuntimeException('Invalid npm package or version: '.$package['name'].'@'.$package['version']);
            }

            // Aliases preserve every installed version, including nested copies of the same package.
            $alias = 'audit-'.$index;
            $version = ltrim($package['version'], 'v');
            $dependencies[$alias] = 'npm:'.$package['name'].'@'.$version;
            $packages['node_modules/'.$alias] = ['name' => $package['name'], 'version' => $version];
        }

        $manifest = ['name' => 'backtik-dependency-audit', 'version' => '1.0.0', 'dependencies' => $dependencies];
        $this->writeJson($directory.'/package.json', $manifest);
        $this->writeJson($directory.'/package-lock.json', [
            'name' => $manifest['name'], 'version' => '1.0.0', 'lockfileVersion' => 3,
            'requires' => true, 'packages' => ['' => $manifest, ...$packages],
        ]);

        return ['npm', 'audit', '--json', '--package-lock-only', '--ignore-scripts', '--include=dev', '--include=optional', '--include=peer', '--registry=https://registry.npmjs.org', '--fetch-retries=1', '--fetch-timeout=45000'];
    }

    private function composerFindings(array $output, array $inventory): array
    {
        if (! isset($output['advisories']) || ! is_array($output['advisories'])) {
            throw new RuntimeException('Unexpected Composer audit response.');
        }

        $versions = collect($inventory)->groupBy('name')->map(fn ($packages): array => $packages->pluck('version')->unique()->values()->all());
        $findings = [];
        foreach ($output['advisories'] as $name => $advisories) {
            foreach ($advisories as $advisory) {
                $message = $advisory['title'];
                if (filled($advisory['cve'] ?? null)) {
                    $message .= "\n".$advisory['cve'];
                }
                if (filled($advisory['affectedVersions'] ?? null)) {
                    $message .= "\nVersions affectées : ".$advisory['affectedVersions'];
                }
                $findings[] = $this->finding('composer', $name, $versions->get($name, []), $advisory['severity'] ?? 'unknown', $message, $advisory['link'] ?? null);
            }
        }
        foreach ($output['abandoned'] ?? [] as $name => $replacement) {
            $findings[] = $this->finding('composer', $name, $versions->get($name, []), 'info', 'Paquet abandonné.'.(is_string($replacement) ? ' Remplacement recommandé : '.$replacement : ''));
        }

        return $findings;
    }

    private function npmFindings(array $output, array $inventory): array
    {
        if (($output['auditReportVersion'] ?? null) !== 2 || ! is_array($output['vulnerabilities'] ?? null)) {
            throw new RuntimeException('Unexpected npm audit response.');
        }

        $findings = [];
        foreach ($output['vulnerabilities'] as $name => $vulnerability) {
            $versions = [];
            foreach ($vulnerability['nodes'] as $node) {
                if (preg_match('~^node_modules/audit-(\d+)$~D', $node, $matches) && isset($inventory[(int) $matches[1]])) {
                    $versions[] = $inventory[(int) $matches[1]]['version'];
                }
            }
            if ($versions === []) {
                throw new RuntimeException('Unable to identify versions flagged by npm for '.$name);
            }

            $messages = [];
            $urls = [];
            foreach ($vulnerability['via'] as $advisory) {
                if (is_string($advisory)) {
                    $messages[] = 'Dépendance vulnérable via '.$advisory;

                    continue;
                }
                if (isset($advisory['url'])) {
                    $urls[] = $advisory['url'];
                }
                $messages[] = $advisory['title'].' ('.$advisory['range'].')'.(isset($advisory['url']) ? "\n".$advisory['url'] : '');
            }
            sort($messages, SORT_STRING);
            sort($urls, SORT_STRING);
            $findings[] = $this->finding('npm', $name, array_values(array_unique($versions)), $vulnerability['severity'], implode("\n\n", $messages), $urls[0] ?? null);
        }

        return $findings;
    }

    private function finding(string $manager, string $name, array $versions, string $severity, string $message, ?string $url = null): array
    {
        return [
            'manager' => $manager,
            'dependency' => $name,
            'versions' => $versions,
            'severity' => in_array($severity, ['critical', 'high', 'medium', 'moderate', 'low', 'info'], true) ? $severity : 'unknown',
            'message' => Str::limit($message, 11990),
            'url' => $url,
        ];
    }
}
