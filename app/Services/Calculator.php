<?php

namespace App\Services;

use App\Models\User;

/**
 * 金額の計算(インボイス制度の書き方に合わせる)
 *  ・明細ごとの金額 = 数量 × 単価(1円未満は四捨五入)
 *  ・消費税は「税率ごとに合計してから」1回だけ計算し、端数は設定(切り捨て・四捨五入・切り上げ)に従う
 */
class Calculator
{
    /**
     * @param  iterable<array{quantity: float|int|string, unit_price: int|string, tax_rate: int|string}|\App\Models\DocumentItem>  $items
     * @return array{rates: array<int, array{base: int, tax: int}>, subtotal: int, tax: int, total: int}
     */
    public static function totals(iterable $items, string $rounding = 'floor'): array
    {
        $bases = [];
        foreach ($items as $item) {
            $rate = (int) data_get($item, 'tax_rate', 10);
            $amount = (int) round((float) data_get($item, 'quantity', 0) * (int) data_get($item, 'unit_price', 0));
            $bases[$rate] = ($bases[$rate] ?? 0) + $amount;
        }
        krsort($bases); // 10% → 8% → 非課税 の順

        $rates = [];
        foreach ($bases as $rate => $base) {
            $rates[$rate] = ['base' => $base, 'tax' => self::round($base * $rate / 100, $rounding)];
        }
        $subtotal = array_sum(array_column($rates, 'base'));
        $tax = array_sum(array_column($rates, 'tax'));

        return ['rates' => $rates, 'subtotal' => $subtotal, 'tax' => $tax, 'total' => $subtotal + $tax];
    }

    public static function round(float $value, string $rounding): int
    {
        // 浮動小数点の誤差(1100 × 0.1 = 110.00000000000001 など)で切り上げにならないよう、先に小さく丸める
        $value = round($value, 6);

        return (int) match ($rounding) {
            'ceil' => ceil($value),
            'round' => round($value),
            default => floor($value),
        };
    }

    /** ¥12,345 */
    public static function yen(int $value): string
    {
        return '¥'.number_format($value);
    }

    public static function roundingOf(?User $user): string
    {
        return array_key_exists($user?->rounding, User::ROUNDINGS) ? $user->rounding : 'floor';
    }
}
