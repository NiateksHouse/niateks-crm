<?php

namespace App\Services;

use App\Models\KozaRecord;
use Brick\Math\BigDecimal as D;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class KozaEconomics
{
    public function quote(KozaRecord $record): array
    {
        $sales = D::of('0');
        $cost = D::of('0');
        $complete = true;
        $lines = DB::table('koza_lines')->where('record_id', $record->id)->get();
        foreach ($lines as $line) {
            $sales = $sales->plus(D::of($line->quantity)->multipliedBy($line->unit_price));
            if ($line->unit_cost === null) {
                $complete = false;
            } else {
                $cost = $cost->plus(D::of($line->quantity)->multipliedBy($line->unit_cost));
            }
        }
        foreach (['packaging_cost', 'sample_cost', 'freight_cost', 'commission_cost', 'reserve_cost', 'other_cost'] as $key) {
            if (! isset($record->data[$key]) || $record->data[$key] === '') {
                $complete = false;
            } else {
                $cost = $cost->plus($record->data[$key]);
            }
        }
        $contribution = $sales->minus($cost);

        return ['currency' => $record->data['currency'] ?? null, 'complete' => $complete && $lines->isNotEmpty(),
            'net_sales' => (string) $sales->toScale(2, RoundingMode::HalfUp), 'cost' => (string) $cost->toScale(2, RoundingMode::HalfUp),
            'contribution' => (string) $contribution->toScale(2, RoundingMode::HalfUp),
            'rate' => $sales->isZero() ? null : (string) $contribution->multipliedBy(100)->dividedBy($sales, 4, RoundingMode::HalfUp),
            'basis' => 'estimate', 'formula' => 'lines revenue - lines cost - packaging - sample - freight - commission - reserve - other'];
    }
}
