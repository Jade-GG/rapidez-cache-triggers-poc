<?php

namespace Rapidez\RapidezCacheTriggersPoc\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Rapidez\Core\Models\EavAttribute;
use Rapidez\Core\Models\Model;

class AttributeOption extends Model
{
    public $timestamps = false;

    protected $table = 'eav_attribute_option';

    protected $primaryKey = 'option_id';

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(EavAttribute::class, 'attribute_id')->withoutGlobalScopes();
    }

    public function values(): HasMany
    {
        return $this->hasMany(config('rapidez.models.option_value'), 'option_id');
    }

    public function swatches(): HasMany
    {
        return $this->hasMany(config('rapidez.models.option_swatch'), 'option_id');
    }

    /**
     * @return array<int, int>
     */
    public static function getHashes(): array
    {
        return static::with('values', 'swatches')
            ->get()
            ->mapWithKeys(fn (self $option) => [$option->option_id => crc32(json_encode([
                $option->sort_order,
                $option->values->sortBy('store_id')->map->only('store_id', 'value')->values(),
                $option->swatches->sortBy('store_id')->map->only('store_id', 'type', 'value')->values(),
            ]))])
            ->all();
    }

    /**
     * @return Collection<int, int>
     */
    public static function getProducts(array $optionIds): Collection
    {
        return static::with('attribute')
            ->whereIn('option_id', $optionIds)
            ->get()
            ->groupBy('attribute_id')
            ->flatMap(function (Collection $options) {
                $attribute = $options->first()->attribute;

                if (! in_array($attribute?->backend_type, ['int', 'varchar', 'text'])) {
                    return [];
                }

                return config('rapidez.models.product')::withoutGlobalScopes()
                    ->whereHas('attribute'.ucfirst($attribute->backend_type), fn (Builder $query) => $query
                        ->withoutGlobalScopes()
                        ->where($query->qualifyColumn('attribute_id'), $attribute->attribute_id)
                        ->where(fn (Builder $query) => $options->each(
                            fn (self $option) => $query->orWhereRaw('FIND_IN_SET(?, '.$query->qualifyColumn('value').')', [$option->option_id])
                        ))
                    )
                    ->pluck('entity_id');
            });
    }
}
