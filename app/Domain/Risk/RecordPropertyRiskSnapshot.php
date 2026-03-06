<?php

namespace App\Domain\Risk;

use App\Models\Property;
use App\Models\PropertyRiskSnapshot;
use Illuminate\Support\Facades\Date;

class RecordPropertyRiskSnapshot
{
    public function __construct(private readonly CalculatePropertyRiskScore $calculator) {}

    public function handle(Property|int $property): PropertyRiskSnapshot
    {
        $propertyId = $property instanceof Property ? $property->id : $property;

        $scores = $this->calculator->handle($propertyId);

        return PropertyRiskSnapshot::query()->create([
            'property_id' => $propertyId,
            'risk_score' => $scores['risk_score'],
            'open_issue_count' => $scores['open_issue_count'],
            'snapshot_date' => Date::today(),
            'created_at' => Date::now(),
        ]);
    }
}
