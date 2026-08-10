<?php

namespace MathiasOnea\Rulebook\Evaluations;

enum RuleEvaluationStatus: string
{
    case Applicable = 'applicable';
    case DoesNotApply = 'does_not_apply';
    case OutsideValidity = 'outside_validity';
}
