<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function (Model $model): void
        {
            $model->setAttribute(
                static::slugColumn(),
                static::generateUniqueSlug($model->getAttribute(static::slugSource()), $model)
            );
        });
    }

    protected static function slugColumn(): string
    {
        return 'slug';
    }

    protected static function slugSource(): string
    {
        return 'name';
    }

    protected static function generateUniqueSlug(string $source, Model $model): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($source));
        $base = mb_trim($base, '-');
        $slug = $base;
        $i = 2;
        while (static::slugExists($slug, $model))
        {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    protected static function slugExists(string $slug, Model $model): bool
    {
        $query = static::where(static::slugColumn(), $slug);

        if (method_exists(static::class, 'slugScope'))
        {
            $query = static::slugScope($query, $model);
        }

        if ($model->exists)
        {
            $query->whereKeyNot($model->getKey());
        }

        return $query->exists();
    }
}
