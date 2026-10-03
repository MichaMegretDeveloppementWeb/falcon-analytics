<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * How a fiche's controller reads the row it shows, once the ability has
 * answered.
 *
 * A row that is not there raises, and the package's page says « introuvable ».
 * A read that fails is logged and gives nothing · the screen then says itself
 * what it could not load, inside the host's layout, rather than a raw error.
 *
 * @internal
 */
trait ReadsTheRowItShows
{
    /**
     * @template TModel of Model
     *
     * @param  Closure(): TModel  $read
     * @return TModel|null
     */
    private function rowOrNothing(Closure $read, int $id): ?Model
    {
        try {
            return $read();
        } catch (ModelNotFoundException $missing) {
            throw $missing;
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics record read failed.', [
                'controller' => static::class,
                'id' => $id,
                'exception' => $e,
            ]);

            return null;
        }
    }
}
