<?php
namespace Linkdoc\Metrics\Core\Storage;

interface Adapter
{
    const COMMAND_INCREMENT_INTEGER = 1;
    const COMMAND_INCREMENT_FLOAT = 2;
    const COMMAND_SET = 3;

    /**
     * @return array
     */
    public function collect(): array;

    /**
     * @param array $data
     * @return void
     */
    public function updateGauge(array $data): void;

    /**
     * @param array $data
     * @return void
     */
    public function updateCounter(array $data): void;
}
