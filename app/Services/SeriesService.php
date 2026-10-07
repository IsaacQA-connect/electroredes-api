<?php

namespace App\Services;

use App\Models\Series;
use Illuminate\Support\Facades\DB;

class SeriesService
{
    /**
     * Obtiene el siguiente correlativo y actualiza el contador de forma atómica.
     */
    public function getNextCorrelative(string $documentType, string $seriesPrefix): int
    {
        return DB::transaction(function () use ($documentType, $seriesPrefix) {
            $series = Series::where('document_type', $documentType)
                ->where('series', $seriesPrefix)
                ->lockForUpdate()
                ->first();

            if (!$series) {
                $series = Series::create([
                    'document_type'  => $documentType,
                    'series'         => $seriesPrefix,
                    'current_number' => 0,
                ]);
            }

            $series->increment('current_number');

            return $series->current_number;
        });
    }
}
