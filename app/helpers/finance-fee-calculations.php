<?php
declare(strict_types=1);

/**
 * Convert a non-negative decimal currency string to integer kobo.
 * No binary floating-point arithmetic is used.
 */
function financeFeeAmountToCents(string $amount): int
{
    $amount = trim($amount);
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
        throw new InvalidArgumentException('Fee amounts must be non-negative numbers with at most two decimal places.');
    }
    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
    $whole = ltrim($whole, '0');
    if ($whole === '') $whole = '0';
    if (strlen($whole) > 12) throw new InvalidArgumentException('Fee amount is too large.');
    return ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
}

/**
 * Calculate a fee adjustment using integer kobo only.
 * Returns [normal fee cents, reduction cents, payable cents].
 */
function financeCalculateFeeAmountsCents(int $baseCents, string $adjustmentType, string $value): array
{
    if ($baseCents < 0) throw new InvalidArgumentException('The base fee cannot be negative.');
    $valueCents = financeFeeAmountToCents($value);
    $discountCents = 0;
    if ($adjustmentType === 'fixed_fee') {
        if ($valueCents > $baseCents) throw new DomainException('Negotiated final fee cannot exceed the normal section fee.');
        $discountCents = $baseCents - $valueCents;
    } elseif ($adjustmentType === 'discount_amount') {
        $discountCents = min($baseCents, $valueCents);
    } elseif ($adjustmentType === 'discount_percent') {
        if ($valueCents > 10000) throw new DomainException('Percentage scholarship cannot exceed 100%.');
        $discountCents = intdiv(($baseCents * $valueCents) + 5000, 10000);
    } elseif ($adjustmentType === 'full_scholarship') {
        $discountCents = $baseCents;
    } else {
        throw new InvalidArgumentException('Unsupported student fee adjustment type.');
    }
    $discountCents = min($baseCents, $discountCents);
    return [$baseCents, $discountCents, max(0, $baseCents - $discountCents)];
}
