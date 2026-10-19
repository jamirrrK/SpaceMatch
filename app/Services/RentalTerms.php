<?php

namespace App\Services;

use App\Models\Space;

class RentalTerms
{
    public static function snapshot(Space $space): array
    {
        return [
            'monthly_rent' => $space->price,
            'deposit_amount' => $space->deposit_amount,
            'advance_months' => $space->advance_months,
            'house_rules' => $space->house_rules,
            'move_in_requirements' => $space->move_in_requirements,
            'cancellation_policy' => $space->cancellation_policy,
            'utilities_notes' => $space->utilities_notes,
            'other_fees' => 'No other fees have been disclosed in this listing. Confirm any proposed additional fees before agreeing.',
        ];
    }

    public static function matches(?array $accepted, Space $space): bool
    {
        if ($accepted === null) {
            return false;
        }
        $current = self::snapshot($space);
        // MySQL's native JSON storage can reorder object keys; acceptance is order independent.
        ksort($accepted);
        ksort($current);

        return $accepted === $current;
    }

    public static function fingerprint(Space $space): string
    {
        return hash('sha256', json_encode(self::snapshot($space), JSON_THROW_ON_ERROR));
    }
}
