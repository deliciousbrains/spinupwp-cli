<?php

namespace App\Commands\Concerns;

use App\Field;
use SpinupWp\Resources\Resource;

trait SpecifyFields
{
    protected array $fieldsMap = [];

    protected function specifyFields(Resource $resource, array $fieldsFilter = []): array
    {
        if (empty($this->fieldsMap)) {
            return $resource->toArray();
        }

        $fields = [];

        if (empty($fieldsFilter)) {
            $fieldsFilter = $this->getCommandFieldsConfiguration($this->name, $this->profile());
        }

        if ($this->option('fields')) {
            $fieldsOption = str_replace(' ', '', strval($this->option('fields')));
            $fieldsFilter = explode(',', $fieldsOption);
        }

        $this->applyFilter($fieldsFilter);

        collect($this->fieldsMap)->each(function (Field $field) use ($resource, &$fields) {
            $label = $field->getDisplayLabel($this->displayFormat() === 'table');

            if (!property_exists($resource, $field->getName())) {
                return;
            }

            $value = $field->getDisplayValue($resource);

            if (!is_array($value)) {
                $fields[$label] = $value;
                return;
            }

            foreach ($value as $key => $_value) {
                $fields[$key] = $_value;
            }
        });

        return $fields;
    }

    protected function saveFieldsFilter(bool $saveConfiguration = false): void
    {
        $commandOptions = $this->config->getCommandConfiguration($this->name, $this->profile());

        if (!empty($commandOptions)) {
            return;
        }

        if (empty($commandOptions) && !$saveConfiguration) {
            $saveConfiguration = $this->confirm('Do you want to save the specified fields as the default for this command?', true);
        }

        if ($saveConfiguration) {
            $this->config->setCommandConfiguration($this->name, 'fields', strval($this->option('fields')), $this->profile());
            return;
        }

        $this->config->setCommandConfiguration($this->name, 'fields', '', $this->profile());
    }

    protected function applyFilter(?array $fieldsFilter): void
    {
        if (!empty($fieldsFilter)) {
            $this->fieldsMap = array_filter($this->fieldsMap, fn (Field $field) => $field->isInFilter($fieldsFilter));
        }
    }

    protected function shouldSpecifyFields(): bool
    {
        $commandOptions = $this->config->getCommandConfiguration($this->name, $this->profile());
        return $this->option('fields') || (isset($commandOptions['fields']) && !empty($commandOptions['fields']));
    }

    private function getCommandFieldsConfiguration(string $command, string $profile): ?array
    {
        $commandFields = data_get($this->config->getCommandConfiguration($command, $profile), 'fields');
        if (!$commandFields) {
            return null;
        }
        return explode(',', str_replace(' ', '', $commandFields));
    }
}
