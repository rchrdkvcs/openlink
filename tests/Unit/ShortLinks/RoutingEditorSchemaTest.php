<?php

namespace Tests\Unit\ShortLinks;

use App\Services\ShortLinks\Routing\RoutingEditorSchema;
use PHPUnit\Framework\TestCase;

class RoutingEditorSchemaTest extends TestCase
{
    public function test_condition_types_expose_their_operator_group_and_defaults(): void
    {
        $types = collect((new RoutingEditorSchema)->payload()['conditionTypes'])->keyBy('value');

        $this->assertSame(RoutingEditorSchema::conditionTypes(), $types->keys()->all());
        $this->assertSame(['value' => 'country', 'label' => 'Country', 'operatorGroup' => 'scalar'], $types['country']);
        $this->assertSame('scalar', $types['day_of_week']['operatorGroup']);
        $this->assertSame(['operatorGroup' => 'time', 'defaultOperator' => 'after', 'defaultValue' => ''], collect($types['date_time'])->only(['operatorGroup', 'defaultOperator', 'defaultValue'])->all());
        $this->assertSame(['from' => '09:00', 'to' => '18:00'], $types['time_of_day']['defaultValue']);
    }

    public function test_every_operator_group_used_by_a_condition_type_is_published(): void
    {
        $payload = (new RoutingEditorSchema)->payload();
        $groups = collect($payload['conditionTypes'])->pluck('operatorGroup')->unique()->sort()->values()->all();
        $operators = collect($payload['operators'])->flatten(1)->pluck('value')->all();

        $this->assertSame($groups, collect($payload['operators'])->keys()->sort()->values()->all());
        $this->assertSame(RoutingEditorSchema::operators(), $operators);
    }
}
