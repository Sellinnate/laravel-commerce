<?php

declare(strict_types=1);

namespace Selli\Commerce\Calculation;

use Selli\Commerce\Contracts\Calculator;

/**
 * Runs an ordered list of {@see Calculator} steps over a {@see Calculation}.
 *
 * The sequence is configuration, not code: moving tax before or after
 * discounts is a config line, not a refactor.
 */
final class Pipeline
{
    /** @var list<Calculator> */
    private array $calculators;

    /**
     * @param  iterable<Calculator>  $calculators
     */
    public function __construct(iterable $calculators = [])
    {
        $this->calculators = [];

        foreach ($calculators as $calculator) {
            $this->calculators[] = $calculator;
        }
    }

    public function pipe(Calculator $calculator): self
    {
        $this->calculators[] = $calculator;

        return $this;
    }

    /**
     * @return list<Calculator>
     */
    public function calculators(): array
    {
        return $this->calculators;
    }

    public function process(Calculation $calculation): Calculation
    {
        foreach ($this->calculators as $calculator) {
            $calculator->apply($calculation);
        }

        return $calculation;
    }
}
