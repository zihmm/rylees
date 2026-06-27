<?php

declare(strict_types=1);

/*
 * Modular-monolith boundary guarantees — AC-API-03, AC-API-15 (ADR-005).
 *
 * A module must never reach into another module's internal implementation
 * details — its Controllers, Requests, Resources, or Repositories. Cross-module
 * data and behaviour are obtained only through public Service interfaces
 * (e.g. ProjectService, CustomerService, AI\Services\TranslationService).
 *
 * Application code (Controllers, Services, Repositories, Requests, Resources)
 * must NOT import another module's Models either; cross-module data is fetched
 * via the owning module's service. The single sanctioned exception is Eloquent
 * relationship definitions inside Models/, which may reference another module's
 * Model to express framework-level associations (hasMany / belongsTo / hasOne).
 */

/**
 * @return list<string>
 */
function moduleNames(): array
{
    $base = dirname(__DIR__, 2).'/app/Modules';

    return array_values(array_filter(
        array_map('basename', glob($base.'/*', GLOB_ONLYDIR) ?: []),
    ));
}

/**
 * @return list<string> the PHP files under a module directory
 */
function moduleFiles(string $module): array
{
    $dir = dirname(__DIR__, 2).'/app/Modules/'.$module;
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file)
    {
        if ($file->isFile() && $file->getExtension() === 'php')
        {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

it('discovers the expected business modules', function (): void
{
    expect(moduleNames())->toContain('Auth', 'Account', 'Customer', 'Project', 'ReleaseHistory', 'AI');
});

it('gives every module its own routes.php', function (): void
{
    foreach (moduleNames() as $module)
    {
        expect(file_exists(dirname(__DIR__, 2)."/app/Modules/{$module}/routes.php"))
            ->toBeTrue("Module {$module} is missing routes.php");
    }
});

it('does not let a module reach into another module\'s internal layers', function (): void
{
    // Internal implementation details that must never cross a module boundary.
    $internalLayers = ['Controllers', 'Requests', 'Resources', 'Repositories'];
    $pattern = '/use\s+App\\\\Modules\\\\([A-Za-z]+)\\\\('.implode('|', $internalLayers).')\\\\/';

    $violations = [];

    foreach (moduleNames() as $owner)
    {
        foreach (moduleFiles($owner) as $file)
        {
            $contents = file_get_contents($file) ?: '';

            if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER))
            {
                foreach ($matches as $match)
                {
                    [, $targetModule, $layer] = $match;

                    if ($targetModule !== $owner)
                    {
                        $violations[] = sprintf(
                            '%s imports %s\\%s (%s)',
                            $owner,
                            $targetModule,
                            $layer,
                            basename($file),
                        );
                    }
                }
            }
        }
    }

    expect($violations)->toBe([], 'Cross-module internal dependency detected: '.implode('; ', $violations));
});

it('only references another module\'s Model inside Model relationship definitions', function (): void
{
    // Cross-module Model imports are allowed solely in <Module>/Models/* (to
    // declare Eloquent relations). Anywhere else they signal data access that
    // should go through the owning module's service instead.
    $pattern = '/use\s+App\\\\Modules\\\\([A-Za-z]+)\\\\Models\\\\([A-Za-z]+)/';

    $violations = [];

    foreach (moduleNames() as $owner)
    {
        foreach (moduleFiles($owner) as $file)
        {
            $isModelFile = str_contains($file, "/Modules/{$owner}/Models/");
            $contents = file_get_contents($file) ?: '';

            if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER))
            {
                foreach ($matches as $match)
                {
                    [, $targetModule, $model] = $match;

                    if ($targetModule !== $owner && ! $isModelFile)
                    {
                        $violations[] = sprintf(
                            '%s imports %s\\Models\\%s (%s)',
                            $owner,
                            $targetModule,
                            $model,
                            basename($file),
                        );
                    }
                }
            }
        }
    }

    expect($violations)->toBe([], 'Cross-module Model used outside a relationship definition: '.implode('; ', $violations));
});
